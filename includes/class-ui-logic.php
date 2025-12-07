<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // 1. بارگذاری ترجمه‌ها در یک آرایه برای جستجوی سریع
        $saved_strings = get_option( 'fikup_string_translations', [] );
        if( is_array($saved_strings) ) {
            foreach($saved_strings as $item) {
                if(!empty($item['key']) && !empty($item['val'])) {
                    // کلید = متن اصلی (مثلاً "تومان")
                    // مقدار = ترجمه (مثلاً "Toman")
                    $key = trim( $item['key'] ); 
                    $this->translations_map[ $key ] = $item['val'];
                }
            }
        }

        $this->en_header_id = get_option( 'fikup_woodmart_header_id' );
        $this->en_footer_id = get_option( 'fikup_woodmart_footer_id' );

        // هوک‌های اصلی
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );
        add_action( 'wp_head', [ $this, 'print_custom_css_and_js' ] );
        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
        add_filter( 'option_persian_woocommerce_replacements', [ $this, 'disable_persian_replacements' ] );

        // --- بخش جدید: ترجمه هوشمند مقادیر ---
        
        // 1. ترجمه تنظیمات قالب WoodMart (مثل سبد خالی، کپی رایت و...)
        add_filter( 'woodmart_option', [ $this, 'smart_translate_theme_options' ], 999, 2 );
        
        // 2. ترجمه واحد پول (تومان -> Toman)
        add_filter( 'woocommerce_currency_symbol', [ $this, 'smart_translate_currency' ], 999, 2 );
        
        // 3. ترجمه متون کدنویسی شده (Gettext)
        add_filter( 'gettext', [ $this, 'smart_translate_gettext' ], PHP_INT_MAX, 3 );
        add_filter( 'gettext_with_context', [ $this, 'smart_translate_gettext_context' ], PHP_INT_MAX, 4 );
    }

    /**
     * ترجمه هوشمند تنظیمات قالب
     * اگر مقدار تنظیم با یکی از "متن‌های اصلی" کاربر برابر بود، ترجمه کن.
     */
    public function smart_translate_theme_options( $value, $slug ) {
        if ( ! $this->is_english_context() ) return $value;

        // تغییر اجباری فوتر
        if ( $slug === 'footer_content_type' ) return 'html_block';
        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;

        // اگر مقدار این تنظیم، در لیست ترجمه‌های کاربر وجود دارد، ترجمه را برگردان
        if ( is_string( $value ) ) {
            $clean_val = trim( $value ); // حذف فاصله احتمالی
            if ( isset( $this->translations_map[ $clean_val ] ) ) {
                return $this->translations_map[ $clean_val ];
            }
        }

        return $value;
    }

    /**
     * ترجمه هوشمند واحد پول (تومان)
     */
    public function smart_translate_currency( $currency_symbol, $currency ) {
        if ( ! $this->is_english_context() ) return $currency_symbol;

        // اگر کاربر برای نماد فعلی (مثلاً "تومان") ترجمه نوشته باشد
        if ( isset( $this->translations_map[ $currency_symbol ] ) ) {
            return $this->translations_map[ $currency_symbol ];
        }
        
        return $currency_symbol;
    }

    /**
     * ترجمه هوشمند متون (Gettext)
     */
    public function smart_translate_gettext( $translated, $text, $domain ) {
        if ( ! $this->is_english_context() ) return $translated;

        // 1. اولویت اول: اگر کاربر دقیقاً این متن را ترجمه کرده است
        $clean_translated = trim( $translated ); // متن نهایی (که شاید فارسی شده باشد)
        if ( isset( $this->translations_map[ $clean_translated ] ) ) {
            return $this->translations_map[ $clean_translated ];
        }

        // 2. اولویت دوم: شاید کاربر متن انگلیسی اصلی را ترجمه کرده باشد
        $clean_text = trim( $text );
        if ( isset( $this->translations_map[ $clean_text ] ) ) {
            return $this->translations_map[ $clean_text ];
        }

        return $translated;
    }

    public function smart_translate_gettext_context( $translated, $text, $context, $domain ) {
        return $this->smart_translate_gettext( $translated, $text, $domain );
    }

    // --- توابع کمکی قبلی ---

    private function is_english_context() {
        if ( is_admin() && ! wp_doing_ajax() ) return false;
        if ( isset( $_COOKIE['fikup_lang'] ) && $_COOKIE['fikup_lang'] === 'en' ) return true;
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) return true;
        if ( isset( $_GET['lang'] ) && $_GET['lang'] === 'en' ) return true;
        if ( wp_doing_ajax() || isset( $_GET['wc-ajax'] ) ) {
            if ( isset( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'], '/en/' ) !== false ) return true;
        }
        return false;
    }

    public function disable_persian_replacements( $value ) {
        if ( $this->is_english_context() ) return [];
        return $value;
    }

    public function unload_persian_translations( $mofile, $domain ) {
        if ( ! $this->is_english_context() ) return $mofile;
        $blocked = [ 'woodmart', 'woocommerce', 'woodmart-core', 'woocommerce-persian', 'persian-woocommerce', 'wooc-fa' ];
        if ( in_array( $domain, $blocked ) ) return ''; 
        return $mofile;
    }

    public function swap_header_builder_id( $id ) {
        if ( $this->is_english_context() && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        return $id;
    }

    public function force_layout_via_meta( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() ) return $value;
        if ( ! $this->is_english_context() ) return $value;
        if ( $meta_key === '_woodmart_whb_header' && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        if ( $meta_key === '_woodmart_footer_content_type' ) return 'html_block';
        if ( $meta_key === '_woodmart_footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        return $value;
    }

    public function print_custom_css_and_js() {
        ?>
        <script>
        (function() {
            function setFikupCookie(name, value, days) {
                var d = new Date(); d.setTime(d.getTime() + (days*24*60*60*1000));
                document.cookie = name + "=" + (value || "")  + "; expires=" + d.toUTCString() + "; path=/";
            }
            if ( window.location.pathname.indexOf('/en/') !== -1 ) {
                setFikupCookie('fikup_lang', 'en', 30);
                if ( typeof jQuery !== 'undefined' ) {
                    jQuery( document ).ajaxSend(function(event, xhr, settings) {
                        xhr.setRequestHeader('X-Fikup-Lang', 'en');
                    });
                }
            } else {
                if ( document.cookie.indexOf('fikup_lang=en') !== -1 ) {
                    setFikupCookie('fikup_lang', 'fa', 30);
                }
            }
        })();
        </script>
        <?php

        if ( $this->is_english_context() ) {
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