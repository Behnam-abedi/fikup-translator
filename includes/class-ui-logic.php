<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // --- 1. پروتکل پاکسازی (The Cleaner Logic) ---
        // این بخش حیاتی است. تمام نویزهای قالب را حذف می‌کند تا ایجکس سالم بماند.
        if ( wp_doing_ajax() ) {
            // خاموش کردن گزارش خطا
            error_reporting(0);
            @ini_set('display_errors', 0);
            
            // شروع ضبط خروجی (برای اینکه خطاهای قالب چاپ نشوند)
            ob_start();
            
            // پاکسازی بافر قبل از شات‌داون (تمیز کردن نهایی)
            add_action( 'shutdown', function() {
                // اگر طول بافر خیلی زیاد بود (یعنی خطا چاپ شده)، پاکش کن
                // اما اگر خروجی استاندارد ووکامرس بود، کاری نداشته باش
                if ( ob_get_length() > 0 ) {
                    $output = ob_get_contents();
                    // یک چک ساده: اگر خروجی جیسون نبود ولی پر بود، احتمالاً خطاست
                    if ( strpos( trim($output), '{' ) !== 0 && strpos( trim($output), '[' ) !== 0 ) {
                        ob_clean(); // دور ریختن خطاها
                    }
                }
            }, 0 );
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

        // --- مدیریت ایجکس و کش ---
        add_filter( 'woocommerce_cart_hash', [ $this, 'split_cart_hash_by_lang' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'force_refresh_script' ], 20 );
    }

    /**
     * تشخیص زبان (فقط بر اساس Referer برای ایجکس)
     */
    private function is_english() {
        if ( ! wp_doing_ajax() ) {
            return isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false;
        }
        $referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
        return strpos( $referer, '/en/' ) !== false;
    }

    /**
     * جداسازی هش سبد خرید
     */
    public function split_cart_hash_by_lang( $hash ) {
        $lang = $this->is_english() ? 'en' : 'fa';
        return $hash . '-' . $lang;
    }

    /**
     * اسکریپت رفرش اجباری
     * این اسکریپت در فرانت اجرا می‌شود و مطمئن می‌شود که اگر زبان عوض شده، کش پاک شود.
     */
    public function force_refresh_script() {
        ?>
        <script>
        (function() {
            var isEn = window.location.pathname.indexOf('/en/') !== -1;
            var currentLang = isEn ? 'en' : 'fa';
            var key = 'fikup_cleaner_state';
            
            try {
                var last = localStorage.getItem(key);
                if ( last !== currentLang ) {
                    // پاکسازی کامل کش‌ها
                    sessionStorage.removeItem('wc_fragments_hash');
                    sessionStorage.removeItem('wc_fragments');
                    
                    // ذخیره وضعیت جدید
                    localStorage.setItem(key, currentLang);
                    
                    // رفرش ووکامرس (با تاخیر کم برای اطمینان)
                    setTimeout(function(){
                        if(typeof jQuery != 'undefined') {
                            jQuery(document.body).trigger('wc_fragment_refresh');
                        }
                    }, 200);
                }
            } catch(e){}

            // استایل‌های انگلیسی
            if(isEn) {
                var s = document.createElement('style');
                s.innerHTML = 'body.fikup-en-mode { font-family: "Roboto", sans-serif !important; }';
                document.head.appendChild(s);
                document.body.classList.add('fikup-en-mode');
            }
        })();
        </script>
        <?php
    }

    // --- توابع ترجمه ---
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