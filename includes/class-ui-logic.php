<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // ۱. پروتکل سکوت برای ایجکس
        if ( wp_doing_ajax() ) {
            @error_reporting(0);
            @ini_set('display_errors', 0);
            if ( ! ob_get_level() ) ob_start();
            add_action( 'shutdown', function() {
                if ( ob_get_length() ) {
                    $out = ob_get_contents();
                    if ( strpos( trim($out), '{' ) !== 0 && strpos( trim($out), '[' ) !== 0 ) {
                        ob_clean();
                    }
                }
            }, 0 );
        }

        $saved_strings = get_option( 'fikup_translations_list', [] );
        if( is_array($saved_strings) ) {
            foreach($saved_strings as $item) {
                if(!empty($item['key'])) $this->translations_map[ trim( $item['key'] ) ] = $item['val'];
            }
        }
        $this->en_header_id = get_option( 'fikup_woodmart_header_id' );
        $this->en_footer_id = get_option( 'fikup_woodmart_footer_id' );

        add_filter( 'gettext', [ $this, 'universal_translator' ], 9999, 3 );
        add_filter( 'gettext_with_context', [ $this, 'universal_translator_context' ], 9999, 4 );
        add_filter( 'woodmart_option', [ $this, 'translate_theme_options' ], 999, 2 );
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );
        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
        add_filter( 'woocommerce_cart_hash', [ $this, 'split_cart_hash' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'force_english_ajax_url' ], 20 );
    }

    /**
     * تشخیص زبان (اصلاح شده)
     */
    private function is_english() {
        // ۱. پارامتر مستقیم در URL
        if ( isset( $_GET['lang'] ) && $_GET['lang'] === 'en' ) return true;

        // ۲. ساختار آدرس (مهم‌ترین شرط)
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) return true;

        // ۳. بررسی رفرر (فقط و فقط برای درخواست‌های AJAX)
        // تغییر مهم: این شرط باعث باگ شما شده بود چون در لود معمولی هم چک می‌شد
        if ( wp_doing_ajax() ) {
            $referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
            if ( strpos( $referer, '/en/' ) !== false ) return true;
        }

        return false;
    }

    public function split_cart_hash( $hash ) {
        return $hash . '-' . ( $this->is_english() ? 'en' : 'fa' );
    }

    public function force_english_ajax_url() {
        if ( ! $this->is_english() ) return;

        $script = "
            if ( typeof wc_cart_fragments_params === 'undefined' ) {
                var wc_cart_fragments_params = {};
            }
            wc_cart_fragments_params.fragment_name = 'wc_fragments_en_';
            
            if ( typeof wc_cart_fragments_params.wc_ajax_url !== 'undefined' ) {
                var base = wc_cart_fragments_params.wc_ajax_url;
                if ( base.indexOf('/en/') === -1 && base.indexOf('lang=en') === -1 ) {
                     var separator = base.indexOf('?') !== -1 ? '&' : '?';
                     wc_cart_fragments_params.wc_ajax_url = base + separator + 'lang=en';
                }
            }
        ";
        wp_add_inline_script( 'wc-cart-fragments', $script, 'before' );
        echo '<style>body.fikup-en-mode { font-family: "Roboto", sans-serif !important; direction: ltr !important; }</style>';
    }

    // --- توابع ترجمه ---

    public function unload_persian_translations( $mofile, $domain ) {
        if ( ! $this->is_english() ) return $mofile;
        $blocked = [ 'woodmart', 'woocommerce', 'woodmart-core', 'woocommerce-persian', 'persian-woocommerce', 'wooc-fa' ];
        if ( in_array( $domain, $blocked ) ) return ''; 
        if ( strpos( $mofile, 'fa_IR' ) !== false ) return '';
        return $mofile;
    }

    public function universal_translator( $translated, $text, $domain ) {
        if ( ! $this->is_english() ) return $translated;
        $clean = trim( $translated );
        if ( isset( $this->translations_map[ $clean ] ) ) return $this->translations_map[ $clean ];
        if ( isset( $this->translations_map[ trim($text) ] ) ) return $this->translations_map[ trim($text) ];
        return $translated;
    }
    public function universal_translator_context( $translated, $text, $context, $domain ) { return $this->universal_translator( $translated, $text, $domain ); }

    public function translate_theme_options( $value, $slug ) {
        if ( ! $this->is_english() ) return $value;
        if ( $slug === 'footer_content_type' ) return 'html_block';
        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        if ( is_string( $value ) && isset( $this->translations_map[ trim($value) ] ) ) return $this->translations_map[ trim($value) ];
        
        $defaults = [ 'empty_cart_text', 'mini_cart_view_cart_text', 'mini_cart_checkout_text', 'btn_view_cart_text', 'btn_checkout_text', 'copyrights' ];
        if ( in_array( $slug, $defaults ) ) return '';
        
        return $value;
    }

    public function swap_header_builder_id( $id ) {
        if ( $this->is_english() && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        return $id;
    }

    public function force_layout_via_meta( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() || ! $this->is_english() ) return $value;
        if ( $meta_key === '_woodmart_whb_header' && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        if ( $meta_key === '_woodmart_footer_content_type' ) return 'html_block';
        if ( $meta_key === '_woodmart_footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        return $value;
    }
}