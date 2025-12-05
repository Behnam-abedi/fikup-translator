<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Duplicator {

    public function __construct() {
        // اضافه کردن دکمه به لیست پست‌ها/محصولات
        add_filter( 'post_row_actions', [ $this, 'add_duplicate_action' ], 10, 2 );
        add_filter( 'page_row_actions', [ $this, 'add_duplicate_action' ], 10, 2 );
        
        // اجرای عملیات کپی
        add_action( 'admin_action_fikup_duplicate_to_en', [ $this, 'process_duplication' ] );
    }

    public function add_duplicate_action( $actions, $post ) {
        // چک کنیم اگر قبلاً ترجمه شده، دکمه را نشان ندهیم
        $is_translated = get_post_meta( $post->ID, '_fikup_translation_group', true );
        
        // فقط اگر هنوز گروه ندارد یا نسخه اصلی است (منطق ساده شده)
        // لینک عملیات با Nonce برای امنیت
        $url = wp_nonce_url( 
            admin_url( 'admin.php?action=fikup_duplicate_to_en&post_id=' . $post->ID ), 
            'fikup_duplicate_nonce' 
        );

        $actions['fikup_duplicate'] = '<a href="' . $url . '">Create English Version</a>';
        return $actions;
    }

    public function process_duplication() {
        // 1. بررسی امنیت و دسترسی
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

        // 2. ساخت آرایه پست جدید
        $new_post_args = array(
            'post_title'   => $post->post_title . ' (EN)',
            'post_content' => $post->post_content,
            'post_status'  => 'draft', // در حالت پیش‌نویس ذخیره شود
            'post_type'    => $post->post_type,
            'post_author'  => get_current_user_id(),
        );

        // 3. درج پست جدید
        $new_post_id = wp_insert_post( $new_post_args );

        if ( $new_post_id ) {
            // 4. کپی تمام Meta Fields (شامل قیمت، تنظیمات وودمارت و ...)
            $meta_keys = get_post_custom_keys( $post_id );
            if ( ! empty( $meta_keys ) ) {
                foreach ( $meta_keys as $key ) {
                    // نادیده گرفتن متای داخلی وردپرس که نباید کپی شود
                    if ( in_array( $key, ['_edit_lock', '_edit_last'] ) ) continue;

                    $meta_values = get_post_custom_values( $key, $post_id );
                    foreach ( $meta_values as $value ) {
                        add_post_meta( $new_post_id, $key, maybe_unserialize( $value ) );
                    }
                }
            }

            // 5. ایجاد گروه اتصال (Translation Group)
            // اگر پست اصلی گروه نداشت، یکی می‌سازیم
            $group_id = get_post_meta( $post_id, '_fikup_translation_group', true );
            if ( ! $group_id ) {
                $group_id = uniqid( 'trans_' );
                update_post_meta( $post_id, '_fikup_translation_group', $group_id );
                update_post_meta( $post_id, '_fikup_lang', 'fa' ); // زبان مبدا
            }

            // تنظیم متای پست جدید
            update_post_meta( $new_post_id, '_fikup_translation_group', $group_id );
            update_post_meta( $new_post_id, '_fikup_lang', 'en' ); // زبان مقصد

            // رایرکت به صفحه ویرایش پست جدید
            wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
            exit;
        } else {
            wp_die( 'Error creating duplicate post.' );
        }
    }
}