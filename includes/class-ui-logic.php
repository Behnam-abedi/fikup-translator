<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $string_translations;

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

        // 1. هوک تغییر هدر وودمارت (مهم‌ترین بخش)
        add_filter( 'woodmart_header_id', [ $this, 'swap_header' ] );

        // 2. ترجمه کلمات
        add_filter( 'gettext', [ $this, 'translate_strings' ], 20, 3 );
        
        // 3. CSS
        add_action( 'wp_head', [ $this, 'print_custom_css' ] );
    }

    /**
     * سوئیچ کردن هدر در حالت انگلیسی
     */
    public function swap_header( $id ) {
        // اگر سایت در حالت انگلیسی است
        if ( Fikup_Poly_Language::is_english() ) {
            
            // آی‌دی هدر انگلیسی را از تنظیمات بگیر
            $en_header_id = get_option( 'fikup_woodmart_header_id' );
            
            // اگر تنظیم شده بود، آن را برگردان تا وودمارت آن را لود کند
            if ( ! empty( $en_header_id ) ) {
                return $en_header_id;
            }
        }
        
        // در غیر این صورت همان هدر پیش‌فرض (فارسی) را برگردان
        return $id;
    }

    public function translate_strings( $translated, $text, $domain ) {
        if ( ! Fikup_Poly_Language::is_english() ) return $translated;
        
        if ( isset( $this->string_translations[ $text ] ) ) {
            return $this->string_translations[ $text ];
        }
        return $translated;
    }

    public function print_custom_css() {
        if ( Fikup_Poly_Language::is_english() ) {
            $css = get_option( 'fikup_custom_css_en' );
            if ( ! empty( $css ) ) {
                echo '<style>' . wp_strip_all_tags( $css ) . '</style>';
            }
        }
    }
}