<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $string_translations;
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // لود ترجمه‌ها
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

        // 1. هوک تغییر ID فوتر و هدر (برای اطمینان)
        add_filter( 'woodmart_header_id', [ $this, 'swap_header_global' ], 99999 );
        add_filter( 'woodmart_footer_id', [ $this, 'swap_footer_global' ], 99999 );

        // 2. [مهم] هوک تغییر تنظیمات اصلی قالب (Options)
        // این بخش باعث می‌شود اگر فوتر فارسی "ابزارک" بود، در انگلیسی به "HTML Block" تبدیل شود
        add_filter( 'woodmart_get_opt', [ $this, 'force_theme_options' ], 99999, 2 );

        // 3. [تیر خلاص] هوک متا دیتا (برای صفحات تکی و محصولات)
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );

        // 4. ترجمه کلمات
        add_filter( 'gettext', [ $this, 'translate_strings' ], 20, 3 );
        
        // 5. CSS
        add_action( 'wp_head', [ $this, 'print_custom_css' ] );
    }

    /**
     * تغییر ID هدر
     */
    public function swap_header_global( $id ) {
        if ( $this->is_english_context() && ! empty( $this->en_header_id ) ) {
            return $this->en_header_id;
        }
        return $id;
    }

    /**
     * تغییر ID فوتر
     */
    public function swap_footer_global( $id ) {
        if ( $this->is_english_context() && ! empty( $this->en_footer_id ) ) {
            return $this->en_footer_id;
        }
        return $id;
    }

    /**
     * [بخش جدید] مجبور کردن تنظیمات قالب به استفاده از HTML Block در انگلیسی
     */
    public function force_theme_options( $value, $key ) {
        if ( ! $this->is_english_context() ) return $value;

        // اگر قالب پرسید "نوع فوتر چیست؟"، بگو: "HTML Block"
        if ( $key === 'footer_content_type' ) {
            return 'html_block';
        }

        // اگر قالب پرسید "کدام بلوک برای فوتر؟"، ID انگلیسی را بده
        if ( $key === 'footer_html_block' && ! empty( $this->en_footer_id ) ) {
            return $this->en_footer_id;
        }

        return $value;
    }

    /**
     * تغییر تنظیمات صفحه (Meta Override)
     */
    public function force_layout_via_meta( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() ) return $value;
        if ( ! $this->is_english_context() ) return $value;

        // تغییر هدر
        if ( $meta_key === '_woodmart_header_id' && ! empty( $this->en_header_id ) ) {
            return $this->en_header_id;
        }

        // تغییر فوتر
        if ( $meta_key === '_woodmart_footer_id' && ! empty( $this->en_footer_id ) ) {
            return $this->en_footer_id;
        }

        // اگر صفحه‌ای تنظیم شده بود که از ابزارک استفاده کند، مجبورش کن از HTML Block استفاده کند
        if ( $meta_key === '_woodmart_footer_content_type' ) {
            return 'html_block';
        }

        return $value;
    }

    /**
     * تشخیص زبان
     */
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