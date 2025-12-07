<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // آماده‌سازی لیست ترجمه
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

        // --- هوک‌های اصلی ---
        
        add_filter( 'gettext', [ $this, 'universal_translator' ], 9999, 3 );
        add_filter( 'gettext_with_context', [ $this, 'universal_translator_context' ], 9999, 4 );
        add_filter( 'ngettext', [ $this, 'universal_translator_plural' ], 9999, 5 );

        add_filter( 'woodmart_option', [ $this, 'translate_theme_options' ], 999, 2 );

        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );

        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
        add_filter( 'option_persian_woocommerce_replacements', [ $this, 'disable_persian_replacements' ] );

        // اسکریپت حیاتی برای تشخیص زبان در ایجکس
        add_action( 'wp_head', [ $this, 'print_custom_css_and_js' ], 1 );
    }

    /**
     * منطق تشخیص زبان "فوق‌العاده دقیق" (بدون کوکی)
     */
    private function is_english_context() {
        // 1. اگر کلاس زبان قبلاً تشخیص داده که انگلیسی است (از طریق هدر ایجکس یا URL)
        if ( Fikup_Poly_Language::is_english() ) {
            return true;
        }

        // 2. فال‌بک (محض اطمینان): چک کردن URL در حالت غیر ایجکس
        if ( ! wp_doing_ajax() ) {
            if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) {
                return true;
            }
        }

        // 3. فال‌بک نهایی برای ایجکس: بررسی هدر اختصاصی
        if ( wp_doing_ajax() ) {
            if ( isset( $_SERVER['HTTP_X_FIKUP_LANG'] ) && $_SERVER['HTTP_X_FIKUP_LANG'] === 'en' ) {
                return true;
            }
        }

        return false;
    }

    public function universal_translator( $translated, $text, $domain ) {
        if ( ! $this->is_english_context() ) return $translated;

        $clean_translated = trim( $translated );
        if ( isset( $this->translations_map[ $clean_translated ] ) ) {
            return $this->translations_map[ $clean_translated ];
        }

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

    public function translate_theme_options( $value, $slug ) {
        if ( ! $this->is_english_context() ) return $value;

        if ( $slug === 'footer_content_type' ) return 'html_block';
        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;

        if ( is_string( $value ) ) {
            $clean_val = trim( $value );
            if ( isset( $this->translations_map[ $clean_val ] ) ) {
                return $this->translations_map[ $clean_val ];
            }
        }

        $persian_defaults = [
            'empty_cart_text', 'mini_cart_view_cart_text', 'mini_cart_checkout_text', 
            'btn_view_cart_text', 'btn_checkout_text', 'popup_added_to_cart_message',
            'copyrights', 'copyrights2'
        ];
        if ( in_array( $slug, $persian_defaults ) ) return '';

        return $value;
    }

    public function unload_persian_translations( $mofile, $domain ) {
        if ( ! $this->is_english_context() ) return $mofile;
        $blocked = [ 'woodmart', 'woocommerce', 'woodmart-core', 'woocommerce-persian', 'persian-woocommerce', 'wooc-fa' ];
        if ( in_array( $domain, $blocked ) ) return ''; 
        return $mofile;
    }

    public function disable_persian_replacements( $value ) {
        if ( $this->is_english_context() ) return [];
        return $value;
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
        /**
         * FIKUP LANG SYSTEM (Stateless/Cookie-less)
         * این اسکریپت وظیفه دارد به سرور بگوید در چه زبانی هستیم.
         * منبع تشخیص: فقط URL مرورگر
         */
        (function() {
            var isEn = window.location.pathname.indexOf('/en/') !== -1;
            
            // 1. jQuery AJAX (برای ووکامرس و قالب)
            if ( typeof jQuery !== 'undefined' ) {
                jQuery.ajaxPrefilter(function( options, originalOptions, jqXHR ) {
                    if ( isEn ) {
                        jqXHR.setRequestHeader('X-Fikup-Lang', 'en');
                    }
                });
            }

            // 2. Fetch API (برای تکنولوژی‌های مدرن‌تر)
            var originalFetch = window.fetch;
            window.fetch = function(url, options) {
                if ( isEn ) {
                    options = options || {};
                    options.headers = options.headers || {};
                    if (options.headers instanceof Headers) {
                        options.headers.append('X-Fikup-Lang', 'en');
                    } else {
                        options.headers['X-Fikup-Lang'] = 'en';
                    }
                }
                return originalFetch(url, options);
            };

            // 3. XMLHttpRequest (برای پلاگین‌های خیلی قدیمی)
            var originalOpen = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function(method, url) {
                this.addEventListener('loadstart', function() {
                   if ( isEn ) {
                       this.setRequestHeader('X-Fikup-Lang', 'en');
                   }
                });
                originalOpen.apply(this, arguments);
            };

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