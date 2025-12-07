<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // لود تنظیمات ترجمه
        $saved_strings = get_option( 'fikup_translations_list', [] );
        if( is_array($saved_strings) ) {
            foreach($saved_strings as $item) {
                if(!empty($item['key'])) {
                    $this->translations_map[ trim( $item['key'] ) ] = $item['val'];
                }
            }
        }
        $this->en_header_id = get_option( 'fikup_woodmart_header_id' );
        $this->en_footer_id = get_option( 'fikup_woodmart_footer_id' );

        // --- هوک‌های اصلی ---
        
        // ترجمه متن‌ها
        add_filter( 'gettext', [ $this, 'universal_translator' ], 9999, 3 );
        add_filter( 'gettext_with_context', [ $this, 'universal_translator_context' ], 9999, 4 );
        
        // ترجمه تنظیمات قالب
        add_filter( 'woodmart_option', [ $this, 'translate_theme_options' ], 999, 2 );

        // تغییر هدر/فوتر
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );

        // --- بخش حیاتی: پروتکل ریست کردن کش ---
        // این هوک باعث می‌شود برای هر زبان، یک هش جداگانه در سرور ساخته شود
        add_filter( 'woocommerce_cart_hash', [ $this, 'server_side_hash_split' ] );

        // این اسکریپت وظیفه دارد کش مرورگر را هنگام تغییر زبان منفجر کند
        add_action( 'wp_head', [ $this, 'client_side_cache_buster' ], 0 );
    }

    /**
     * منطق تشخیص زبان (Server Side)
     */
    private function is_english() {
        if ( ! wp_doing_ajax() ) {
            return isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false;
        }
        // در ایجکس به رفرر اعتماد می‌کنیم
        $referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
        return strpos( $referer, '/en/' ) !== false;
    }

    /**
     * جداسازی هش سبد خرید در سرور
     * این باعث می‌شود حتی اگر کش مرورگر پاک شد، سرور دیتای جدید بدهد نه دیتای کش شده خودش را
     */
    public function server_side_hash_split( $hash ) {
        $lang = $this->is_english() ? 'en' : 'fa';
        return $hash . '-' . $lang;
    }

    // --- توابع ترجمه و قالب (استاندارد) ---

    public function universal_translator( $translated, $text, $domain ) {
        if ( ! $this->is_english() ) return $translated;
        $clean = trim( $translated );
        if ( isset( $this->translations_map[ $clean ] ) ) return $this->translations_map[ $clean ];
        if ( isset( $this->translations_map[ trim($text) ] ) ) return $this->translations_map[ trim($text) ];
        return $translated;
    }

    public function universal_translator_context( $translated, $text, $context, $domain ) {
        return $this->universal_translator( $translated, $text, $domain );
    }

    public function translate_theme_options( $value, $slug ) {
        if ( ! $this->is_english() ) return $value;
        if ( $slug === 'footer_content_type' ) return 'html_block';
        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        if ( is_string( $value ) && isset( $this->translations_map[ trim($value) ] ) ) return $this->translations_map[ trim($value) ];
        $defaults = [ 'empty_cart_text', 'mini_cart_view_cart_text', 'mini_cart_checkout_text', 'btn_view_cart_text', 'btn_checkout_text', 'copyrights' ];
        if ( in_array( $slug, $defaults ) ) return '';
        return $value;
    }

    public function swap_header_builder_id( $id ) {
        if ( $this->is_english() && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        return $id;
    }

    public function force_layout_via_meta( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() || ! $this->is_english() ) return $value;
        if ( $meta_key === '_woodmart_whb_header' && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        if ( $meta_key === '_woodmart_footer_content_type' ) return 'html_block';
        if ( $meta_key === '_woodmart_footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        return $value;
    }

    /**
     * اسکریپت هوشمند مدیریت کش (State Mismatch Protocol)
     * این کد در مرورگر کاربر اجرا می‌شود.
     */
    public function client_side_cache_buster() {
        ?>
        <script>
        (function() {
            // ۱. تشخیص زبان فعلی صفحه (حقیقت مطلق)
            var currentUrl = window.location.pathname;
            var isCurrentPageEn = currentUrl.indexOf('/en/') !== -1;
            var realLang = isCurrentPageEn ? 'en' : 'fa';

            // نام کلیدی که وضعیت قبلی را در آن نگه می‌داریم
            var stateKey = 'fikup_cart_lang_state';

            try {
                // ۲. خواندن وضعیت قبلی از حافظه مرورگر
                var lastKnownLang = localStorage.getItem(stateKey);

                // ۳. مقایسه: آیا زبان عوض شده است؟
                // اگر دفعه اول است (null) یا زبان فرق کرده
                if ( lastKnownLang !== realLang ) {
                    console.log('Fikup: Language switched from ' + lastKnownLang + ' to ' + realLang + '. PURGING CACHE.');

                    // ۴. پاکسازی بی‌رحمانه تمام کش‌های ووکامرس
                    // این‌ها فقط "نمایش" سبد خرید هستند، نه خود محصولات
                    sessionStorage.removeItem('wc_fragments_hash'); // هش محتوا
                    sessionStorage.removeItem('wc_fragments');      // HTML سبد خرید
                    sessionStorage.removeItem('wc_cart_hash_data');
                    sessionStorage.removeItem('wc_cart_created');
                    
                    // حذف کوکی هش سبد خرید (بسیار مهم برای ووکامرس)
                    document.cookie = 'woocommerce_cart_hash=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                    
                    // ۵. بروزرسانی وضعیت جدید در حافظه
                    localStorage.setItem(stateKey, realLang);

                    // ۶. دستور رفرش به ووکامرس
                    // ما صبر میکنیم تا صفحه کامل لود شود، سپس دستور آپدیت میدهیم
                    document.addEventListener("DOMContentLoaded", function() {
                        if ( typeof jQuery !== 'undefined' ) {
                            // این دستور به ووکامرس می‌گوید: "کش تو خالی است، برو از سرور بگیر"
                            jQuery( document.body ).trigger( 'wc_fragment_refresh' );
                            jQuery( document.body ).trigger( 'removed_from_cart' ); // تریگر کمکی برای قالب وودمارت
                        }
                    });
                } else {
                    console.log('Fikup: Language matches. Cache is valid.');
                }
            } catch(e) {
                console.error('Fikup Cache Error:', e);
            }
        })();
        </script>
        <?php
    }
}