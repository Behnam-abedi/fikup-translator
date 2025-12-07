<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // آماده‌سازی ترجمه‌ها
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

        // --- 1. هوک‌های ترجمه و قالب ---
        add_filter( 'gettext', [ $this, 'universal_translator' ], 9999, 3 );
        add_filter( 'gettext_with_context', [ $this, 'universal_translator_context' ], 9999, 4 );
        add_filter( 'ngettext', [ $this, 'universal_translator_plural' ], 9999, 5 );
        add_filter( 'woodmart_option', [ $this, 'translate_theme_options' ], 999, 2 );
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );
        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
        add_filter( 'option_persian_woocommerce_replacements', [ $this, 'disable_persian_replacements' ] );

        // --- 2. لاجیک پیشرفته سبد خرید (Backend) ---
        // اضافه کردن زبان به هش سبد خرید برای جلوگیری از کش شدن سمت سرور
        add_filter( 'woocommerce_cart_hash', [ $this, 'split_cart_hash_by_lang' ] );

        // --- 3. لاجیک پیشرفته جاوااسکریپت (Frontend) ---
        // اجرای اسکریپت در فوتر برای اطمینان از لود شدن متغیرهای ووکامرس
        add_action( 'wp_footer', [ $this, 'inject_state_driven_logic' ], 1000 );
        // استایل‌ها در هدر
        add_action( 'wp_head', [ $this, 'print_custom_css' ] );
    }

    /**
     * جداسازی کش سرور بر اساس زبان
     */
    public function split_cart_hash_by_lang( $hash ) {
        $lang = $this->is_english_context() ? 'en' : 'fa';
        return $hash . '-' . $lang;
    }

    /**
     * سیستم تشخیص زبان (Backend)
     */
    private function is_english_context() {
        // 1. اگر کلاس زبان قبلاً تشخیص داده
        if ( class_exists('Fikup_Poly_Language') && Fikup_Poly_Language::is_english() ) {
            return true;
        }

        // 2. اگر در URL پارامتر lang وجود دارد (که توسط JS ما ارسال شده)
        if ( isset( $_GET['lang'] ) && $_GET['lang'] === 'en' ) {
            return true;
        }

        // 3. چک کردن URL معمولی
        if ( ! wp_doing_ajax() ) {
            if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) {
                return true;
            }
        }

        // 4. چک کردن هدرهای ایجکس
        if ( wp_doing_ajax() ) {
            if ( isset( $_SERVER['HTTP_X_FIKUP_LANG'] ) && $_SERVER['HTTP_X_FIKUP_LANG'] === 'en' ) {
                return true;
            }
        }

        return false;
    }

    // --- توابع ترجمه (بدون تغییر) ---
    public function universal_translator( $translated, $text, $domain ) {
        if ( ! $this->is_english_context() ) return $translated;
        $clean_translated = trim( $translated );
        if ( isset( $this->translations_map[ $clean_translated ] ) ) return $this->translations_map[ $clean_translated ];
        $clean_text = trim( $text );
        if ( isset( $this->translations_map[ $clean_text ] ) ) return $this->translations_map[ $clean_text ];
        return $translated;
    }
    public function universal_translator_context( $translated, $text, $context, $domain ) { return $this->universal_translator( $translated, $text, $domain ); }
    public function universal_translator_plural( $translation, $single, $plural, $number, $domain ) { return $this->universal_translator( $translation, $single, $domain ); }
    public function translate_theme_options( $value, $slug ) {
        if ( ! $this->is_english_context() ) return $value;
        if ( $slug === 'footer_content_type' ) return 'html_block';
        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        if ( is_string( $value ) && isset( $this->translations_map[ trim($value) ] ) ) return $this->translations_map[ trim($value) ];
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
    public function disable_persian_replacements( $value ) { return $this->is_english_context() ? [] : $value; }
    public function swap_header_builder_id( $id ) { return ( $this->is_english_context() && ! empty( $this->en_header_id ) ) ? $this->en_header_id : $id; }
    public function force_layout_via_meta( $value, $object_id, $meta_key, $single ) {
        if ( is_admin() || ! $this->is_english_context() ) return $value;
        if ( $meta_key === '_woodmart_whb_header' && ! empty( $this->en_header_id ) ) return $this->en_header_id;
        if ( $meta_key === '_woodmart_footer_content_type' ) return 'html_block';
        if ( $meta_key === '_woodmart_footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;
        return $value;
    }

    public function print_custom_css() {
        if ( $this->is_english_context() ) {
            echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">';
            echo '<style>body.fikup-en-mode { font-family: "Roboto", sans-serif !important; } .fikup-en-mode .woodmart-font { font-family: "woodmart-font" !important; }</style>';
            $css = get_option( 'fikup_custom_css_en' );
            if ( ! empty( $css ) ) echo '<style>' . wp_strip_all_tags( $css ) . '</style>';
        }
    }

    /**
     * شاهکار منطقی: State-Driven Logic
     * تغییر تمام URLهای ایجکس در مرورگر + مدیریت کش هوشمند
     */
    public function inject_state_driven_logic() {
        ?>
        <script>
        (function() {
            // 1. تشخیص قطعی زبان
            var currentPath = window.location.pathname;
            var isEn = currentPath.indexOf('/en/') !== -1;
            var langParam = isEn ? 'en' : 'fa';
            
            console.log('Fikup Engine: Active Language -> ' + langParam);

            // 2. مدیریت کش ووکامرس (Signature System)
            // به جای اینکه فقط کش را پاک کنیم، یک "امضا" بررسی میکنیم
            try {
                var storageKey = 'fikup_cart_signature';
                var lastSignature = localStorage.getItem(storageKey);
                
                // اگر امضای قبلی با زبان فعلی یکی نیست
                if ( lastSignature !== langParam ) {
                    console.log('Fikup Engine: Cache Signature Mismatch! Nuking fragments.');
                    
                    // پاکسازی کامل
                    sessionStorage.removeItem('wc_fragments_hash');
                    sessionStorage.removeItem('wc_fragments');
                    sessionStorage.removeItem('wc_cart_hash_data');
                    sessionStorage.removeItem('wc_cart_created');
                    
                    // به وودمارت هم دستور ریست میدهیم (اگر چیزی ذخیره کرده باشد)
                    sessionStorage.removeItem('woodmart_cart_count');
                    
                    // آپدیت امضا
                    localStorage.setItem(storageKey, langParam);
                    
                    // درخواست رفرش فوری
                    if ( typeof jQuery !== 'undefined' ) {
                        jQuery(document.body).trigger('wc_fragment_refresh');
                        jQuery(document.body).trigger('woodmart_cart_refresh'); // مخصوص قالب
                    }
                }
            } catch(e) { console.error(e); }

            // 3. رهگیری و تغییر متغیرهای جهانی (Global Variable Interception)
            // این بخش URLهای ایجکس که ووکامرس و قالب استفاده میکنند را تغییر میدهد
            function modifyAjaxUrl( originalUrl ) {
                if ( ! originalUrl ) return originalUrl;
                // حذف پارامتر تکراری اگر هست
                var newUrl = originalUrl.replace(/&lang=(en|fa)/g, '');
                newUrl = newUrl.replace(/\?lang=(en|fa)/g, '?');
                
                // اضافه کردن پارامتر درست
                var separator = newUrl.indexOf('?') !== -1 ? '&' : '?';
                return newUrl + separator + 'lang=' + langParam;
            }

            var targets = [
                'woocommerce_params', 
                'wc_cart_fragments_params', 
                'wc_add_to_cart_params', 
                'woodmart_settings' // تنظیمات اختصاصی قالب
            ];

            targets.forEach(function( variable ) {
                if ( window[variable] ) {
                    if ( window[variable].ajax_url ) {
                        window[variable].ajax_url = modifyAjaxUrl( window[variable].ajax_url );
                    }
                    if ( window[variable].wc_ajax_url ) {
                        window[variable].wc_ajax_url = modifyAjaxUrl( window[variable].wc_ajax_url );
                    }
                }
            });

            // 4. هوک کردن به jQuery AJAX برای اطمینان ۱۰۰٪
            if ( typeof jQuery !== 'undefined' ) {
                jQuery.ajaxPrefilter(function( options, originalOptions, jqXHR ) {
                    // تزریق هدر
                    if ( isEn ) jqXHR.setRequestHeader('X-Fikup-Lang', 'en');
                    
                    // تزریق پارامتر به URL درخواست اگر نداشته باشد
                    if ( options.url && options.url.indexOf('lang=') === -1 ) {
                        var sep = options.url.indexOf('?') !== -1 ? '&' : '?';
                        options.url = options.url + sep + 'lang=' + langParam;
                    }
                });
            }

        })();
        </script>
        <?php
    }
}