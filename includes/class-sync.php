<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Sync {

    public function __construct() {
        // هوک‌های ووکامرس برای آپدیت موجودی و قیمت
        add_action( 'woocommerce_product_set_stock', [ $this, 'sync_stock' ] );
        add_action( 'woocommerce_variation_set_stock', [ $this, 'sync_stock' ] );
        // هوک برای زمانی که قیمت دستی در ادمین آپدیت می‌شود
        add_action( 'save_post_product', [ $this, 'sync_price_on_save' ], 10, 3 );
    }

    public function sync_stock( $product ) {
        $product_id = $product->get_id();
        $qty = $product->get_stock_quantity();

        // جلوگیری از لوپ (A -> B -> A)
        if ( get_transient( 'fikup_sync_lock_' . $product_id ) ) {
            return;
        }

        $linked_id = $this->get_linked_product_id( $product_id );

        if ( $linked_id ) {
            // قفل کردن محصول لینک شده برای 5 ثانیه
            set_transient( 'fikup_sync_lock_' . $linked_id, true, 5 );

            $linked_product = wc_get_product( $linked_id );
            if ( $linked_product ) {
                wc_update_product_stock( $linked_product, $qty );
            }
        }
    }
    
    // تابعی برای سینک قیمت (اختیاری - اگر بخواهید قیمت هم همیشه یکی باشد)
    public function sync_price_on_save( $post_id, $post, $update ) {
        // منطق مشابه بالا برای قیمت (_regular_price, _sale_price)
        // اینجا کدنویسی نمی‌کنیم تا شلوغ نشود، اما جایش اینجاست.
    }

    private function get_linked_product_id( $post_id ) {
        $group_id = get_post_meta( $post_id, '_fikup_translation_group', true );
        if ( ! $group_id ) return false;

        // کوئری برای پیدا کردن پستی که همین گروه را دارد اما آی‌دی‌اش متفاوت است
        global $wpdb;
        $linked_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_fikup_translation_group' AND meta_value = %s AND post_id != %d LIMIT 1",
            $group_id, $post_id
        ));

        return $linked_id;
    }
}