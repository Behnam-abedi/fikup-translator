<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Core {

    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    private function load_dependencies() {
        require_once FIKUP_POLY_PATH . 'includes/class-language.php';
        require_once FIKUP_POLY_PATH . 'includes/class-duplicator.php';
        require_once FIKUP_POLY_PATH . 'includes/class-sync.php';
        require_once FIKUP_POLY_PATH . 'includes/class-translator.php';
        require_once FIKUP_POLY_PATH . 'includes/class-comments.php';
        require_once FIKUP_POLY_PATH . 'includes/class-seo.php';

        // Initialize modules
        new Fikup_Poly_Language();
        new Fikup_Poly_Duplicator();
        new Fikup_Poly_Sync();
        new Fikup_Poly_Translator();
        new Fikup_Poly_Comments();
        new Fikup_Poly_SEO();
    }
    
    private function define_admin_hooks() {
        // استایل‌ها یا اسکریپت‌های ادمین اگر نیاز بود اینجا لود می‌شوند
    }
}