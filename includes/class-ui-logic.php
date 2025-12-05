<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_UI_Logic {

    private $menu_mappings;
    private $string_translations;

    public function __construct() {
        $this->menu_mappings = get_option( 'fikup_menu_mappings', [] );
        
        $saved_strings = get_option( 'fikup_string_translations', [] );
        $this->string_translations = [];
        if( is_array($saved_strings) ) {
            foreach($saved_strings as $item) {
                if(!empty($item['org']) && !empty($item['trans'])) {
                    $this->string_translations[ $item['org'] ] = $item['trans'];
                }
            }
        }

        add_filter( 'wp_nav_menu_args', [ $this, 'swap_menus' ], 999 );
        add_filter( 'woodmart_header_id', [ $this, 'swap_header' ] );
        add_filter( 'gettext', [ $this, 'translate_strings' ], 20, 3 );
        add_action( 'wp_head', [ $this, 'print_custom_css' ] );
    }

    public function swap_menus( $args ) {
        if ( ! Fikup_Poly_Language::is_english() ) return $args;

        $current_id = 0;
        if ( isset( $args['menu'] ) && $args['menu'] ) {
            $m = wp_get_nav_menu_object( $args['menu'] );
            if($m) $current_id = $m->term_id;
        } elseif ( isset( $args['theme_location'] ) && $args['theme_location'] ) {
            $locs = get_nav_menu_locations();
            if ( isset( $locs[ $args['theme_location'] ] ) ) $current_id = $locs[ $args['theme_location'] ];
        }

        if ( $current_id && isset( $this->menu_mappings[ $current_id ] ) ) {
            $mapped_id = intval( $this->menu_mappings[ $current_id ] );
            if ( $mapped_id > 0 ) $args['menu'] = $mapped_id;
        }
        return $args;
    }

    public function swap_header( $id ) {
        if ( Fikup_Poly_Language::is_english() ) {
            $en_header = get_option( 'fikup_woodmart_header_id' );
            if ( ! empty( $en_header ) ) return $en_header;
        }
        return $id;
    }

    public function translate_strings( $translated, $text, $domain ) {
        if ( ! Fikup_Poly_Language::is_english() ) return $translated;
        if ( isset( $this->string_translations[ $text ] ) ) {
            return $this->string_translations[ $text ];
        }
        return $translated;
    }

    public function print_custom_css() {
        if ( Fikup_Poly_Language::is_english() ) {
            $css = get_option( 'fikup_custom_css_en' );
            if ( ! empty( $css ) ) {
                echo '<style>' . wp_strip_all_tags( $css ) . '</style>';
            }
        }
    }
}