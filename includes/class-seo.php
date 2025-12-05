<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_SEO {
    public function __construct() {
        add_action( 'wp_head', [ $this, 'output_hreflangs' ] );
    }

    public function output_hreflangs() {
        if ( ! is_single() && ! is_page() ) return;
        $post_id = get_the_ID();
        $group_id = get_post_meta( $post_id, '_fikup_translation_group', true );

        if ( $group_id ) {
            global $wpdb;
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_fikup_lang' AND post_id IN (
                    SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_fikup_translation_group' AND meta_value = %s
                )",
                $group_id
            ));

            foreach ( $results as $row ) {
                $lang = $row->meta_value;
                $url  = get_permalink( $row->post_id );
                $code = ( $lang == 'en' ) ? 'en-US' : 'fa-IR';
                echo '<link rel="alternate" hreflang="' . esc_attr( $code ) . '" href="' . esc_url( $url ) . '" />' . "\n";
            }
        }
    }
}