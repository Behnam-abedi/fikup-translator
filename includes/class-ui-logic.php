<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $string_translations;
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        $saved_strings = get_option( 'fikup_string_translations', [] );
        $this->string_translations = [];
        if( is_array($saved_strings) ) {
            foreach($saved_strings as $item) {
                if(!empty($item['org']) && !empty($item['trans'])) {
                    $key = trim( html_entity_decode( $item['org'] ) );
                    $this->string_translations[ $key ] = $item['trans'];
                }
            }
        }

        $this->en_header_id = get_option( 'fikup_woodmart_header_id' );
        $this->en_footer_id = get_option( 'fikup_woodmart_footer_id' );

        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'woodmart_option', [ $this, 'override_theme_options' ], 999, 2 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );
        add_filter( 'gettext', [ $this, 'translate_strings' ], 20, 3 );
        add_filter( 'gettext_with_context', [ $this, 'translate_strings_context' ], 20, 4 );
        add_action( 'wp_head', [ $this, 'print_custom_css' ] );
        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
    }

    private function is_english_context() {
        // 1. اولویت اول: کوکی (برای آژاکس عالی است)
        if ( isset( $_COOKIE['fikup_lang'] ) && $_COOKIE['fikup_lang'] === 'en' ) {
            return true;
        }

        // 2. URL
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) {
            return true;
        }
        
        // 3. کوئری استرینگ
        if ( isset( $_GET['lang'] ) && $_GET['lang'] === 'en' ) {
            return true;
        }

        // 4. کلاس زبان
        if ( class_exists('Fikup_Poly_Language') && Fikup_Poly_Language::is_english() ) {
            return true;
        }

        // 5. Referer برای آژاکس (پشتیبان کوکی)
        if ( wp_doing_ajax() ) {
            if ( isset( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'], '/en/' ) !== false ) {
                return true;
            }
        }

        return false;
    }

    public function unload_persian_translations( $mofile, $domain ) {
        if ( ! $this->is_english_context() ) return $mofile;
        $blocked_domains = [ 'woodmart', 'woocommerce', 'woodmart-core' ];
        if ( in_array( $domain, $blocked_domains ) ) {
            return ''; 
        }
        return $mofile;
    }

    public function translate_strings( $translated, $text, $domain ) {
        if ( ! $this->is_english_context() ) return $translated;
        $clean_text = trim( html_entity_decode( $text ) );
        if ( isset( $this->string_translations[ $clean_text ] ) ) {
            return $this->string_translations[ $clean_text ];
        }
        return $translated;
    }

    public function translate_strings_context( $translated, $text, $context, $domain ) {
        return $this->translate_strings( $translated, $text, $domain );
    }

    public function swap_header_builder_id( $id ) {
        if ( $this->is_english_context() && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        return $id;
    }

    public function override_theme_options( $value, $slug ) {
        if ( ! $this->is_english_context() ) return $value;

        if ( $slug === 'footer_content_type' ) return 'html_block';
        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;

        // پاک کردن مقادیر فارسی هاردکد شده در تنظیمات قالب
        $hardcoded_labels = [
            'mini_cart_view_cart_text',
            'mini_cart_checkout_text',
            'btn_view_cart_text',
            'btn_checkout_text',
            'empty_cart_text'
        ];
        if ( in_array( $slug, $hardcoded_labels ) ) return '';

        return $value;
    }

    public function force_layout_via_meta( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() ) return $value;
        if ( ! $this->is_english_context() ) return $value;
        if ( $meta_key === '_woodmart_whb_header' && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        if ( $meta_key === '_woodmart_footer_content_type' ) return 'html_block';
        if ( $meta_key === '_woodmart_footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        return $value;
    }

    public function print_custom_css() {
        if ( $this->is_english_context() ) {
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
            echo '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">';
            
            echo '<style>';
            echo '
                body.fikup-en-mode,
                body.fikup-en-mode h1, body.fikup-en-mode h2, body.fikup-en-mode h3, 
                body.fikup-en-mode h4, body.fikup-en-mode h5, body.fikup-en-mode h6,
                body.fikup-en-mode p, body.fikup-en-mode a, body.fikup-en-mode li, 
                body.fikup-en-mode span, body.fikup-en-mode div, 
                body.fikup-en-mode strong, body.fikup-en-mode b, body.fikup-en-mode i, body.fikup-en-mode em,
                body.fikup-en-mode button, body.fikup-en-mode input, body.fikup-en-mode textarea, 
                body.fikup-en-mode select, body.fikup-en-mode label,
                body.fikup-en-mode .btn, body.fikup-en-mode .button, body.fikup-en-mode .wd-btn {
                    font-family: "Roboto", sans-serif !important;
                }
                
                body.fikup-en-mode .wd-icon,
                body.fikup-en-mode span.wd-icon {
                    font-family: "woodmart-font" !important;
                }

                body.fikup-en-mode [class*="wd-icon-"],
                body.fikup-en-mode .woodmart-font,
                body.fikup-en-mode .wd-tools-icon,
                body.fikup-en-mode .wd-cross-icon,
                body.fikup-en-mode .wd-arrow-inner,
                body.fikup-en-mode .social-icon,
                body.fikup-en-mode .star-rating,
                body.fikup-en-mode .star-rating span:before {
                    font-family: "woodmart-font" !important;
                }

                body.fikup-en-mode .fa, body.fikup-en-mode .fas, body.fikup-en-mode .far {
                    font-family: "Font Awesome 5 Free" !important;
                }
                body.fikup-en-mode .fab {
                    font-family: "Font Awesome 5 Brands" !important;
                }
                body.fikup-en-mode i[class*="eicon-"] {
                    font-family: "eicons" !important;
                }
            ';
            
            $css = get_option( 'fikup_custom_css_en' );
            if ( ! empty( $css ) ) {
                echo wp_strip_all_tags( $css );
            }
            echo '</style>';
        }
    }
}