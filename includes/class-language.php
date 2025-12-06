<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Language {
    
    public static $current_lang = 'fa';

    public function __construct() {
        // 1. تغییر زبان هسته وردپرس (بسیار مهم برای LTR شدن)
        add_filter( 'locale', [ $this, 'set_locale' ], 1 );

        // 2. تنظیمات URL و Rewrite
        add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
        add_filter( 'rewrite_rules_array', [ $this, 'add_en_rewrite_rules' ] );
        
        // 3. فیلتر لینک‌ها
        add_filter( 'post_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'page_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'post_type_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'term_link', [ $this, 'filter_term_link' ], 10, 2 );
        add_filter( 'home_url', [ $this, 'filter_home_url' ], 10, 2 );
        
        // 4. پردازش درخواست
        add_filter( 'request', [ $this, 'intercept_request_for_translation' ] );
        add_filter( 'redirect_canonical', [ $this, 'prevent_canonical_redirect' ], 10, 2 );

        // 5. اضافه کردن کلاس به body برای استایل‌دهی راحت‌تر
        add_filter( 'body_class', [ $this, 'add_body_classes' ] );
    }

    /**
     * تغییر زبان هسته وردپرس به انگلیسی
     * این تابع باعث می‌شود is_rtl() مقدار false برگرداند و سایت LTR شود.
     */
    public function set_locale( $locale ) {
        // بررسی سریع URL برای تشخیص زبان (چون هنوز query_vars لود نشده)
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
            // اگر آدرس با /en/ شروع شده یا خود /en است
            if ( strpos( $path, '/en/' ) === 0 || $path === '/en' ) {
                self::$current_lang = 'en';
                return 'en_US';
            }
        }

        // بررسی پارامتر GET برای اطمینان
        if ( isset( $_GET['lang'] ) && $_GET['lang'] === 'en' ) {
            self::$current_lang = 'en';
            return 'en_US';
        }

        return $locale;
    }

    public function add_body_classes( $classes ) {
        if ( self::$current_lang === 'en' ) {
            $classes[] = 'fikup-en-mode';
            // حذف کلاس rtl اگر اشتباهاً اضافه شده باشد
            $key = array_search( 'rtl', $classes );
            if ( false !== $key ) {
                unset( $classes[ $key ] );
            }
            // اضافه کردن کلاس ltr برای اطمینان
            $classes[] = 'ltr';
        }
        return $classes;
    }

    // --- بقیه توابع بدون تغییر ---

    public function prevent_canonical_redirect( $redirect_url, $requested_url ) {
        if ( strpos( $requested_url, '/en/' ) !== false || get_query_var( 'lang' ) === 'en' ) {
            return false;
        }
        return $redirect_url;
    }

    public function register_query_vars( $vars ) {
        $vars[] = 'lang';
        return $vars;
    }

    public function add_en_rewrite_rules( $rules ) {
        $new_rules = array();
        foreach ( $rules as $regex => $query ) {
            $new_regex = 'en/' . $regex;
            $new_query = $query . '&lang=en';
            $new_rules[ $new_regex ] = $new_query;
        }
        return $new_rules + $rules;
    }

    public function intercept_request_for_translation( $vars ) {
        if ( isset( $vars['lang'] ) && $vars['lang'] === 'en' ) {
            self::$current_lang = 'en'; // اطمینان مجدد
            
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
                    "SELECT ID FROM $wpdb->posts 
                     INNER JOIN $wpdb->postmeta ON ($wpdb->posts.ID = $wpdb->postmeta.post_id)
                     WHERE post_name = %s AND post_type = %s 
                     AND meta_key = '_fikup_lang' AND meta_value = 'en'
                     LIMIT 1",
                    $target_slug, $post_type
                ));

                if ( $direct_en_post ) {
                    if( $post_type == 'page' ) $vars['page_id'] = $direct_en_post;
                    else $vars['p'] = $direct_en_post;
                } else {
                    $original_post = get_page_by_path( $target_slug, OBJECT, $post_type );
                    if ( $original_post ) {
                        $group_id = get_post_meta( $original_post->ID, '_fikup_translation_group', true );
                        if ( $group_id ) {
                            $en_id = $wpdb->get_var( $wpdb->prepare(
                                "SELECT post_id FROM $wpdb->postmeta 
                                 WHERE meta_key = '_fikup_translation_group' AND meta_value = %s 
                                 AND post_id != %d LIMIT 1",
                                $group_id, $original_post->ID
                            ));
                            if ( $en_id ) {
                                if( $post_type == 'page' ) $vars['page_id'] = $en_id;
                                else $vars['p'] = $en_id;
                                unset( $vars['pagename'] );
                                unset( $vars['name'] );
                            }
                        }
                    }
                }
            }
        }
        return $vars;
    }

    public function filter_permalink( $url, $post ) {
        $post = get_post( $post );
        if ( ! $post ) return $url;
        $lang = get_post_meta( $post->ID, '_fikup_lang', true );
        if ( $lang === 'en' ) {
            return $this->inject_en_prefix( $url );
        }
        return $url;
    }

    public function filter_term_link( $url, $term ) {
        if ( self::$current_lang === 'en' ) {
            return $this->inject_en_prefix( $url );
        }
        return $url;
    }

    public function filter_home_url( $url, $path ) {
        if ( self::$current_lang === 'en' ) {
            if ( strpos( $url, '/en/' ) === false ) {
                return rtrim( $url, '/' ) . '/en/';
            }
        }
        return $url;
    }

    private function inject_en_prefix( $url ) {
        $home = home_url();
        $clean_home = str_replace( ['http://', 'https://'], '', $home );
        $clean_url  = str_replace( ['http://', 'https://'], '', $url );
        if ( strpos( $clean_url, $clean_home ) === 0 ) {
            $path = substr( $clean_url, strlen( $clean_home ) );
            if ( strpos( $path, '/en/' ) !== 0 ) {
                return home_url( '/en' . $path );
            }
        }
        return $url;
    }

    public static function is_english() {
        return self::$current_lang === 'en';
    }
}