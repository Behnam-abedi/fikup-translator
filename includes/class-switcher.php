<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Switcher {

    public function __construct() {
        add_shortcode( 'fikup_switcher', [ $this, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'load_assets' ] );
    }

    public function load_assets() {
        // دریافت تنظیمات دیزاین از دیتابیس
        $bg_color = get_option( 'fikup_switcher_bg_color', '#f1f1f1' );
        $text_color = get_option( 'fikup_switcher_text_color', '#333' );
        $active_bg = get_option( 'fikup_switcher_active_bg', '#0073aa' );
        $active_text = get_option( 'fikup_switcher_active_text', '#fff' );
        $radius = get_option( 'fikup_switcher_radius', '5' );
        $padding = get_option( 'fikup_switcher_padding', '5' );

        $css = "
            .fikup-lang-switch {
                display: inline-flex;
                align-items: center;
                background: {$bg_color};
                border-radius: {$radius}px;
                padding: {$padding}px;
                font-family: inherit;
                line-height: 1;
                direction: ltr; /* همیشه چپ‌چین برای ثبات */
            }
            .fikup-lang-switch a {
                text-decoration: none !important;
                padding: 6px 12px;
                border-radius: {$radius}px;
                font-size: 14px;
                font-weight: 600;
                color: {$text_color};
                transition: all 0.3s ease;
                margin: 0 2px;
            }
            .fikup-lang-switch a:hover {
                opacity: 0.8;
            }
            .fikup-lang-switch a.active {
                background-color: {$active_bg};
                color: {$active_text};
                cursor: default;
            }
        ";
        wp_register_style( 'fikup-switcher-style', false );
        wp_enqueue_style( 'fikup-switcher-style' );
        wp_add_inline_style( 'fikup-switcher-style', $css );
    }

    public function render_shortcode( $atts ) {
        $is_en = Fikup_Poly_Language::is_english();
        
        // دریافت لینک هوشمند
        $target_url = Fikup_Poly_Language::get_translated_url();
        
        // ساخت HTML
        ob_start();
        ?>
        <div class="fikup-lang-switch">
            <a href="<?php echo $is_en ? $target_url : '#'; ?>" class="<?php echo !$is_en ? 'active' : ''; ?>">FA</a>
            <a href="<?php echo $is_en ? '#' : $target_url; ?>" class="<?php echo $is_en ? 'active' : ''; ?>">EN</a>
        </div>
        <?php
        return ob_get_clean();
    }
}