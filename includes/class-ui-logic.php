<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $translations_map = [];
    private $en_header_id;
    private $en_footer_id;

    public function __construct() {
        // 1. آماده‌سازی لیست ترجمه (Map) برای سرعت بالا
        $saved_strings = get_option( 'fikup_translations_list', [] );
        if( is_array($saved_strings) ) {
            foreach($saved_strings as $item) {
                if(!empty($item['key'])) {
                    $key = trim( $item['key'] ); // حذف فاصله برای مقایسه دقیق
                    $this->translations_map[ $key ] = $item['val'];
                }
            }
        }

        $this->en_header_id = get_option( 'fikup_woodmart_header_id' );
        $this->en_footer_id = get_option( 'fikup_woodmart_footer_id' );

        // --- منطق تشخیص زبان و اعمال تغییرات ---
        
        // 1. ترجمه متن‌ها (هسته اصلی - مشابه ووکامرس فارسی)
        add_filter( 'gettext', [ $this, 'universal_translator' ], PHP_INT_MAX, 3 );
        add_filter( 'gettext_with_context', [ $this, 'universal_translator_context' ], PHP_INT_MAX, 4 );
        add_filter( 'ngettext', [ $this, 'universal_translator_plural' ], PHP_INT_MAX, 5 );

        // 2. ترجمه آپشن‌های قالب (مثل متن سبد خالی)
        add_filter( 'woodmart_option', [ $this, 'translate_theme_options' ], 999, 2 );

        // 3. هوک‌های تغییر ساختار (هدر/فوتر)
        add_filter( 'woodmart_get_current_header_id', [ $this, 'swap_header_builder_id' ], 999 );
        add_filter( 'get_post_metadata', [ $this, 'force_layout_via_meta' ], 10, 4 );

        // 4. غیرفعال کردن ترجمه‌های فارسی مزاحم
        add_filter( 'load_textdomain_mofile', [ $this, 'unload_persian_translations' ], 999, 2 );
        add_filter( 'option_persian_woocommerce_replacements', [ $this, 'disable_persian_replacements' ] );

        // 5. استایل‌ها و اسکریپت‌ها
        add_action( 'wp_head', [ $this, 'print_custom_css_and_js' ] );

        // 6. [جدید] فیکس کردن ایجکس سبد خرید وودمارت
        add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'fix_ajax_cart_fragments' ], 999 );
    }

    /**
     * آیا الان باید انگلیسی باشیم؟ (منطق تشخیص بسیار دقیق)
     */
    private function is_english_context() {
        // الف) پنل ادمین همیشه فارسی بماند (مگر اینکه ایجکس باشد)
        if ( is_admin() && ! wp_doing_ajax() ) return false;

        // ب) اگر در URL یا پارامترها نشانه انگلیسی باشد
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) !== false ) return true;
        if ( isset( $_GET['lang'] ) && $_GET['lang'] === 'en' ) return true;

        // ج) بررسی Referer برای درخواست‌های AJAX (بسیار مهم برای سبد خرید)
        if ( wp_doing_ajax() || isset( $_GET['wc-ajax'] ) ) {
            if ( isset( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'], '/en/' ) !== false ) {
                return true;
            }
            // اگر هدر سفارشی ما ارسال شده باشد (توسط JS)
            if ( isset( $_SERVER['HTTP_X_FIKUP_LANG'] ) && $_SERVER['HTTP_X_FIKUP_LANG'] === 'en' ) {
                return true;
            }
        }

        // د) بررسی کوکی به عنوان آخرین راه حل
        if ( isset( $_COOKIE['fikup_lang'] ) && $_COOKIE['fikup_lang'] === 'en' ) {
            // یک چک نهایی: اگر کاربر الان در صفحه فارسی است، کوکی انگلیسی را نادیده بگیر
            if ( ! wp_doing_ajax() && isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/en/' ) === false ) {
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * مترجم سراسری (جایگزین کننده متن)
     */
    public function universal_translator( $translated, $text, $domain ) {
        if ( ! $this->is_english_context() ) return $translated;

        // 1. اگر خود متن ترجمه شده (فارسی) در لیست ما باشد
        $clean_translated = trim( $translated );
        if ( isset( $this->translations_map[ $clean_translated ] ) ) {
            return $this->translations_map[ $clean_translated ];
        }

        // 2. اگر متن اصلی (انگلیسی) در لیست ما باشد
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

    /**
     * ترجمه آپشن‌های قالب (مثل متن سبد خالی)
     */
    public function translate_theme_options( $value, $slug ) {
        if ( ! $this->is_english_context() ) return $value;

        // تغییر اجباری فوتر
        if ( $slug === 'footer_content_type' ) return 'html_block';
        if ( $slug === 'footer_html_block' && ! empty( $this->en_footer_id ) ) return $this->en_footer_id;

        // اگر متن این آپشن در لیست ترجمه‌های ما باشد، جایگزین کن
        if ( is_string( $value ) ) {
            $clean_val = trim( $value );
            if ( isset( $this->translations_map[ $clean_val ] ) ) {
                return $this->translations_map[ $clean_val ];
            }
        }

        return $value;
    }

    /**
     * فیکس کردن ایجکس سبد خرید (بسیار مهم)
     * این تابع وقتی فرگمنت‌های سبد خرید ساخته می‌شوند اجرا می‌شود
     */
    public function fix_ajax_cart_fragments( $fragments ) {
        if ( ! $this->is_english_context() ) return $fragments;

        // اینجا می‌توانیم اگر لازم بود چیزی را به زور تغییر دهیم
        // اما چون هوک‌های gettext و theme_options در بالا فعال هستند،
        // وقتی ووکامرس دارد HTML سبد را می‌سازد، خودکار ترجمه می‌شوند.
        return $fragments;
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
        (function() {
            // اسکریپت مدیریت کوکی برای جلوگیری از باگ کش
            function setFikupCookie(name, value) {
                document.cookie = name + "=" + (value || "")  + "; path=/";
            }
            
            var isEn = window.location.pathname.indexOf('/en/') !== -1;
            
            if ( isEn ) {
                setFikupCookie('fikup_lang', 'en');
                // اضافه کردن هدر به تمام درخواست‌های ایجکس بعدی
                if ( typeof jQuery !== 'undefined' ) {
                    jQuery( document ).ajaxSend(function(event, xhr, settings) {
                        xhr.setRequestHeader('X-Fikup-Lang', 'en');
                    });
                }
            } else {
                // اگر در صفحه فارسی هستیم، حتما کوکی را پاک کن یا فارسی کن
                setFikupCookie('fikup_lang', 'fa');
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