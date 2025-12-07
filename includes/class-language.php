<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Language {
    
    public static $current_lang = 'fa';

    public function __construct() {
        add_filter( 'locale', [ $this, 'set_locale' ], 1 );
        add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
        add_filter( 'rewrite_rules_array', [ $this, 'add_en_rewrite_rules' ] );
        
        add_filter( 'post_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'page_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'post_type_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'term_link', [ $this, 'filter_term_link' ], 10, 2 );
        add_filter( 'home_url', [ $this, 'filter_home_url' ], 10, 2 );
        
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
     * هسته اصلی تولید لینک ترجمه (با قابلیت Slug Matching)
     */
    public static function get_translated_url() {
        $is_currently_en = self::is_english();
        $target_lang = $is_currently_en ? 'fa' : 'en';
        $final_url = '';

        if ( is_singular() ) {
            $current_id = get_the_ID();
            
            // روش ۱: استفاده از اتصال دستی (Metabox/Duplicate)
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
                    $final_url = self::get_clean_permalink( $target_id, $target_lang );
                }
            }

            // روش ۲: اگر اتصال دستی نبود، پیدا کردن از روی نامک (Slug Matching)
            if ( empty( $final_url ) ) {
                global $wpdb;
                $current_post = get_post( $current_id );
                if ( $current_post ) {
                    $slug = $current_post->post_name;
                    $post_type = $current_post->post_type;
                    
                    // پیدا کردن پستی با همین نامک اما زبان متفاوت
                    $fuzzy_id = $wpdb->get_var( $wpdb->prepare(
                        "SELECT p.ID FROM $wpdb->posts p
                         INNER JOIN $wpdb->postmeta m ON p.ID = m.post_id
                         WHERE p.post_name = %s 
                         AND p.post_type = %s 
                         AND p.post_status = 'publish'
                         AND m.meta_key = '_fikup_lang' 
                         AND m.meta_value = %s
                         LIMIT 1",
                        $slug, $post_type, $target_lang
                    ));

                    if ( $fuzzy_id ) {
                        $final_url = self::get_clean_permalink( $fuzzy_id, $target_lang );
                    }
                }
            }
        }

        // ۳. فال‌بک به صفحه اصلی
        if ( empty( $final_url ) ) {
            $home = rtrim( home_url(), '/' ); // استفاده از home_url برای اطمینان از پروتکل
            $final_url = ( $target_lang === 'en' ) ? $home . '/en/' : $home . '/';
        }

        // ۴. پاکسازی نهایی و حیاتی (حذف/اضافه کردن EN)
        if ( $target_lang === 'fa' ) {
            return self::strip_en_prefix( $final_url );
        } else {
            return self::inject_en_prefix( $final_url );
        }
    }

    /**
     * تابع کمکی برای دریافت لینک تمیز از ID
     */
    private static function get_clean_permalink( $id, $target_lang ) {
        $url = get_permalink( $id );
        if ( strpos( $url, 'page_id=' ) !== false || strpos( $url, '?p=' ) !== false ) {
            $slug_path = get_page_uri( $id );
            if ( $slug_path ) {
                $home = rtrim( home_url(), '/' );
                $url = ( $target_lang === 'en' ) ? $home . '/en/' . $slug_path . '/' : $home . '/' . $slug_path . '/';
            }
        }
        return $url;
    }

    // --- توابع کمکی (اصلاح شده برای پروتکل) ---

    private static function inject_en_prefix( $url ) {
        if ( strpos( $url, '/en/' ) !== false ) return $url;
        
        $home = rtrim( home_url(), '/' );
        // جایگزینی هوشمند که به http/https کاری ندارد
        $url_parts = parse_url( $url );
        $home_parts = parse_url( $home );
        
        if ( isset( $url_parts['path'] ) ) {
            // اضافه کردن /en به اول path
            $new_path = '/en' . '/' . ltrim( $url_parts['path'], '/' );
            // بازسازی URL
            $scheme = isset($url_parts['scheme']) ? $url_parts['scheme'] . '://' : (isset($home_parts['scheme']) ? $home_parts['scheme'] . '://' : '//');
            $host = isset($url_parts['host']) ? $url_parts['host'] : (isset($home_parts['host']) ? $home_parts['host'] : '');
            return $scheme . $host . rtrim($new_path, '/') . '/';
        }
        
        return $home . '/en/';
    }

    private static function strip_en_prefix( $url ) {
        // روش تهاجمی: حذف /en/ از هر جای آدرس
        // این روش بسیار مطمئن‌تر از strpos ساده است
        $url = str_replace( '/en/', '/', $url );
        
        // اگر آدرس با /en تمام شد (مثل صفحه اصلی انگلیسی)
        if ( substr( $url, -3 ) === '/en' ) {
            $url = substr( $url, 0, -3 );
        }
        
        return $url;
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
            $home_root = rtrim( home_url(), '/' );
            if ( $url === $home_root || $url === $home_root . '/' ) return $home_root . '/en/';
            return str_replace( $home_root, $home_root . '/en', $url );
        }
        return $url;
    }

    public static function is_english() {
        return self::$current_lang === 'en';
    }
}