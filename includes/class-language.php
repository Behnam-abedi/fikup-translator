<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Language {
    
    public static $current_lang = 'fa';

    public function __construct() {
        // 1. ثبت متغیرهای کوئری
        add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
        // 2. ساخت رول‌های /en/
        add_filter( 'rewrite_rules_array', [ $this, 'add_en_rewrite_rules' ] );
        // 3. تشخیص زبان
        add_action( 'wp', [ $this, 'detect_language' ] );
        // 4. اصلاح لینک‌های خروجی
        add_filter( 'post_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'page_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'post_type_link', [ $this, 'filter_permalink' ], 10, 2 );
        add_filter( 'term_link', [ $this, 'filter_term_link' ], 10, 2 );
        add_filter( 'home_url', [ $this, 'filter_home_url' ], 10, 2 );

        // 5. [جدید] مسیریابی هوشمند برای حل مشکل نامک‌ها
        add_filter( 'request', [ $this, 'intercept_request_for_translation' ] );
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

    /**
     * بخش حیاتی برای حل مشکل 404 و نامک‌ها
     */
    public function intercept_request_for_translation( $vars ) {
        // فقط اگر درخواست انگلیسی بود
        if ( isset( $vars['lang'] ) && $vars['lang'] === 'en' ) {
            
            $target_slug = '';
            $post_type = 'post'; // پیش‌فرض

            // 1. اگر برگه (Page) است
            if ( isset( $vars['pagename'] ) ) {
                $target_slug = $vars['pagename'];
                $post_type = 'page';
            } 
            // 2. اگر محصول یا پست (Post/Product) است
            elseif ( isset( $vars['name'] ) ) {
                $target_slug = $vars['name'];
                if ( isset( $vars['post_type'] ) ) {
                    $post_type = $vars['post_type'];
                }
            }

            if ( $target_slug ) {
                // تلاش برای پیدا کردن پست فارسی (اصلی) با این نامک
                // مثال: ما دنبال 'about-us' هستیم
                $original_post = get_page_by_path( $target_slug, OBJECT, $post_type );

                // اگر پست فارسی پیدا شد، چک می‌کنیم ترجمه دارد یا نه
                if ( $original_post ) {
                    $group_id = get_post_meta( $original_post->ID, '_fikup_translation_group', true );
                    
                    if ( $group_id ) {
                        // پیدا کردن آی‌دی پست انگلیسی هم‌گروه
                        global $wpdb;
                        $en_id = $wpdb->get_var( $wpdb->prepare(
                            "SELECT post_id FROM $wpdb->postmeta 
                             WHERE meta_key = '_fikup_translation_group' 
                             AND meta_value = %s 
                             AND post_id != %d 
                             LIMIT 1",
                            $group_id, $original_post->ID
                        ));

                        if ( $en_id ) {
                            // مسیر را تغییر می‌دهیم به پست انگلیسی (حتی اگر نامکش فرق کند)
                            $vars['page_id'] = $en_id; // برای برگه‌ها
                            $vars['p'] = $en_id;       // برای پست‌ها
                            
                            // حذف نامک‌ها از کوئری تا وردپرس گیج نشود و فقط با ID کار کند
                            unset( $vars['pagename'] );
                            unset( $vars['name'] );
                        }
                    }
                }
            }
        }
        return $vars;
    }

    public function detect_language() {
        if ( get_query_var( 'lang' ) === 'en' ) {
            self::$current_lang = 'en';
            add_filter( 'locale', function() { return 'en_US'; } );
            add_filter( 'body_class', function( $classes ) {
                $classes[] = 'fikup-en-mode';
                return $classes;
            });
        }
    }

    public function filter_permalink( $url, $post ) {
        $post = get_post( $post );
        if ( ! $post ) return $url;

        $lang = get_post_meta( $post->ID, '_fikup_lang', true );
        if ( $lang === 'en' ) {
            // [بهبود] برای زیبایی، لینک را بر اساس نامک فارسی تولید می‌کنیم (اختیاری)
            // فعلا همان لینک استاندارد را /en/ می‌زنیم
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
            // جلوگیری از دوبله شدن en
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