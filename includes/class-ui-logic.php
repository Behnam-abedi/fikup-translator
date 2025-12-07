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
        add_filter( 'gettext', [ $this, 'universal_translator' ], 9999, 3 );
        add_filter( 'gettext_with_context', [ $this, 'universal_translator_context' ], 9999, 4 );
        add_filter( 'woodmart_option', [ $this, 'translate_theme_options' ], 999, 2 );
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );

        // --- بخش حیاتی: پروتکل اتمی ---
        // ۱. تغییر امضای سبد خرید در سرور
        add_filter( 'woocommerce_cart_hash', [ $this, 'server_side_hash_split' ] );

        // ۲. اسکریپت پاکسازی اتمی در مرورگر
        add_action( 'wp_head', [ $this, 'nuclear_cache_reset' ], 0 );
    }

    /**
     * تشخیص زبان (سمت سرور)
     */
    private function is_english() {
        if ( ! wp_doing_ajax() ) {
            return isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false;
        }
        $referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
        return strpos( $referer, '/en/' ) !== false;
    }

    /**
     * تغییر هش سبد خرید در دیتابیس
     * این باعث می‌شود سرور دیتای متفاوت برای زبان‌های مختلف تولید کند
     */
    public function server_side_hash_split( $hash ) {
        $lang = $this->is_english() ? 'en' : 'fa';
        return $hash . '-' . $lang;
    }

    // --- توابع ترجمه و قالب ---
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
     * پروتکل پاکسازی اتمی (The Nuclear Reset)
     * این اسکریپت با خشونت تمام کش‌ها را پاک می‌کند اگر زبان تغییر کرده باشد.
     */
    public function nuclear_cache_reset() {
        ?>
        <script>
        (function() {
            // ۱. زبان فعلی صفحه چیست؟
            var isEn = window.location.pathname.indexOf('/en/') !== -1;
            var currentLang = isEn ? 'en' : 'fa';
            
            // کلید ذخیره وضعیت در مرورگر
            var storageKey = 'fikup_lang_tracker';

            try {
                // ۲. زبان قبلی چه بوده؟
                var lastLang = localStorage.getItem(storageKey);

                // ۳. اگر زبان تغییر کرده (یا بار اول است)
                if ( lastLang !== currentLang ) {
                    console.log('Fikup: Language Mismatch (' + lastLang + ' -> ' + currentLang + '). Executing Nuclear Reset.');

                    // الف) پاکسازی کش HTML ووکامرس
                    sessionStorage.removeItem('wc_fragments_hash');
                    sessionStorage.removeItem('wc_fragments');
                    sessionStorage.removeItem('wc_cart_hash_data');
                    sessionStorage.removeItem('wc_cart_created');

                    // ب) حذف کوکی هش سبد خرید (مهم‌ترین بخش!)
                    // این کار باعث می‌شود ووکامرس بفهمد کش نامعتبر است
                    document.cookie = "woocommerce_cart_hash=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                    document.cookie = "woocommerce_items_in_cart=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";

                    // ج) بروزرسانی وضعیت زبان
                    localStorage.setItem(storageKey, currentLang);

                    // د) دستور رفرش به ووکامرس (با کمی تاخیر برای اطمینان)
                    window.addEventListener('load', function() {
                        setTimeout(function(){
                            if ( typeof jQuery !== 'undefined' ) {
                                jQuery( document.body ).trigger( 'wc_fragment_refresh' );
                                jQuery( document.body ).trigger( 'removed_from_cart' );
                                console.log('Fikup: Refresh Triggered.');
                            }
                        }, 500); // نیم ثانیه صبر برای لود کامل اسکریپت‌های قالب
                    });
                }
            } catch(e) {
                console.error('Fikup Reset Error:', e);
            }

            // استایل‌های انگلیسی
            if ( isEn ) {
                var css = 'body.fikup-en-mode, .fikup-en-mode { font-family: "Roboto", sans-serif !important; }';
                var s = document.createElement('style');
                s.innerHTML = css;
                document.head.appendChild(s);
                document.body.classList.add('fikup-en-mode');
            }
        })();
        </script>
        <?php
    }
}