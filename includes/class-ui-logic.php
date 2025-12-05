<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $string_translations;
    private $en_header_id;

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

        // کش کردن ID هدر انگلیسی برای استفاده در توابع
        $this->en_header_id = get_option( 'fikup_woodmart_header_id' );

        // 1. هوک استاندارد وودمارت (برای صفحات عمومی مثل آرشیو/سرچ)
        add_filter( 'woodmart_header_id', [ $this, 'swap_header_global' ], 99999 );

        // 2. [تیر خلاص] هوک متا دیتا (برای صفحات تکی و محصولات)
        // این هوک باعث می‌شود تنظیمات داخلی صفحه نادیده گرفته شود
        add_filter( 'get_post_metadata', [ $this, 'force_header_via_meta' ], 10, 4 );

        // 3. ترجمه کلمات
        add_filter( 'gettext', [ $this, 'translate_strings' ], 20, 3 );
        
        // 4. CSS
        add_action( 'wp_head', [ $this, 'print_custom_css' ] );
    }

    /**
     * روش 1: تغییر هدر برای کل سایت (Global)
     */
    public function swap_header_global( $id ) {
        if ( $this->is_english_context() && ! empty( $this->en_header_id ) ) {
            return $this->en_header_id;
        }
        return $id;
    }

    /**
     * روش 2: تغییر هدر با دستکاری متا دیتا (Meta Override)
     * وقتی وودمارت سعی میکند تنظیمات صفحه را بخواند، ما ID انگلیسی را به او میدهیم.
     */
    public function force_header_via_meta( $value, $object_id, $meta_key, $single ) {
        // فقط در فرانت‌اند اجرا شود
        if ( is_admin() ) return $value;

        // فقط اگر کلید درخواستی _woodmart_header_id باشد
        if ( $meta_key === '_woodmart_header_id' ) {
            if ( $this->is_english_context() && ! empty( $this->en_header_id ) ) {
                return $this->en_header_id;
            }
        }

        return $value;
    }

    /**
     * تابع تشخیص زبان قدرتمند (بدون وابستگی به هوک‌های اولیه)
     */
    private function is_english_context() {
        // 1. بررسی کلاس زبان (اگر لود شده باشد)
        if ( class_exists('Fikup_Poly_Language') && Fikup_Poly_Language::is_english() ) {
            return true;
        }
        // 2. بررسی URL به صورت خام (برای اطمینان 100%)
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) {
            return true;
        }
        // 3. بررسی کوئری استرینگ
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