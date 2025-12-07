<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // ۱. غیرفعال کردن نمایش خطاها (حیاتی برای سالم ماندن ایجکس)
        if ( wp_doing_ajax() ) {
            @ini_set( 'display_errors', 0 );
        }

        // لود تنظیمات ترجمه
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

        // --- هوک‌های اصلی ---
        add_filter( 'gettext', [ $this, 'universal_translator' ], 9999, 3 );
        add_filter( 'gettext_with_context', [ $this, 'universal_translator_context' ], 9999, 4 );
        add_filter( 'woodmart_option', [ $this, 'translate_theme_options' ], 999, 2 );
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );

        // --- شاهکار جداسازی (Isolation Logic) ---
        // ۱. جداسازی هش در سمت سرور
        add_filter( 'woocommerce_cart_hash', [ $this, 'server_side_hash_split' ] );

        // ۲. جداسازی سطل‌های ذخیره‌سازی در سمت کلاینت (مرورگر)
        add_action( 'wp_enqueue_scripts', [ $this, 'isolate_client_fragments' ], 20 );
    }

    /**
     * تشخیص زبان
     */
    private function is_english() {
        if ( ! wp_doing_ajax() ) {
            return isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false;
        }
        $referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
        return strpos( $referer, '/en/' ) !== false;
    }

    /**
     * جداسازی سرور: هر زبان، هش مخصوص خودش را دارد.
     */
    public function server_side_hash_split( $hash ) {
        $lang = $this->is_english() ? 'en' : 'fa';
        return $hash . '-' . $lang;
    }

    /**
     * جداسازی کلاینت: تغییر نام کلیدهای ذخیره‌سازی ووکامرس
     * این باعث می‌شود تداخل کش بین دو زبان غیرممکن شود.
     */
    public function isolate_client_fragments() {
        $lang = $this->is_english() ? 'en' : 'fa';
        
        // این اسکریپت دقیقاً قبل از فایل اصلی ووکامرس اجرا می‌شود
        // و تنظیمات آن را تغییر می‌دهد تا در "سطل" دیگری ذخیره کند.
        $script = "
            if ( typeof wc_cart_fragments_params === 'undefined' ) {
                var wc_cart_fragments_params = {};
            }
            // تغییر نام کلید ذخیره‌سازی (مثلاً: wc_fragments_fa_...)
            wc_cart_fragments_params.fragment_name = 'wc_fragments_" . $lang . "_';
            
            // اجبار به استفاده از آدرس ایجکس زبان‌دار
            // wc_cart_fragments_params.wc_ajax_url = '" . esc_js( add_query_arg( 'lang', $lang, WC()->ajax_url() ) ) . "';
        ";
        
        wp_add_inline_script( 'wc-cart-fragments', $script, 'before' );
    }

    // --- توابع ترجمه و قالب ---
    public function universal_translator( $translated, $text, $domain ) {
        if ( ! $this->is_english() ) return $translated;
        $clean = trim( $translated );
        if ( isset( $this->translations_map[ $clean ] ) ) return $this->translations_map[ $clean ];
        if ( isset( $this->translations_map[ trim($text) ] ) ) return $this->translations_map[ trim($text) ];
        return $translated;
    }
    public function universal_translator_context( $translated, $text, $context, $domain ) {
        return $this->universal_translator( $translated, $text, $domain );
    }
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