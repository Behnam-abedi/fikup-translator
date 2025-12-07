<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Language {
    
    public static $current_lang = 'fa';

    public function __construct() {
        add_filter( 'locale', [ $this, 'set_locale' ], 1 );
        add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
        add_filter( 'rewrite_rules_array', [ $this, 'add_en_rewrite_rules' ] );
        
        // فیلترهای لینک‌دهی
        add_filter( 'post_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'page_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'post_type_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'term_link', [ $this, 'filter_term_link' ], 10, 2 );
        
        // اصلاح لینک‌های ووکامرس و وودمارت
        add_filter( 'woocommerce_get_cart_url', [ $this, 'force_en_url' ] );
        add_filter( 'woocommerce_get_checkout_url', [ $this, 'force_en_url' ] );
        add_filter( 'woocommerce_get_shop_url', [ $this, 'force_en_url' ] );
        add_filter( 'woodmart_get_wishlist_url', [ $this, 'force_en_url' ] );
        add_filter( 'woodmart_get_compare_url', [ $this, 'force_en_url' ] );
        
        add_filter( 'request', [ $this, 'intercept_request_for_translation' ] );
        add_filter( 'redirect_canonical', [ $this, 'prevent_canonical_redirect' ], 10, 2 );
        add_filter( 'body_class', [ $this, 'add_body_classes' ] );
    }

    public function force_en_url( $url ) {
        if ( self::is_english() ) return self::inject_en_prefix( $url );
        return $url;
    }

    /**
     * هسته اصلی تولید لینک ترجمه (نسخه اصلاح شده برای تشخیص دقیق انگلیسی به فارسی)
     */
    public static function get_translated_url() {
        $is_currently_en = self::is_english();
        $target_lang = $is_currently_en ? 'fa' : 'en';
        
        // --- ۱. بارگذاری و تمیزکاری نقشه لینک‌ها ---
        $slug_map = [];
        $raw_map = get_option( 'fikup_slug_mapping', '' );
        if ( ! empty( $raw_map ) ) {
            $lines = explode( "\n", $raw_map );
            foreach ( $lines as $line ) {
                $parts = explode( ':', $line );
                if ( count( $parts ) >= 2 ) {
                    // تمیزکاری فاصله و حروف بزرگ/کوچک برای تطابق دقیق
                    $en_key = strtolower( trim( $parts[0] ) ); 
                    $fa_val = trim( $parts[1] );
                    if ( ! empty( $en_key ) && ! empty( $fa_val ) ) {
                        $slug_map[ $en_key ] = $fa_val;
                    }
                }
            }
        }
        
        // اطمینان از وجود مقادیر پیش‌فرض اگر کاربر وارد نکرده باشد
        if ( ! isset( $slug_map['cart'] ) ) $slug_map['cart'] = 'cart';
        if ( ! isset( $slug_map['checkout'] ) ) $slug_map['checkout'] = 'checkout';
        if ( ! isset( $slug_map['wishlist'] ) ) $slug_map['wishlist'] = 'wishlist';

        // --- ۲. استخراج نامک (Slug) از آدرس فعلی ---
        // این روش مستقیم‌ترین راه است و خطاهای parse_url را ندارد
        $request_uri = $_SERVER['REQUEST_URI'];
        
        // حذف پارامترهای کوئری (?lang=...)
        if ( strpos( $request_uri, '?' ) !== false ) {
            $request_uri = substr( $request_uri, 0, strpos( $request_uri, '?' ) );
        }
        
        // حذف /en/ از آدرس برای رسیدن به نامک خالص
        // مثال: /en/wishlist/ => /wishlist/
        $clean_path = str_replace( '/en/', '/', $request_uri );
        
        // تکه تکه کردن آدرس برای گرفتن قسمت آخر
        $parts = explode( '/', trim( $clean_path, '/' ) );
        $current_url_slug = end( $parts );
        
        // دیکد کردن (برای فارسی) و کوچک کردن حروف (برای انگلیسی)
        $current_url_slug = strtolower( urldecode( $current_url_slug ) );

        // --- ۳. بررسی اولویت‌دار نقشه (Map Check) ---
        if ( ! empty( $current_url_slug ) ) {
            $home = rtrim( get_option( 'home' ), '/' );
            
            if ( $is_currently_en ) {
                // سناریوی مشکل‌دار شما: تبدیل انگلیسی به فارسی
                // الان $current_url_slug مثلاً 'wishlist' است.
                if ( isset( $slug_map[ $current_url_slug ] ) ) {
                    // مقدار map را بردار (مثلاً 'علاقه-مندی-ها')
                    $target_slug = $slug_map[ $current_url_slug ];
                    // لینک فارسی بساز
                    return $home . '/' . $target_slug . '/';
                }
            } else {
                // سناریوی فارسی به انگلیسی (که درست کار می‌کرد)
                $english_slug = array_search( $current_url_slug, $slug_map );
                if ( $english_slug ) {
                    return $home . '/en/' . $english_slug . '/';
                }
            }
        }

        // --- ۴. بررسی دیتابیس (برای صفحات عادی) ---
        if ( is_singular() ) {
            $current_id = get_the_ID();
            $group_id = get_post_meta( $current_id, '_fikup_translation_group', true );
            if ( $group_id ) {
                global $wpdb;
                $target_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT m.post_id FROM $wpdb->postmeta m 
                     INNER JOIN $wpdb->posts p ON m.post_id = p.ID 
                     WHERE m.meta_key = '_fikup_translation_group' 
                     AND m.meta_value = %s 
                     AND m.post_id != %d 
                     AND p.post_status = 'publish' 
                     LIMIT 1",
                    $group_id, $current_id
                ));
                if ( $target_id ) {
                    return self::finalize_url( get_permalink( $target_id ), $target_lang );
                }
            }
        }

        // --- ۵. فال‌بک نهایی (اگر هیچ‌کدام پیدا نشد) ---
        global $wp;
        $current_full_url = home_url( add_query_arg( array(), $wp->request ) );
        
        // تلاش مجدد برای لینک‌سازی دستی اگر اسلاگ پیدا شده بود ولی در مپ نبود
        if ( ! empty( $current_url_slug ) ) {
            $home = rtrim( get_option( 'home' ), '/' );
            if ( $target_lang === 'en' ) {
                return $home . '/en/' . $current_url_slug . '/';
            } else {
                return $home . '/' . $current_url_slug . '/';
            }
        }

        return self::finalize_url( $current_full_url, $target_lang );
    }

    private static function finalize_url( $url, $target_lang ) {
        if ( $target_lang === 'fa' ) {
            return self::strip_en_prefix( $url );
        } else {
            return self::inject_en_prefix( $url );
        }
    }

    private static function inject_en_prefix( $url ) {
        if ( strpos( $url, '/en/' ) !== false ) return $url;
        $home = rtrim( get_option( 'home' ), '/' );
        return str_replace( $home, $home . '/en', $url );
    }

    private static function strip_en_prefix( $url ) {
        return str_replace( '/en/', '/', $url );
    }

    // --- سایر توابع ---
    public function set_locale( $locale ) {
        $is_en = false;
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
            if ( strpos( $path, '/en/' ) === 0 || $path === '/en' ) $is_en = true;
        }
        if ( isset( $_GET['lang'] ) && $_GET['lang'] === 'en' ) $is_en = true;
        if ( wp_doing_ajax() && isset( $_SERVER['HTTP_X_FIKUP_LANG'] ) && $_SERVER['HTTP_X_FIKUP_LANG'] === 'en' ) $is_en = true;

        if ( $is_en ) {
            self::$current_lang = 'en';
            return 'en_US';
        }
        return $locale;
    }

    public function add_body_classes( $classes ) {
        if ( self::$current_lang === 'en' ) {
            $classes[] = 'fikup-en-mode';
            $classes[] = 'ltr';
            $key = array_search( 'rtl', $classes );
            if ( false !== $key ) unset( $classes[ $key ] );
        }
        return $classes;
    }

    public function register_query_vars( $vars ) { $vars[] = 'lang'; return $vars; }

    public function add_en_rewrite_rules( $rules ) {
        $new_rules = array();
        foreach ( $rules as $regex => $query ) {
            $new_rules[ 'en/' . $regex ] = $query . '&lang=en';
        }
        return $new_rules + $rules;
    }

    public function intercept_request_for_translation( $vars ) {
        if ( isset( $vars['lang'] ) && $vars['lang'] === 'en' ) {
            self::$current_lang = 'en';
            $target_slug = '';
            $post_type = 'post';

            if ( isset( $vars['pagename'] ) ) {
                $target_slug = $vars['pagename'];
                $post_type = 'page';
            } elseif ( isset( $vars['name'] ) ) {
                $target_slug = $vars['name'];
                if ( isset( $vars['post_type'] ) ) $post_type = $vars['post_type'];
            }

            if ( $target_slug ) {
                global $wpdb;
                $direct_en_post = $wpdb->get_var( $wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts INNER JOIN $wpdb->postmeta ON ($wpdb->posts.ID = $wpdb->postmeta.post_id) WHERE post_name = %s AND post_type = %s AND post_status = 'publish' AND meta_key = '_fikup_lang' AND meta_value = 'en' LIMIT 1", $target_slug, $post_type
                ));

                if ( $direct_en_post ) {
                    if( $post_type == 'page' ) $vars['page_id'] = $direct_en_post; else $vars['p'] = $direct_en_post;
                } else {
                    $original_post = get_page_by_path( $target_slug, OBJECT, $post_type );
                    if ( $original_post ) {
                        $group_id = get_post_meta( $original_post->ID, '_fikup_translation_group', true );
                        if ( $group_id ) {
                            $en_id = $wpdb->get_var( $wpdb->prepare( "SELECT m.post_id FROM $wpdb->postmeta m INNER JOIN $wpdb->posts p ON m.post_id = p.ID WHERE m.meta_key = '_fikup_translation_group' AND m.meta_value = %s AND m.post_id != %d AND p.post_status = 'publish' LIMIT 1", $group_id, $original_post->ID ));
                            if ( $en_id ) {
                                if( $post_type == 'page' ) $vars['page_id'] = $en_id; else $vars['p'] = $en_id;
                                unset( $vars['pagename'] ); unset( $vars['name'] );
                            }
                        }
                    }
                }
            }
        }
        return $vars;
    }

    public function prevent_canonical_redirect( $redirect_url, $requested_url ) {
        if ( strpos( $requested_url, '/en/' ) !== false || get_query_var( 'lang' ) === 'en' ) return false;
        return $redirect_url;
    }

    public function filter_permalink( $url, $post ) {
        $post = get_post( $post );
        if ( ! $post ) return $url;
        $lang = get_post_meta( $post->ID, '_fikup_lang', true );
        
        if ( $lang === 'en' ) return self::inject_en_prefix( $url );
        if ( $lang !== 'en' ) return self::strip_en_prefix( $url );
        
        return $url;
    }

    public function filter_term_link( $url, $term ) {
        if ( self::$current_lang === 'en' ) return self::inject_en_prefix( $url );
        return $url;
    }

    public function filter_home_url( $url, $path ) {
        if ( self::$current_lang === 'en' ) {
            if ( strpos( $url, '/en/' ) !== false ) return $url;
            $home_root = rtrim( get_option( 'home' ), '/' );
            if ( $url === $home_root || $url === $home_root . '/' ) return $home_root . '/en/';
            return str_replace( $home_root, $home_root . '/en', $url );
        }
        return $url;
    }

    public static function is_english() {
        return self::$current_lang === 'en';
    }
}