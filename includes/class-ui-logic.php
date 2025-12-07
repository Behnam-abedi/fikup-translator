<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // ۱. جلوگیری از خطای ایجکس
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

        // بارگذاری ترجمه‌های متنی
        $saved_strings = get_option( 'fikup_translations_list', [] );
        if( is_array($saved_strings) ) {
            foreach($saved_strings as $item) {
                if(!empty($item['key'])) $this->translations_map[ trim( $item['key'] ) ] = $item['val'];
            }
        }
        $this->en_header_id = get_option( 'fikup_woodmart_header_id' );
        $this->en_footer_id = get_option( 'fikup_woodmart_footer_id' );

        // --- سیستم بافر خروجی (مهم‌ترین بخش) ---
        add_action( 'template_redirect', [ $this, 'start_output_buffer' ], 1 );

        // هوک‌های قالب و ووکامرس
        add_filter( 'woodmart_option', [ $this, 'translate_theme_options' ], 999, 2 );
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );
        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
        add_filter( 'woocommerce_cart_hash', [ $this, 'split_cart_hash' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'force_english_ajax_url' ], 20 );
    }

    public function start_output_buffer() {
        // فقط در حالت انگلیسی و درخواست‌های غیر ایجکس
        if ( $this->is_english() && ! wp_doing_ajax() && ! is_admin() ) {
            ob_start( [ $this, 'process_html_buffer' ] );
        }
    }

    /**
     * پردازش نهایی HTML: اینجا لینک‌ها و متن‌ها را اصلاح می‌کنیم
     */
    public function process_html_buffer( $buffer ) {
        if ( empty( $buffer ) ) return $buffer;

        // ۱. اصلاح لینک‌های سیستمی خراب (سبد خرید، علاقه‌مندی و...)
        $buffer = $this->fix_broken_system_links( $buffer );

        // ۲. اعمال ترجمه‌های متنی کاربر
        if ( ! empty( $this->translations_map ) ) {
            $keys = array_keys( $this->translations_map );
            $values = array_values( $this->translations_map );
            $buffer = str_replace( $keys, $values, $buffer );
        }

        return $buffer;
    }

    /**
     * تابع جدید: تعمیر لینک‌های سیستمی که قاطی شده‌اند
     */
    private function fix_broken_system_links( $content ) {
        // لیست صفحات مهم (لینک فارسی => لینک انگلیسی)
        // شما می‌توانید اسلاگ‌های انگلیسی را متناسب با سایت خود تغییر دهید
        $maps = [
            // سبد خرید
            'سبد-خرید' => 'cart',
            '%d8%b3%d8%a8%d8%af-%d8%ae%d8%b1%db%8c%d8%af' => 'cart', // فرمت انکود شده
            
            // علاقه مندی ها (Woodmart Wishlist)
            'علاقه-مندی-ها' => 'wishlist',
            '%d8%b9%d9%84%d8%a7%d9%82%d9%87-%d9%85%d9%86%d8%af%db%8c-%d9%87%d8%a7' => 'wishlist',

            // تسویه حساب
            'تسویه-حساب' => 'checkout',
            '%d8%aa%d8%b3%d9%88%db%8c%d9%87-%d8%ad%d8%b3%d8%a7%d8%a8' => 'checkout',

            // فروشگاه
            'فروشگاه' => 'shop',
            '%d9%81%d8%b1%d9%88%d8%b4%da%af%d8%a7%d9%87' => 'shop',

            // حساب کاربری
            'حساب-کاربری-من' => 'my-account',
            '%d8%ad%d8%b3%d8%a7%d8%a8-%da%a9%d8%a7%d8%b1%d8%a8%d8%b1%db%8c-%d9%85%d9%86' => 'my-account',
        ];

        $home_url = rtrim( get_option( 'home' ), '/' ); // مثلا https://fikup.ir

        foreach ( $maps as $fa_slug => $en_slug ) {
            // حالت ۱: لینک غلط ترکیبی (/en/سبد-خرید) -> تبدیل به (/en/cart)
            $wrong_mixed = $home_url . '/en/' . $fa_slug . '/';
            $correct_en  = $home_url . '/en/' . $en_slug . '/';
            $content = str_replace( $wrong_mixed, $correct_en, $content );
            
            // حالت ۲: لینک فارسی خالص (/سبد-خرید) -> تبدیل به (/en/cart)
            // این برای وقتی است که قالب وودمارت لینک فارسی را مستقیماً چاپ کرده
            $pure_fa = $home_url . '/' . $fa_slug . '/';
            $content = str_replace( $pure_fa, $correct_en, $content );

            // حالت ۳: بدون اسلش انتهایی (جهت احتیاط)
            $wrong_mixed_noslash = $home_url . '/en/' . $fa_slug;
            $pure_fa_noslash = $home_url . '/' . $fa_slug;
            $correct_en_noslash = $home_url . '/en/' . $en_slug;
            
            $content = str_replace( $wrong_mixed_noslash, $correct_en_noslash, $content );
            $content = str_replace( $pure_fa_noslash, $correct_en_noslash, $content );
        }

        return $content;
    }

    private function is_english() {
        if ( isset( $_GET['lang'] ) && $_GET['lang'] === 'en' ) return true;
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) return true;
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
            if ( typeof wc_cart_fragments_params === 'undefined' ) { var wc_cart_fragments_params = {}; }
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

    // --- توابع حذف فایل زبان ---
    public function unload_persian_translations( $mofile, $domain ) {
        if ( ! $this->is_english() ) return $mofile;
        $blocked = [ 'woodmart', 'woocommerce', 'woodmart-core', 'woocommerce-persian', 'persian-woocommerce', 'wooc-fa' ];
        if ( in_array( $domain, $blocked ) ) return ''; 
        if ( strpos( $mofile, 'fa_IR' ) !== false ) return '';
        return $mofile;
    }

    public function translate_theme_options( $value, $slug ) {
        if ( ! $this->is_english() ) return $value;
        if ( $slug === 'footer_content_type' ) return 'html_block';
        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        
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