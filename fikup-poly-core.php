<?php
/**
 * Plugin Name: Fikup Poly Core
 * Description: Custom multilingual engine for Fikup.ir (Decoupled Architecture).
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: fikup-poly
 */

defined( 'ABSPATH' ) || exit;

// تعریف ثابت‌های مسیر
define( 'FIKUP_POLY_PATH', plugin_dir_path( __FILE__ ) );
define( 'FIKUP_POLY_URL', plugin_dir_url( __FILE__ ) );

// لود کردن کلاس هسته
require_once FIKUP_POLY_PATH . 'includes/class-core.php';

// اجرا
function fikup_poly_init() {
    new Fikup_Poly_Core();
}
add_action( 'plugins_loaded', 'fikup_poly_init' );