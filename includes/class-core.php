<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Core {

    public function __construct() {
        $this->load_dependencies();
    }

    private function load_dependencies() {
        // ترتیب لود شدن
        require_once FIKUP_POLY_PATH . 'includes/class-settings.php';
        require_once FIKUP_POLY_PATH . 'includes/class-language.php';
        require_once FIKUP_POLY_PATH . 'includes/class-duplicator.php';
        require_once FIKUP_POLY_PATH . 'includes/class-sync.php';
        require_once FIKUP_POLY_PATH . 'includes/class-ui-logic.php';
        require_once FIKUP_POLY_PATH . 'includes/class-comments.php';
        require_once FIKUP_POLY_PATH . 'includes/class-seo.php';
        require_once FIKUP_POLY_PATH . 'includes/class-switcher.php'; // <--- فایل جدید اضافه شد

        // اجرای کلاس‌ها
        new Fikup_Poly_Settings();    
        new Fikup_Poly_Language();    
        new Fikup_Poly_Duplicator();  
        new Fikup_Poly_Sync();        
        new Fikup_Poly_UI_Logic();    
        new Fikup_Poly_Comments();    
        new Fikup_Poly_SEO();
        new Fikup_Poly_Switcher(); // <--- کلاس جدید اجرا شد
    }
}