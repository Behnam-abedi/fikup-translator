<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        $this->log( 'Init: Fikup UI Logic Loaded.' );

        // لود تنظیمات
        $saved_strings = get_option( 'fikup_translations_list', [] );
        if( is_array($saved_strings) ) {
            foreach($saved_strings as $item) {
                if(!empty($item['key'])) {
                    $this->translations_map[ trim( $item['key'] ) ] = $item['val'];
                }
            }
        }
        $this->en_header_id = get_option( 'fikup_woodmart_header_id' );
        $this->en_footer_id = get_option( 'fikup_woodmart_footer_id' );

        // 1. هوک‌های ترجمه و قالب
        add_filter( 'gettext', [ $this, 'universal_translator' ], 9999, 3 );
        add_filter( 'gettext_with_context', [ $this, 'universal_translator_context' ], 9999, 4 );
        add_filter( 'woodmart_option', [ $this, 'translate_theme_options' ], 999, 2 );
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );
        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
        add_filter( 'option_persian_woocommerce_replacements', [ $this, 'disable_persian_replacements' ] );

        // 2. هوک تشخیص زبان (The Override)
        add_filter( 'locale', [ $this, 'force_ajax_locale_by_referer' ], 20 );

        // 3. هوک هش سبد خرید
        add_filter( 'woocommerce_cart_hash', [ $this, 'split_cart_hash_by_lang' ] );

        // 4. اسکریپت دیباگر در فرانت
        add_action( 'wp_head', [ $this, 'print_debug_js' ], 1 );
    }

    /**
     * سیستم لاگ‌برداری در فایل debug.log
     */
    private function log( $msg ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Fikup Debug] ' . $msg );
        }
    }

    /**
     * منطق تشخیص زبان با لاگ کامل
     */
    private function get_referer_based_lang() {
        // اگر درخواست عادی است (Page Load)
        if ( ! wp_doing_ajax() ) {
            if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) {
                return 'en';
            }
            return 'fa';
        }

        // اگر درخواست ایجکس است
        $referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : 'NO_REFERER';
        $is_en_ref = strpos( $referer, '/en/' ) !== false;
        
        // لاگ کردن جزئیات درخواست ایجکس
        // $this->log( "AJAX Request Detected. Referer: $referer" );

        if ( $is_en_ref ) {
            // $this->log( "Decision: ENGLISH (based on referer)" );
            return 'en';
        }

        // $this->log( "Decision: PERSIAN (based on referer)" );
        return 'fa';
    }

    private function is_english_context() {
        return $this->get_referer_based_lang() === 'en';
    }

    public function force_ajax_locale_by_referer( $locale ) {
        if ( wp_doing_ajax() ) {
            $lang = $this->get_referer_based_lang();
            
            // اضافه کردن هدر برای دیدن در Network Tab مرورگر
            if ( ! headers_sent() ) {
                header( 'X-Fikup-Debug-Lang: ' . $lang );
                header( 'X-Fikup-Debug-Locale: ' . $locale );
            }

            if ( $lang === 'en' ) {
                return 'en_US';
            } else {
                return 'fa_IR';
            }
        }
        return $locale;
    }

    public function split_cart_hash_by_lang( $hash ) {
        $lang = $this->get_referer_based_lang();
        $new_hash = $hash . '-' . $lang;
        // $this->log( "Cart Hash Modified: $new_hash" );
        return $new_hash;
    }

    // --- توابع ترجمه ---
    public function universal_translator( $translated, $text, $domain ) {
        if ( ! $this->is_english_context() ) return $translated;
        $clean = trim( $translated );
        if ( isset( $this->translations_map[ $clean ] ) ) return $this->translations_map[ $clean ];
        if ( isset( $this->translations_map[ trim($text) ] ) ) return $this->translations_map[ trim($text) ];
        return $translated;
    }
    public function universal_translator_context( $translated, $text, $context, $domain ) { return $this->universal_translator( $translated, $text, $domain ); }

    public function translate_theme_options( $value, $slug ) {
        if ( ! $this->is_english_context() ) return $value;
        if ( $slug === 'footer_content_type' ) return 'html_block';
        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        if ( is_string( $value ) && isset( $this->translations_map[ trim($value) ] ) ) return $this->translations_map[ trim($value) ];
        $defaults = [ 'empty_cart_text', 'mini_cart_view_cart_text', 'mini_cart_checkout_text', 'btn_view_cart_text', 'btn_checkout_text', 'copyrights' ];
        if ( in_array( $slug, $defaults ) ) return '';
        return $value;
    }

    public function unload_persian_translations( $mofile, $domain ) {
        if ( ! $this->is_english_context() ) return $mofile;
        $blocked = [ 'woodmart', 'woocommerce', 'woodmart-core', 'woocommerce-persian', 'persian-woocommerce', 'wooc-fa' ];
        if ( in_array( $domain, $blocked ) ) return ''; 
        return $mofile;
    }
    public function disable_persian_replacements( $value ) { return $this->is_english_context() ? [] : $value; }
    public function swap_header_builder_id( $id ) { return ( $this->is_english_context() && ! empty( $this->en_header_id ) ) ? $this->en_header_id : $id; }
    
    public function force_layout_via_meta( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() || ! $this->is_english_context() ) return $value;
        if ( $meta_key === '_woodmart_whb_header' && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        if ( $meta_key === '_woodmart_footer_content_type' ) return 'html_block';
        if ( $meta_key === '_woodmart_footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        return $value;
    }

    /**
     * اسکریپت دیباگر کنسول
     */
    public function print_debug_js() {
        ?>
        <script>
        (function() {
            var isEn = window.location.pathname.indexOf('/en/') !== -1;
            var currentLang = isEn ? 'en' : 'fa';
            
            console.group("🔴 Fikup Debugger");
            console.log("URL Path:", window.location.pathname);
            console.log("Detected Lang (JS):", currentLang);

            try {
                var savedLang = localStorage.getItem('fikup_active_lang');
                console.log("Saved Lang in Storage:", savedLang);
                
                if ( savedLang !== currentLang ) {
                    console.warn("⚠️ Mismatch Detected! Clearing Cache...");
                    
                    sessionStorage.removeItem('wc_fragments_hash');
                    sessionStorage.removeItem('wc_fragments');
                    sessionStorage.removeItem('wc_cart_hash_data');
                    sessionStorage.removeItem('wc_cart_created');
                    
                    localStorage.setItem('fikup_active_lang', currentLang);
                    
                    if ( typeof jQuery !== 'undefined' ) {
                        console.log("🚀 Triggering wc_fragment_refresh...");
                        jQuery(document.body).trigger('wc_fragment_refresh');
                    } else {
                        console.error("❌ jQuery is not loaded yet!");
                    }
                } else {
                    console.log("✅ Lang matches storage. No cache clear needed.");
                }
            } catch(e) {
                console.error("Storage Error:", e);
            }
            console.groupEnd();
            
            if ( isEn ) {
                var css = 'body.fikup-en-mode, .fikup-en-mode { font-family: "Roboto", sans-serif !important; }';
                var style = document.createElement('style');
                style.innerHTML = css;
                document.head.appendChild(style);
                document.body.classList.add('fikup-en-mode');
            }
        })();
        </script>
        <?php
    }
}