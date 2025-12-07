<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_admin_menu() {
        add_menu_page(
            'تنظیمات چندزبانه', 'Fikup Poly', 'manage_options', 'fikup-poly',
            [ $this, 'render_settings_page' ], 'dashicons-translation', 80
        );
    }

    public function register_settings() {
        // تنظیمات عمومی
        register_setting( 'fikup_poly_general_group', 'fikup_woodmart_header_id' );
        register_setting( 'fikup_poly_general_group', 'fikup_woodmart_footer_id' );
        register_setting( 'fikup_poly_general_group', 'fikup_enable_stock_sync' );
        register_setting( 'fikup_poly_general_group', 'fikup_custom_css_en' );

        // تنظیمات دیزاین سوئیچر (بخش اصلاح شده)
        // قبلاً آرگومان اول (نام گروه) در خطوط پایین جا افتاده بود که باعث ارور می‌شد
        register_setting( 'fikup_poly_switcher_group', 'fikup_switcher_bg_color' );
        register_setting( 'fikup_poly_switcher_group', 'fikup_switcher_text_color' );
        register_setting( 'fikup_poly_switcher_group', 'fikup_switcher_active_bg' );
        register_setting( 'fikup_poly_switcher_group', 'fikup_switcher_active_text' );
        register_setting( 'fikup_poly_switcher_group', 'fikup_switcher_radius' );
        register_setting( 'fikup_poly_switcher_group', 'fikup_switcher_padding' );

        // حلقه ترجمه
        register_setting( 'fikup_poly_strings_group', 'fikup_translations_list', [ 
            'type' => 'array',
            'sanitize_callback' => [ $this, 'sanitize_translations' ]
        ] );
    }

    public function sanitize_translations( $input ) {
        $clean = [];
        if ( is_array( $input ) ) {
            foreach ( $input as $item ) {
                if ( ! empty( $item['key'] ) ) {
                    $clean[] = [
                        'key' => sanitize_text_field( $item['key'] ),
                        'val' => wp_kses_post( $item['val'] )
                    ];
                }
            }
        }
        return $clean;
    }

    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1>سیستم چندزبانه Fikup (نسخه Native)</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=fikup-poly&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">تنظیمات اصلی</a>
                <a href="?page=fikup-poly&tab=switcher" class="nav-tab <?php echo $active_tab == 'switcher' ? 'nav-tab-active' : ''; ?>">دیزاین سوئیچر</a>
                <a href="?page=fikup-poly&tab=strings" class="nav-tab <?php echo $active_tab == 'strings' ? 'nav-tab-active' : ''; ?>">حلقه ترجمه</a>
            </h2>
            <form method="post" action="options.php">
                <?php 
                if ( $active_tab == 'general' ) {
                    settings_fields( 'fikup_poly_general_group' );
                    $this->render_general_tab();
                } elseif ( $active_tab == 'switcher' ) {
                    settings_fields( 'fikup_poly_switcher_group' );
                    $this->render_switcher_tab();
                } elseif ( $active_tab == 'strings' ) {
                    settings_fields( 'fikup_poly_strings_group' );
                    $this->render_strings_tab();
                }
                submit_button(); 
                ?>
            </form>
        </div>
        <?php
    }

    private function render_general_tab() {
        ?>
        <table class="form-table">
            <tr><th scope="row">شناسه هدر انگلیسی</th><td><input type="text" name="fikup_woodmart_header_id" value="<?php echo esc_attr( get_option('fikup_woodmart_header_id') ); ?>" class="regular-text"></td></tr>
            <tr><th scope="row">شناسه فوتر انگلیسی</th><td><input type="text" name="fikup_woodmart_footer_id" value="<?php echo esc_attr( get_option('fikup_woodmart_footer_id') ); ?>" class="regular-text"></td></tr>
            <tr><th scope="row">سینک موجودی</th><td><label><input type="checkbox" name="fikup_enable_stock_sync" value="1" <?php checked( get_option('fikup_enable_stock_sync'), 1 ); ?>> فعال‌سازی</label></td></tr>
            <tr><th scope="row">CSS اختصاصی (EN)</th><td><textarea name="fikup_custom_css_en" rows="10" class="large-text code"><?php echo esc_textarea( get_option('fikup_custom_css_en') ); ?></textarea></td></tr>
        </table>
        <?php
    }

    private function render_switcher_tab() {
        ?>
        <div class="notice inline notice-info"><p>از شورت‌کد <code>[fikup_switcher]</code> برای نمایش دکمه تغییر زبان در هدر یا ابزارک‌ها استفاده کنید.</p></div>
        <table class="form-table">
            <tr>
                <th scope="row">رنگ پس‌زمینه کل</th>
                <td><input type="color" name="fikup_switcher_bg_color" value="<?php echo esc_attr( get_option('fikup_switcher_bg_color', '#f1f1f1') ); ?>"></td>
            </tr>
            <tr>
                <th scope="row">رنگ متن (غیرفعال)</th>
                <td><input type="color" name="fikup_switcher_text_color" value="<?php echo esc_attr( get_option('fikup_switcher_text_color', '#333333') ); ?>"></td>
            </tr>
            <tr>
                <th scope="row">رنگ پس‌زمینه (فعال)</th>
                <td><input type="color" name="fikup_switcher_active_bg" value="<?php echo esc_attr( get_option('fikup_switcher_active_bg', '#0073aa') ); ?>"></td>
            </tr>
            <tr>
                <th scope="row">رنگ متن (فعال)</th>
                <td><input type="color" name="fikup_switcher_active_text" value="<?php echo esc_attr( get_option('fikup_switcher_active_text', '#ffffff') ); ?>"></td>
            </tr>
            <tr>
                <th scope="row">گردی گوشه‌ها (px)</th>
                <td><input type="number" name="fikup_switcher_radius" value="<?php echo esc_attr( get_option('fikup_switcher_radius', '5') ); ?>" class="small-text"> پیکسل</td>
            </tr>
            <tr>
                <th scope="row">فاصله داخلی (px)</th>
                <td><input type="number" name="fikup_switcher_padding" value="<?php echo esc_attr( get_option('fikup_switcher_padding', '5') ); ?>" class="small-text"> پیکسل</td>
            </tr>
        </table>
        <?php
    }

    private function render_strings_tab() {
        $translations = get_option( 'fikup_translations_list', [] );
        ?>
        <div class="notice inline notice-info">
            <p><strong>راهنما:</strong> متن موجود در ستون اول، ترجمه در ستون دوم.</p>
        </div>
        <div id="strings-wrapper">
            <table class="widefat fixed striped" style="max-width: 1000px;">
                <thead><tr><th>متن اصلی</th><th>جایگزین</th><th style="width: 50px;">حذف</th></tr></thead>
                <tbody id="strings-list">
                    <?php if(!empty($translations) && is_array($translations)) foreach($translations as $i => $item) $this->render_row($i, $item['key'], $item['val']); ?>
                </tbody>
            </table>
            <br><button type="button" class="button button-primary" id="add-string">+ افزودن کلمه جدید</button>
        </div>
        <script type="text/template" id="tmpl-row">
            <tr>
                <td><input type="text" name="fikup_translations_list[INDEX][key]" class="widefat"></td>
                <td><input type="text" name="fikup_translations_list[INDEX][val]" class="widefat"></td>
                <td><button type="button" class="button remove-row" style="color: #a00;">X</button></td>
            </tr>
        </script>
        <script>
            jQuery(document).ready(function($) {
                $('#add-string').click(function() {
                    var idx = $('#strings-list tr').length + Date.now();
                    $('#strings-list').append($('#tmpl-row').html().replace(/INDEX/g, idx));
                });
                $('body').on('click', '.remove-row', function() { $(this).closest('tr').remove(); });
            });
        </script>
        <?php
    }

    private function render_row( $index, $key, $val ) {
        ?>
        <tr>
            <td><input type="text" name="fikup_translations_list[<?php echo $index; ?>][key]" value="<?php echo esc_attr( $key ); ?>" class="widefat"></td>
            <td><input type="text" name="fikup_translations_list[<?php echo $index; ?>][val]" value="<?php echo esc_attr( $val ); ?>" class="widefat"></td>
            <td><button type="button" class="button remove-row" style="color: #a00;">X</button></td>
        </tr>
        <?php
    }
}