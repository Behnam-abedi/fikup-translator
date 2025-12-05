<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Language {
    
    public static $current_lang = 'fa';

    public function __construct() {
        // 1. ثبت متغیر lang در کوئری‌های وردپرس
        add_filter( 'query_vars', [ $this, 'register_query_vars' ] );

        // 2. ساخت رول‌های جدید برای /en/
        add_filter( 'rewrite_rules_array', [ $this, 'add_en_rewrite_rules' ] );

        // 3. تشخیص زبان در لحظه لود شدن
        add_action( 'wp', [ $this, 'detect_language' ] );

        // 4. اصلاح لینک‌های خروجی (لینک‌سازی)
        add_filter( 'post_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'page_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'post_type_link', [ $this, 'filter_permalink' ], 10, 2 ); // برای ووکامرس
        add_filter( 'term_link', [ $this, 'filter_term_link' ], 10, 2 ); // برای دسته‌بندی‌ها
        
        // 5. تغییر آدرس Home در حالت انگلیسی
        add_filter( 'home_url', [ $this, 'filter_home_url' ], 10, 2 );
    }

    /**
     * اجازه می‌دهیم وردپرس متغیر ?lang=... را بفهمد
     */
    public function register_query_vars( $vars ) {
        $vars[] = 'lang';
        return $vars;
    }

    /**
     * جادوی اصلی: کپی کردن تمام رول‌های موجود و اضافه کردن پیشوند en/ به آن‌ها
     */
    public function add_en_rewrite_rules( $rules ) {
        $new_rules = array();

        // حلقه روی تمام رول‌های استاندارد وردپرس
        foreach ( $rules as $regex => $query ) {
            // اضافه کردن پیشوند en/ به ابتدای regex
            // مثال: 'product/([^/]+)/?$'  تبدیل می‌شود به  'en/product/([^/]+)/?$'
            $new_regex = 'en/' . $regex;
            
            // اضافه کردن پارامتر lang=en به انتهای کوئری
            $new_query = $query . '&lang=en';

            $new_rules[ $new_regex ] = $new_query;
        }

        // رول‌های جدید باید اولویت بالاتری داشته باشند (بالای لیست قرار بگیرند)
        return $new_rules + $rules;
    }

    /**
     * تشخیص زبان بر اساس کوئری ست شده توسط Rewrite Rule
     */
    public function detect_language() {
        if ( get_query_var( 'lang' ) === 'en' ) {
            self::$current_lang = 'en';
            
            // تغییر لوکال وردپرس به انگلیسی (برای راست‌چین/چپ‌چین و فایل‌های ترجمه)
            add_filter( 'locale', function() { return 'en_US'; } );
            
            // تغییر جهت بادی به LTR
            add_filter( 'body_class', function( $classes ) {
                $classes[] = 'fikup-en-mode';
                return $classes;
            });
        }
    }

    /**
     * اصلاح لینک پست‌ها و محصولات (Outgoing Links)
     * اگر پستی انگلیسی بود، آدرسش باید /en/ داشته باشد
     */
    public function filter_permalink( $url, $post ) {
        // گرفتن آبجکت پست (گاهی فقط ID میاد)
        $post = get_post( $post );
        if ( ! $post ) return $url;

        // چک کردن متای زبان که در زمان داپلیکیت ست کردیم
        $lang = get_post_meta( $post->ID, '_fikup_lang', true );

        if ( $lang === 'en' ) {
            // اضافه کردن /en/ بعد از آدرس اصلی سایت
            return $this->inject_en_prefix( $url );
        }

        return $url;
    }

    /**
     * اصلاح لینک دسته‌بندی‌ها (اگر نیاز دارید دسته‌بندی انگلیسی جدا داشته باشید)
     */
    public function filter_term_link( $url, $term ) {
        // اینجا فرض را بر این می‌گیریم که اگر در حالت انگلیسی هستیم، لینک دسته‌ها هم انگلیسی شود
        if ( self::$current_lang === 'en' ) {
            return $this->inject_en_prefix( $url );
        }
        return $url;
    }

    /**
     * اصلاح لینک خانه (Home URL)
     * که وقتی لوگوی سایت زده شد برود به fikup.ir/en/
     */
    public function filter_home_url( $url, $path ) {
        if ( self::$current_lang === 'en' ) {
            // جلوگیری از لوپ و تکرار /en/en/
            if ( strpos( $url, '/en/' ) === false ) {
                return rtrim( $url, '/' ) . '/en/';
            }
        }
        return $url;
    }

    /**
     * تابع کمکی برای تزریق /en/ به URL
     */
    private function inject_en_prefix( $url ) {
        $home = home_url();
        // حذف پروتکل برای جلوگیری از اشتباه (http/https)
        $clean_home = str_replace( ['http://', 'https://'], '', $home );
        $clean_url  = str_replace( ['http://', 'https://'], '', $url );

        // اگر URL با Home شروع می‌شود، /en/ را بعد از آن بگذار
        if ( strpos( $clean_url, $clean_home ) === 0 ) {
            $path = substr( $clean_url, strlen( $clean_home ) );
            return home_url( '/en' . $path );
        }
        
        return $url;
    }

    public static function is_english() {
        return self::$current_lang === 'en';
    }
}