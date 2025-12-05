<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Core {

    public function __construct() {
        $this->load_dependencies();
    }

    private function load_dependencies() {
        // ترتیب لود شدن مهم است
        require_once FIKUP_POLY_PATH . 'includes/class-settings.php';
        require_once FIKUP_POLY_PATH . 'includes/class-language.php';
        require_once FIKUP_POLY_PATH . 'includes/class-duplicator.php';
        require_once FIKUP_POLY_PATH . 'includes/class-sync.php';
        require_once FIKUP_POLY_PATH . 'includes/class-ui-logic.php';
        require_once FIKUP_POLY_PATH . 'includes/class-comments.php';
        require_once FIKUP_POLY_PATH . 'includes/class-seo.php';

        // اجرای کلاس‌ها
        new Fikup_Poly_Settings();    // پنل تنظیمات
        new Fikup_Poly_Language();    // مدیریت URL
        new Fikup_Poly_Duplicator();  // کپی کننده پست
        new Fikup_Poly_Sync();        // سینک موجودی
        new Fikup_Poly_UI_Logic();    // تغییرات ظاهری (هدر، منو، ترجمه)
        new Fikup_Poly_Comments();    // کامنت مشترک
        new Fikup_Poly_SEO();         // تگ‌های سئو
    }
}