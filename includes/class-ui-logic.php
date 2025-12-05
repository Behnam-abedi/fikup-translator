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

        // 1. هوک تغییر هدر وودمارت (با اولویت بسیار بالا)
        add_filter( 'woodmart_header_id', [ $this, 'swap_header' ], 99999 );

        // 2. ترجمه کلمات
        add_filter( 'gettext', [ $this, 'translate_strings' ], 20, 3 );
        
        // 3. CSS
        add_action( 'wp_head', [ $this, 'print_custom_css' ] );
    }

    /**
     * سوئیچ کردن هدر در حالت انگلیسی
     */
    public function swap_header( $id ) {
        // تشخیص زبان: هم از طریق کلاس اصلی چک می‌کنیم، هم مستقیم از URL برای اطمینان 100%
        $is_en = Fikup_Poly_Language::is_english();
        
        if ( ! $is_en ) {
            // چک کردن دستی URL برای مواقعی که هوک‌های وردپرس هنوز لود نشده‌اند
            if ( strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) {
                $is_en = true;
            }
        }

        if ( $is_en ) {
            $en_header_id = get_option( 'fikup_woodmart_header_id' );
            
            // مطمئن شویم که ID خالی نیست
            if ( ! empty( $en_header_id ) ) {
                return $en_header_id;
            }
        }
        
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