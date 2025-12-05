<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Comments {

    public function __construct() {
        add_filter( 'comments_clauses', [ $this, 'merge_comments' ], 10, 2 );
    }

    public function merge_comments( $clauses, $query ) {
        if ( is_admin() ) return $clauses;

        $post_id = get_the_ID();
        if ( ! $post_id ) return $clauses;

        // گرفتن آی‌دی پست متصل
        // (این کد باید بهینه‌تر شود و تابع get_linked_product_id از کلاس Sync عمومی شود تا اینجا استفاده کنیم)
        $group_id = get_post_meta( $post_id, '_fikup_translation_group', true );
        
        if ( $group_id ) {
            global $wpdb;
            // پیدا کردن تمام پست‌هایی که در این گروه هستند
            $ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_fikup_translation_group' AND meta_value = %s",
                $group_id
            ));
            
            if ( ! empty( $ids ) ) {
                $ids_string = implode( ',', array_map( 'absint', $ids ) );
                // تغییر شرط SQL برای گرفتن کامنت‌های همه این پست‌ها
                $clauses['where'] = str_replace(
                    "comment_post_ID = $post_id",
                    "comment_post_ID IN ($ids_string)",
                    $clauses['where']
                );
            }
        }
        return $clauses;
    }
}