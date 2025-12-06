<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $string_translations;
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // لود ترجمه‌های دستی
        $saved_strings = get_option( 'fikup_string_translations', [] );
        $this->string_translations = [];
        if( is_array($saved_strings) ) {
            foreach($saved_strings as $item) {
                if(!empty($item['org']) && !empty($item['trans'])) {
                    $this->string_translations[ $item['org'] ] = $item['trans'];
                }
            }
        }

        // کش کردن ID ها
        $this->en_header_id = get_option( 'fikup_woodmart_header_id' );
        $this->en_footer_id = get_option( 'fikup_woodmart_footer_id' );

        // 1. تغییر هدر (مخصوص WoodMart Header Builder)
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );

        // 2. تغییر تنظیمات قالب (برای فوتر و سایر آپشن‌ها)
        add_filter( 'woodmart_option', [ $this, 'override_theme_options' ], 999, 2 );

        // 3. تغییرات متای پست
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );

        // 4. ترجمه کلمات دستی
        add_filter( 'gettext', [ $this, 'translate_strings' ], 20, 3 );
        
        // 5. CSS اختصاصی
        add_action( 'wp_head', [ $this, 'print_custom_css' ] );

        // 6. [جدید و مهم] جلوگیری از لود فایل ترجمه قالب و ووکامرس
        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
    }

    /**
     * این تابع جلوی لود شدن فایل‌های ترجمه فارسی را می‌گیرد
     * تا متون به زبان اصلی کد (انگلیسی) نمایش داده شوند.
     */
    public function unload_persian_translations( $mofile, $domain ) {
        // اگر انگلیسی نیستیم، کاری نکن (بذار ترجمه فارسی لود شه)
        if ( ! $this->is_english_context() ) {
            return $mofile;
        }

        // لیست دامین‌هایی که باید انگلیسی بمانند
        // woodmart: قالب
        // woocommerce: فروشگاه ساز (دکمه افزودن به سبد و...)
        // woodmart-core: هسته قالب
        $blocked_domains = [ 'woodmart', 'woocommerce', 'woodmart-core' ];

        if ( in_array( $domain, $blocked_domains ) ) {
            // برگرداندن مسیر خالی باعث می‌شود وردپرس نتواند فایل ترجمه را پیدا کند
            // و در نتیجه متن انگلیسی پیش‌فرض را نشان می‌دهد.
            return ''; 
        }

        return $mofile;
    }

    public function swap_header_builder_id( $id ) {
        if ( $this->is_english_context() && ! empty( $this->en_header_id ) ) {
            return $this->en_header_id;
        }
        return $id;
    }

    public function override_theme_options( $value, $slug ) {
        if ( ! $this->is_english_context() ) return $value;

        if ( $slug === 'footer_content_type' ) {
            return 'html_block';
        }

        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) {
            return $this->en_footer_id;
        }

        return $value;
    }

    public function force_layout_via_meta( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() ) return $value;
        if ( ! $this->is_english_context() ) return $value;

        if ( $meta_key === '_woodmart_whb_header' && ! empty( $this->en_header_id ) ) {
            return $this->en_header_id;
        }

        if ( $meta_key === '_woodmart_footer_content_type' ) {
            return 'html_block';
        }
        if ( $meta_key === '_woodmart_footer_html_block' && ! empty( $this->en_footer_id ) ) {
            return $this->en_footer_id;
        }

        return $value;
    }

    private function is_english_context() {
        if ( class_exists('Fikup_Poly_Language') && Fikup_Poly_Language::is_english() ) {
            return true;
        }
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) {
            return true;
        }
        if ( isset( $_GET['lang'] ) && $_GET['lang'] === 'en' ) {
            return true;
        }
        return false;
    }

    public function translate_strings( $translated, $text, $domain ) {
        if ( ! $this->is_english_context() ) return $translated;
        
        if ( isset( $this->string_translations[ $text ] ) ) {
            return $this->string_translations[ $text ];
        }
        return $translated;
    }

    public function print_custom_css() {
        if ( $this->is_english_context() ) {
            $css = get_option( 'fikup_custom_css_en' );
            if ( ! empty( $css ) ) {
                echo '<style>' . wp_strip_all_tags( $css ) . '</style>';
            }
        }
    }
}