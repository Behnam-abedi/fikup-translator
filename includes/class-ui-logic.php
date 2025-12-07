<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // لود تنظیمات
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

        // --- 1. هوک‌های ترجمه و قالب (اولویت بالا) ---
        add_filter( 'gettext', [ $this, 'universal_translator' ], 9999, 3 );
        add_filter( 'gettext_with_context', [ $this, 'universal_translator_context' ], 9999, 4 );
        add_filter( 'woodmart_option', [ $this, 'translate_theme_options' ], 999, 2 );
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );
        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
        add_filter( 'option_persian_woocommerce_replacements', [ $this, 'disable_persian_replacements' ] );

        // --- 2. اصلاح‌کننده دیکتاتوری زبان (The Override) ---
        // این هوک با اولویت 20 اجرا می‌شود تا تصمیمات class-language.php (که اولویت 1 دارد) را لغو کند
        add_filter( 'locale', [ $this, 'force_ajax_locale_by_referer' ], 20 );

        // --- 3. جلوگیری از کش سرور ---
        // جدا کردن فایل‌های کش شده ووکامرس بر اساس زبان
        add_filter( 'woocommerce_cart_hash', [ $this, 'split_cart_hash_by_lang' ] );

        // --- 4. اسکریپت ساده مدیریت کش مرورگر ---
        add_action( 'wp_head', [ $this, 'print_cache_buster_js' ], 1 );
    }

    /**
     * هسته مرکزی تشخیص زبان (بر اساس Referer)
     * این تابع تصمیم می‌گیرد که الان سایت باید انگلیسی باشد یا فارسی
     */
    private function get_referer_based_lang() {
        // اگر ایجکس نیست، همان منطق URL عادی
        if ( ! wp_doing_ajax() ) {
            if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) {
                return 'en';
            }
            return 'fa';
        }

        // اگر ایجکس است، به "Referer" (صفحه‌ای که کاربر در آن است) نگاه کن
        $referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
        
        // اگر رفرر وجود داشت و توش /en/ بود، یعنی کاربر در صفحه انگلیسیه
        if ( $referer && strpos( $referer, '/en/' ) !== false ) {
            return 'en';
        }

        // در غیر این صورت قطعا فارسیه
        return 'fa';
    }

    private function is_english_context() {
        return $this->get_referer_based_lang() === 'en';
    }

    /**
     * تغییر اجباری Locale وردپرس در لحظه درخواست ایجکس
     * این باعث می‌شود ترجمه‌های ووکامرس (gettext) درست بارگذاری شوند
     */
    public function force_ajax_locale_by_referer( $locale ) {
        if ( wp_doing_ajax() ) {
            $lang = $this->get_referer_based_lang();
            if ( $lang === 'en' ) {
                return 'en_US';
            } else {
                return 'fa_IR'; // برگرداندن به فارسی حتی اگر کوکی انگلیسی ست شده باشد
            }
        }
        return $locale;
    }

    /**
     * تغییر هش سبد خرید
     * باعث می‌شود ووکامرس HTML جدید بسازد و از کش قبلی استفاده نکند
     */
    public function split_cart_hash_by_lang( $hash ) {
        return $hash . '-' . $this->get_referer_based_lang();
    }

    // --- توابع ترجمه ---
    public function universal_translator( $translated, $text, $domain ) {
        if ( ! $this->is_english_context() ) return $translated;
        $clean = trim( $translated );
        if ( isset( $this->translations_map[ $clean ] ) ) return $this->translations_map[ $clean ];
        if ( isset( $this->translations_map[ trim($text) ] ) ) return $this->translations_map[ trim($text) ];
        return $translated;
    }
    public function universal_translator_context( $translated, $text, $context, $domain ) { return $this->universal_translator( $translated, $text, $domain ); }

    public function translate_theme_options( $value, $slug ) {
        if ( ! $this->is_english_context() ) return $value;
        if ( $slug === 'footer_content_type' ) return 'html_block';
        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        if ( is_string( $value ) && isset( $this->translations_map[ trim($value) ] ) ) return $this->translations_map[ trim($value) ];
        
        // مقادیر خالی برای اینکه قالب انگلیسی پیش‌فرض را لود کند
        $defaults = [ 'empty_cart_text', 'mini_cart_view_cart_text', 'mini_cart_checkout_text', 'btn_view_cart_text', 'btn_checkout_text', 'copyrights' ];
        if ( in_array( $slug, $defaults ) ) return '';
        return $value;
    }

    public function unload_persian_translations( $mofile, $domain ) {
        if ( ! $this->is_english_context() ) return $mofile;
        $blocked = [ 'woodmart', 'woocommerce', 'woodmart-core', 'woocommerce-persian', 'persian-woocommerce', 'wooc-fa' ];
        if ( in_array( $domain, $blocked ) ) return ''; 
        return $mofile;
    }
    public function disable_persian_replacements( $value ) { return $this->is_english_context() ? [] : $value; }
    public function swap_header_builder_id( $id ) { return ( $this->is_english_context() && ! empty( $this->en_header_id ) ) ? $this->en_header_id : $id; }
    
    public function force_layout_via_meta( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() || ! $this->is_english_context() ) return $value;
        if ( $meta_key === '_woodmart_whb_header' && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        if ( $meta_key === '_woodmart_footer_content_type' ) return 'html_block';
        if ( $meta_key === '_woodmart_footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        return $value;
    }

    /**
     * اسکریپت ساده و قطعی برای پاکسازی کش مرورگر
     * فقط وقتی اجرا می‌شود که زبان تغییر کرده باشد.
     */
    public function print_cache_buster_js() {
        ?>
        <script>
        (function() {
            // زبانِ آدرس فعلی چیست؟
            var isEn = window.location.pathname.indexOf('/en/') !== -1;
            var currentLang = isEn ? 'en' : 'fa';
            
            try {
                // آخرین زبانی که مرورگر یادش است چیست؟
                var savedLang = localStorage.getItem('fikup_active_lang');
                
                // اگر زبان عوض شده (یا دفعه اول است)
                if ( savedLang !== currentLang ) {
                    // 1. پاک کردن کش‌های ووکامرس در مرورگر
                    sessionStorage.removeItem('wc_fragments_hash');
                    sessionStorage.removeItem('wc_fragments');
                    sessionStorage.removeItem('wc_cart_hash_data');
                    sessionStorage.removeItem('wc_cart_created');
                    
                    // 2. ذخیره زبان جدید
                    localStorage.setItem('fikup_active_lang', currentLang);
                    
                    // 3. دستور رفرش به ووکامرس (اگر در صفحه لود شده باشد)
                    if ( typeof jQuery !== 'undefined' ) {
                        jQuery(document.body).trigger('wc_fragment_refresh');
                    }
                }
            } catch(e) {}
            
            // استایل‌های انگلیسی
            if ( isEn ) {
                var css = 'body.fikup-en-mode, .fikup-en-mode { font-family: "Roboto", sans-serif !important; }';
                var style = document.createElement('style');
                style.innerHTML = css;
                document.head.appendChild(style);
                document.body.classList.add('fikup-en-mode');
            }
        })();
        </script>
        <?php
    }
}