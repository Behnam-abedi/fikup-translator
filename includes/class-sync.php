<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Sync {
    public function __construct() {
        if ( get_option( 'fikup_enable_stock_sync' ) ) {
            add_action( 'woocommerce_product_set_stock', [ $this, 'sync_stock' ] );
            add_action( 'woocommerce_variation_set_stock', [ $this, 'sync_stock' ] );
        }
    }

    public function sync_stock( $product ) {
        $product_id = $product->get_id();
        $qty = $product->get_stock_quantity();
        
        if ( get_transient( 'fikup_sync_lock_' . $product_id ) ) return;

        $linked_id = $this->get_linked_id( $product_id );
        if ( $linked_id ) {
            set_transient( 'fikup_sync_lock_' . $linked_id, true, 5 );
            $linked = wc_get_product( $linked_id );
            if ( $linked ) wc_update_product_stock( $linked, $qty );
        }
    }

    private function get_linked_id( $post_id ) {
        $group_id = get_post_meta( $post_id, '_fikup_translation_group', true );
        if ( ! $group_id ) return false;
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_fikup_translation_group' AND meta_value = %s AND post_id != %d LIMIT 1",
            $group_id, $post_id
        ));
    }
}