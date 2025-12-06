<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $string_translations;
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // لود ترجمه‌های دستی از تنظیمات
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

        // 1. هوک تغییر هدر (Header Builder)
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );

        // 2. هوک تغییر تنظیمات قالب (Footer & Options)
        add_filter( 'woodmart_option', [ $this, 'override_theme_options' ], 999, 2 );

        // 3. هوک تغییر متای پست (Layouts)
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );

        // 4. ترجمه کلمات دستی
        add_filter( 'gettext', [ $this, 'translate_strings' ], 20, 3 );
        
        // 5. لود کردن استایل‌ها و فونت انگلیسی
        add_action( 'wp_head', [ $this, 'print_custom_css' ] );

        // 6. غیرفعال کردن فایل ترجمه فارسی در حالت انگلیسی
        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
    }

    /**
     * جلوگیری از لود شدن ترجمه فارسی قالب و ووکامرس
     */
    public function unload_persian_translations( $mofile, $domain ) {
        if ( ! $this->is_english_context() ) return $mofile;

        $blocked_domains = [ 'woodmart', 'woocommerce', 'woodmart-core' ];
        if ( in_array( $domain, $blocked_domains ) ) {
            return ''; 
        }
        return $mofile;
    }

    /**
     * تغییر هدر
     */
    public function swap_header_builder_id( $id ) {
        if ( $this->is_english_context() && ! empty( $this->en_header_id ) ) {
            return $this->en_header_id;
        }
        return $id;
    }

    /**
     * تغییر تنظیمات قالب (مثل فوتر)
     */
    public function override_theme_options( $value, $slug ) {
        if ( ! $this->is_english_context() ) return $value;

        if ( $slug === 'footer_content_type' ) return 'html_block';
        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;

        return $value;
    }

    /**
     * تغییر تنظیمات صفحه (متا دیتا)
     */
    public function force_layout_via_meta( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() ) return $value;
        if ( ! $this->is_english_context() ) return $value;

        if ( $meta_key === '_woodmart_whb_header' && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        
        if ( $meta_key === '_woodmart_footer_content_type' ) return 'html_block';
        if ( $meta_key === '_woodmart_footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;

        return $value;
    }

    /**
     * تشخیص محیط انگلیسی
     */
    private function is_english_context() {
        if ( class_exists('Fikup_Poly_Language') && Fikup_Poly_Language::is_english() ) return true;
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) return true;
        if ( isset( $_GET['lang'] ) && $_GET['lang'] === 'en' ) return true;
        return false;
    }

    /**
     * ترجمه کلمات
     */
    public function translate_strings( $translated, $text, $domain ) {
        if ( ! $this->is_english_context() ) return $translated;
        
        if ( isset( $this->string_translations[ $text ] ) ) {
            return $this->string_translations[ $text ];
        }
        return $translated;
    }

    /**
     * چاپ CSS و فونت انگلیسی (اصلاح شده)
     */
    public function print_custom_css() {
        if ( $this->is_english_context() ) {
            // 1. لود کردن فونت Roboto از گوگل
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
            echo '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">';
            
            echo '<style>';
            // 2. اعمال فونت Roboto روی تمام عناصر متنی
            echo '
                body.fikup-en-mode {
                    font-family: "Roboto", sans-serif !important;
                }
                
                /* اعمال اجباری روی تگ‌های متنی رایج */
                body.fikup-en-mode h1, body.fikup-en-mode h2, body.fikup-en-mode h3, 
                body.fikup-en-mode h4, body.fikup-en-mode h5, body.fikup-en-mode h6,
                body.fikup-en-mode p, body.fikup-en-mode a, body.fikup-en-mode li, 
                body.fikup-en-mode span, body.fikup-en-mode strong, body.fikup-en-mode b,
                body.fikup-en-mode button, body.fikup-en-mode input, body.fikup-en-mode textarea, 
                body.fikup-en-mode select, body.fikup-en-mode .woodmart-entry-content {
                    font-family: "Roboto", sans-serif !important;
                }
                
                /* 3. استثنا قائل شدن برای آیکون‌ها (تا مربع نشان داده نشوند) */
                body.fikup-en-mode [class*="wd-icon-"],
                body.fikup-en-mode .woodmart-font,
                body.fikup-en-mode .wd-tools-icon {
                    font-family: "woodmart-font" !important;
                }
                body.fikup-en-mode .fa, body.fikup-en-mode .fas, body.fikup-en-mode .far, body.fikup-en-mode .fab {
                    font-family: "Font Awesome 5 Free" !important;
                }
            ';
            
            // 4. اضافه کردن کدهای CSS سفارشی از تنظیمات پلاگین
            $css = get_option( 'fikup_custom_css_en' );
            if ( ! empty( $css ) ) {
                echo wp_strip_all_tags( $css );
            }
            echo '</style>';
        }
    }
}