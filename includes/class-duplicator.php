<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Duplicator {

    public function __construct() {
        add_filter( 'post_row_actions', [ $this, 'add_duplicate_action' ], 10, 2 );
        add_filter( 'page_row_actions', [ $this, 'add_duplicate_action' ], 10, 2 );
        add_action( 'admin_action_fikup_duplicate_to_en', [ $this, 'process_duplication' ] );
    }

    public function add_duplicate_action( $actions, $post ) {
        $is_translated = get_post_meta( $post->ID, '_fikup_translation_group', true );
        
        // اگر هنوز گروه ترجمه ندارد یا ما می‌خواهیم امکان ساخت مجدد بدهیم
        // (اینجا شرط را ساده نگه داشتیم تا همیشه دکمه باشد)
        $url = wp_nonce_url( 
            admin_url( 'admin.php?action=fikup_duplicate_to_en&post_id=' . $post->ID ), 
            'fikup_duplicate_nonce' 
        );

        $actions['fikup_duplicate'] = '<a href="' . $url . '">Create English Version</a>';
        return $actions;
    }

    public function process_duplication() {
        if ( ! isset( $_GET['post_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
            wp_die( 'No post to duplicate has been supplied!' );
        }
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'fikup_duplicate_nonce' ) ) {
            wp_die( 'Security check failed!' );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( 'You do not have permission to do this.' );
        }

        $post_id = absint( $_GET['post_id'] );
        $post    = get_post( $post_id );

        if ( ! $post ) wp_die( 'Post not found.' );

        $new_post_args = array(
            'post_title'   => $post->post_title . ' (EN)',
            'post_content' => $post->post_content, // کپی محتوای اصلی (برای سئو و فال‌بک)
            'post_status'  => 'draft',
            'post_type'    => $post->post_type,
            'post_author'  => get_current_user_id(),
            'post_excerpt' => $post->post_excerpt,
            'post_parent'  => $post->post_parent, // حفظ ساختار درختی اگر برگه است
        );

        $new_post_id = wp_insert_post( $new_post_args );

        if ( $new_post_id ) {
            // --- [ بخش حیاتی: کپی صحیح متادیتا و المنتور ] ---
            $meta_keys = get_post_custom_keys( $post_id );
            
            if ( ! empty( $meta_keys ) ) {
                foreach ( $meta_keys as $key ) {
                    // نادیده گرفتن متای سیستمی وردپرس
                    if ( in_array( $key, ['_edit_lock', '_edit_last'] ) ) continue;

                    // گرفتن مقدار واقعی (Unserialized)
                    $values = get_post_custom_values( $key, $post_id );
                    
                    foreach ( $values as $value ) {
                        // داده‌ها از دیتابیس معمولا unslashed می‌آیند.
                        // اما برای ذخیره مجدد، اگر داده شامل JSON باشد (مثل المنتور)
                        // باید حتما slash شود تا بک‌اسلش‌های داخل JSON حذف نشوند.
                        
                        $value = maybe_unserialize( $value );
                        
                        if ( is_string( $value ) ) {
                            $value = wp_slash( $value ); 
                        }
                        // اگر آرایه باشد، خود وردپرس هندل می‌کند اما المنتور معمولا استرینگ است.
                        
                        add_post_meta( $new_post_id, $key, $value );
                    }
                }
            }

            // --- [ کپی تکسونومی‌ها (دسته‌بندی، تگ) ] ---
            $taxonomies = get_object_taxonomies( $post->post_type );
            foreach ( $taxonomies as $taxonomy ) {
                $terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
                wp_set_object_terms( $new_post_id, $terms, $taxonomy );
            }

            // --- [ تنظیمات گروه ترجمه ] ---
            $group_id = get_post_meta( $post_id, '_fikup_translation_group', true );
            if ( ! $group_id ) {
                $group_id = uniqid( 'trans_' );
                update_post_meta( $post_id, '_fikup_translation_group', $group_id );
                update_post_meta( $post_id, '_fikup_lang', 'fa' );
            }

            update_post_meta( $new_post_id, '_fikup_translation_group', $group_id );
            update_post_meta( $new_post_id, '_fikup_lang', 'en' );

            // ریدایرکت به ویرایشگر المنتور اگر صفحه المنتوری بود
            $is_elementor = get_post_meta( $new_post_id, '_elementor_edit_mode', true );
            $redirect_url = admin_url( 'post.php?action=edit&post=' . $new_post_id );
            
            // اگر بخواهید مستقیم برود به المنتور (اختیاری)
            // if ($is_elementor === 'builder') {
            //    $redirect_url = admin_url( 'post.php?post=' . $new_post_id . '&action=elementor' );
            // }

            wp_redirect( $redirect_url );
            exit;
        } else {
            wp_die( 'Error creating duplicate post.' );
        }
    }
}