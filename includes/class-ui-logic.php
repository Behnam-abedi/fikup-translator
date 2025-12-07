<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // 1. آماده‌سازی لیست ترجمه
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
        
        // ترجمه متن‌ها
        add_filter( 'gettext', [ $this, 'universal_translator' ], 9999, 3 );
        add_filter( 'gettext_with_context', [ $this, 'universal_translator_context' ], 9999, 4 );
        add_filter( 'ngettext', [ $this, 'universal_translator_plural' ], 9999, 5 );

        // ترجمه تنظیمات قالب
        add_filter( 'woodmart_option', [ $this, 'translate_theme_options' ], 999, 2 );

        // تغییر هدر/فوتر
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );

        // غیرفعال کردن ترجمه فارسی در حالت انگلیسی
        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
        add_filter( 'option_persian_woocommerce_replacements', [ $this, 'disable_persian_replacements' ] );

        // +++ نکته کلیدی ۱: جدا کردن کش سرور بر اساس زبان +++
        add_filter( 'woocommerce_cart_hash', [ $this, 'split_cart_hash_by_lang' ] );

        // اسکریپت‌های حیاتی (چک‌کننده URL قبل از اجرا)
        add_action( 'wp_head', [ $this, 'print_custom_css_and_js' ], 1 );
    }

    /**
     * این تابع باعث می‌شود ووکامرس برای زبان فارسی و انگلیسی
     * دو کش کاملاً جداگانه در دیتابیس بسازد.
     */
    public function split_cart_hash_by_lang( $hash ) {
        $lang = $this->is_english_context() ? 'en' : 'fa';
        return $hash . '-' . $lang;
    }

    /**
     * منطق تشخیص زبان "فوق‌العاده دقیق"
     */
    private function is_english_context() {
        // 1. اولویت با کلاس اصلی زبان
        if ( class_exists('Fikup_Poly_Language') && Fikup_Poly_Language::is_english() ) {
            return true;
        }

        // 2. فال‌بک: چک کردن URL
        if ( ! wp_doing_ajax() ) {
            if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) {
                return true;
            }
        }

        // 3. درخواست‌های ایجکس
        if ( wp_doing_ajax() ) {
            // هدر اختصاصی JS
            if ( isset( $_SERVER['HTTP_X_FIKUP_LANG'] ) && $_SERVER['HTTP_X_FIKUP_LANG'] === 'en' ) {
                return true;
            }
            // رفرر (برای اطمینان)
            if ( isset( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'], '/en/' ) !== false ) {
                return true;
            }
        }

        return false;
    }

    public function universal_translator( $translated, $text, $domain ) {
        if ( ! $this->is_english_context() ) return $translated;
        $clean_translated = trim( $translated );
        if ( isset( $this->translations_map[ $clean_translated ] ) ) return $this->translations_map[ $clean_translated ];
        $clean_text = trim( $text );
        if ( isset( $this->translations_map[ $clean_text ] ) ) return $this->translations_map[ $clean_text ];
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
            if ( isset( $this->translations_map[ $clean_val ] ) ) return $this->translations_map[ $clean_val ];
        }

        $persian_defaults = [ 'empty_cart_text', 'mini_cart_view_cart_text', 'mini_cart_checkout_text', 'btn_view_cart_text', 'btn_checkout_text', 'popup_added_to_cart_message', 'copyrights', 'copyrights2' ];
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
         * FIKUP INTELLIGENT CART SYSTEM
         * این اسکریپت قبل از اینکه ووکامرس بخواهد چیزی را رندر کند،
         * چک می‌کند که آیا دیتای کش شده با URL فعلی همخوانی دارد یا نه.
         */
        (function() {
            var currentUrl = window.location.pathname;
            var isUrlEn = currentUrl.indexOf('/en/') !== -1;
            var storageKey = 'fikup_lang_state';
            
            // 1. بررسی وضعیت کش (Strict Check)
            try {
                var savedState = localStorage.getItem(storageKey);
                var currentState = isUrlEn ? 'en' : 'fa';

                // اگر وضعیت تغییر کرده (یا دفعه اول است)
                if ( savedState !== currentState ) {
                    console.log('Fikup: Language Context Mismatch! Nuke the cache.');
                    
                    // پاکسازی بی‌رحمانه تمام کش‌های ووکامرس
                    sessionStorage.removeItem('wc_fragments_hash');
                    sessionStorage.removeItem('wc_fragments');
                    sessionStorage.removeItem('wc_cart_hash_data');
                    sessionStorage.removeItem('wc_cart_created');
                    
                    // آپدیت وضعیت جدید
                    localStorage.setItem(storageKey, currentState);
                    
                    // یک فلگ می‌گذاریم که به ووکامرس بفهماند باید رفرش شود
                    sessionStorage.setItem('fikup_needs_refresh', 'yes');
                }
            } catch(e) { console.log(e); }

            // 2. تزریق هدر به درخواست‌های ایجکس
            if ( isUrlEn ) {
                if ( typeof jQuery !== 'undefined' ) {
                    jQuery.ajaxPrefilter(function( options, originalOptions, jqXHR ) {
                        jqXHR.setRequestHeader('X-Fikup-Lang', 'en');
                    });
                }
                var originalFetch = window.fetch;
                window.fetch = function(url, options) {
                    options = options || {};
                    options.headers = options.headers || {};
                    if (options.headers instanceof Headers) { options.headers.append('X-Fikup-Lang', 'en'); } 
                    else { options.headers['X-Fikup-Lang'] = 'en'; }
                    return originalFetch(url, options);
                };
                var originalOpen = XMLHttpRequest.prototype.open;
                XMLHttpRequest.prototype.open = function(method, url) {
                    this.addEventListener('loadstart', function() { this.setRequestHeader('X-Fikup-Lang', 'en'); });
                    originalOpen.apply(this, arguments);
                };
            }

            // 3. تریگر کردن رفرش بعد از لود صفحه (اگر نیاز بود)
            document.addEventListener("DOMContentLoaded", function() {
                if ( sessionStorage.getItem('fikup_needs_refresh') === 'yes' ) {
                    sessionStorage.removeItem('fikup_needs_refresh');
                    if ( typeof jQuery !== 'undefined' ) {
                        // این دستور به ووکامرس می‌گوید: همین الان برو از سرور دیتای جدید بگیر
                        jQuery( document.body ).trigger( 'wc_fragment_refresh' );
                    }
                }
            });

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