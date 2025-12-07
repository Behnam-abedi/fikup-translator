<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;
    private $is_english = false;

    public function __construct() {
        // 1. تشخیص زبان در همان ابتدای کار (Init)
        // این کار باعث می‌شود وضعیت زبان برای تمام هوک‌ها ثابت و مشخص باشد
        $this->detect_language_context();

        // 2. مدیریت کوکی‌ها در سمت سرور (حل مشکل کش شدن کوکی)
        add_action( 'init', [ $this, 'manage_language_cookie' ] );

        // 3. لود ترجمه‌ها
        $saved_strings = get_option( 'fikup_translations_list', [] );
        if( is_array($saved_strings) ) {
            foreach($saved_strings as $item) {
                if(!empty($item['key'])) {
                    $key = trim( $item['key'] ); 
                    $this->translations_map[ $key ] = $item['val'];
                }
            }
        }

        $this->en_header_id = get_option( 'fikup_woodmart_header_id' );
        $this->en_footer_id = get_option( 'fikup_woodmart_footer_id' );

        // --- اعمال تغییرات ---
        
        // ترجمه متن‌ها (هسته اصلی - مشابه ووکامرس فارسی)
        add_filter( 'gettext', [ $this, 'universal_translator' ], 9999, 3 );
        add_filter( 'gettext_with_context', [ $this, 'universal_translator_context' ], 9999, 4 );
        add_filter( 'ngettext', [ $this, 'universal_translator_plural' ], 9999, 5 );

        // ترجمه آپشن‌های قالب (مثل متن سبد خالی)
        add_filter( 'woodmart_option', [ $this, 'translate_theme_options' ], 999, 2 );

        // هوک‌های تغییر ساختار (هدر/فوتر)
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );

        // غیرفعال کردن ترجمه‌های فارسی مزاحم
        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
        add_filter( 'option_persian_woocommerce_replacements', [ $this, 'disable_persian_replacements' ] );

        // استایل‌ها
        add_action( 'wp_head', [ $this, 'print_custom_css' ] );
    }

    /**
     * منطق مرکزی تشخیص زبان
     * این تابع فقط یک بار اجرا می‌شود و وضعیت را در $this->is_english ذخیره می‌کند.
     */
    private function detect_language_context() {
        if ( is_admin() && ! wp_doing_ajax() ) {
            $this->is_english = false;
            return;
        }

        // 1. اولویت مطلق با URL است (صفحات معمولی)
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            // اگر آدرس انگلیسی است
            if ( strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) {
                $this->is_english = true;
                return;
            }
            // اگر آدرس فارسی است (و آژاکس نیست)، حتماً فارسی شود
            // این شرط باگ "گیر کردن روی انگلیسی" را حل می‌کند
            if ( ! wp_doing_ajax() && strpos( $_SERVER['REQUEST_URI'], '/wp-json/' ) === false ) {
                $this->is_english = false;
                return;
            }
        }

        // 2. اولویت دوم: Referer (برای درخواست‌های AJAX)
        if ( wp_doing_ajax() || isset( $_GET['wc-ajax'] ) ) {
            if ( isset( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'], '/en/' ) !== false ) {
                $this->is_english = true;
                return;
            }
        }

        // 3. اولویت سوم: پارامتر GET
        if ( isset( $_GET['lang'] ) && $_GET['lang'] === 'en' ) {
            $this->is_english = true;
            return;
        }

        $this->is_english = false;
    }

    /**
     * مدیریت کوکی زبان در سمت سرور (PHP)
     * این تابع تضمین می‌کند که کوکی مرورگر همیشه با URL هماهنگ باشد.
     */
    public function manage_language_cookie() {
        if ( is_admin() || wp_doing_ajax() ) return;

        $cookie_name = 'fikup_lang';
        
        // اگر الان در حالت انگلیسی هستیم ولی کوکی انگلیسی نیست -> ست کن
        if ( $this->is_english ) {
            if ( ! isset( $_COOKIE[ $cookie_name ] ) || $_COOKIE[ $cookie_name ] !== 'en' ) {
                setcookie( $cookie_name, 'en', time() + 30 * DAY_IN_SECONDS, '/' );
                $_COOKIE[ $cookie_name ] = 'en'; // آپدیت آنی برای همین درخواست
            }
        } 
        // اگر در حالت فارسی هستیم ولی کوکی انگلیسی مانده -> پاک کن یا فارسی کن
        else {
            if ( isset( $_COOKIE[ $cookie_name ] ) && $_COOKIE[ $cookie_name ] === 'en' ) {
                setcookie( $cookie_name, 'fa', time() + 30 * DAY_IN_SECONDS, '/' );
                $_COOKIE[ $cookie_name ] = 'fa'; // آپدیت آنی
            }
        }
    }

    /**
     * مترجم سراسری (جایگزین کننده متن)
     * هر متنی که از وردپرس رد شود (چه فارسی، چه انگلیسی) را چک می‌کند.
     */
    public function universal_translator( $translated, $text, $domain ) {
        if ( ! $this->is_english ) return $translated;

        // 1. چک کردن متن ترجمه شده (خروجی نهایی)
        $clean_translated = trim( $translated );
        if ( isset( $this->translations_map[ $clean_translated ] ) ) {
            return $this->translations_map[ $clean_translated ];
        }

        // 2. چک کردن متن اصلی (کد)
        $clean_text = trim( $text );
        if ( isset( $this->translations_map[ $clean_text ] ) ) {
            return $this->translations_map[ $clean_text ];
        }

        return $translated;
    }

    public function universal_translator_context( $translated, $text, $context, $domain ) {
        return $this->universal_translator( $translated, $text, $domain );
    }

    public function universal_translator_plural( $translation, $single, $plural, $number, $domain ) {
        return $this->universal_translator( $translation, $single, $domain );
    }

    /**
     * ترجمه آپشن‌های قالب (مثل متن سبد خالی)
     */
    public function translate_theme_options( $value, $slug ) {
        if ( ! $this->is_english ) return $value;

        // تغییر اجباری فوتر
        if ( $slug === 'footer_content_type' ) return 'html_block';
        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;

        // ترجمه مقادیر متنی تنظیمات
        if ( is_string( $value ) ) {
            $clean_val = trim( $value );
            if ( isset( $this->translations_map[ $clean_val ] ) ) {
                return $this->translations_map[ $clean_val ];
            }
        }

        return $value;
    }

    // --- سایر توابع (بدون تغییر منطق، فقط استفاده از $this->is_english) ---

    public function unload_persian_translations( $mofile, $domain ) {
        if ( ! $this->is_english ) return $mofile;
        $blocked = [ 'woodmart', 'woocommerce', 'woodmart-core', 'woocommerce-persian', 'persian-woocommerce', 'wooc-fa' ];
        if ( in_array( $domain, $blocked ) ) return ''; 
        return $mofile;
    }

    public function disable_persian_replacements( $value ) {
        if ( $this->is_english ) return [];
        return $value;
    }

    public function swap_header_builder_id( $id ) {
        if ( $this->is_english && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        return $id;
    }

    public function force_layout_via_meta( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() ) return $value;
        if ( ! $this->is_english ) return $value;
        if ( $meta_key === '_woodmart_whb_header' && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        if ( $meta_key === '_woodmart_footer_content_type' ) return 'html_block';
        if ( $meta_key === '_woodmart_footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        return $value;
    }

    public function print_custom_css() {
        if ( $this->is_english ) {
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
            echo '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">';
            echo '<style>';
            echo '
                body.fikup-en-mode, body.fikup-en-mode :not(.fa):not(.fas):not(.far):not(.fab):not([class*="wd-icon"]):not(.woodmart-font):not(.wd-tools-icon) {
                    font-family: "Roboto", sans-serif !important;
                }
                body.fikup-en-mode .fa, body.fikup-en-mode .fas, body.fikup-en-mode .far { font-family: "Font Awesome 5 Free" !important; }
                body.fikup-en-mode .fab { font-family: "Font Awesome 5 Brands" !important; }
                body.fikup-en-mode [class*="wd-icon-"], body.fikup-en-mode .woodmart-font, body.fikup-en-mode .wd-tools-icon { font-family: "woodmart-font" !important; }
            ';
            $css = get_option( 'fikup_custom_css_en' );
            if ( ! empty( $css ) ) echo wp_strip_all_tags( $css );
            echo '</style>';
        }
    }
}