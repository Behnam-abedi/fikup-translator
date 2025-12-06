<?php
/**
 * Plugin Name: Fikup Poly Core (Ultimate)
 * Description: Custom multilingual engine for Fikup.ir. Features: Decoupled posts, Elementor support, Shared Stock, WoodMart Header integration.
 * Version: 2.0.0
 * Author: Fikup Dev Team
 * Text Domain: fikup-poly
 */

defined( 'ABSPATH' ) || exit;

define( 'FIKUP_POLY_PATH', plugin_dir_path( __FILE__ ) );
define( 'FIKUP_POLY_URL', plugin_dir_url( __FILE__ ) );

// لود کردن هسته اصلی
require_once FIKUP_POLY_PATH . 'includes/class-core.php';

function fikup_poly_init() {
    new Fikup_Poly_Core();
}
add_action( 'plugins_loaded', 'fikup_poly_init' );