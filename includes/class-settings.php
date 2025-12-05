<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_admin_menu() {
        add_menu_page(
            'تنظیمات چندزبانه',
            'Fikup Poly',
            'manage_options',
            'fikup-poly',
            [ $this, 'render_settings_page' ],
            'dashicons-translation',
            80
        );
    }

    public function register_settings() {
        // گروه تنظیمات عمومی
        register_setting( 'fikup_poly_group', 'fikup_woodmart_header_id' );
        register_setting( 'fikup_poly_group', 'fikup_enable_stock_sync' );
        register_setting( 'fikup_poly_group', 'fikup_custom_css_en' );

        // گروه تنظیمات آرایه‌ای
        register_setting( 'fikup_poly_group', 'fikup_menu_mappings', [ 'type' => 'array' ] );
        register_setting( 'fikup_poly_group', 'fikup_string_translations', [ 'type' => 'array' ] );
    }

    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1>تنظیمات سیستم چندزبانه Fikup</h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=fikup-poly&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">عمومی & قالب</a>
                <a href="?page=fikup-poly&tab=menus" class="nav-tab <?php echo $active_tab == 'menus' ? 'nav-tab-active' : ''; ?>">اتصال منوها</a>
                <a href="?page=fikup-poly&tab=strings" class="nav-tab <?php echo $active_tab == 'strings' ? 'nav-tab-active' : ''; ?>">ترجمه کلمات</a>
            </h2>

            <form method="post" action="options.php">
                <?php 
                settings_fields( 'fikup_poly_group' );
                
                if ( $active_tab == 'general' ) {
                    $this->render_general_tab();
                } elseif ( $active_tab == 'menus' ) {
                    $this->render_menus_tab();
                } elseif ( $active_tab == 'strings' ) {
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
            <tr>
                <th scope="row">شناسه هدر انگلیسی (WoodMart)</th>
                <td>
                    <input type="text" name="fikup_woodmart_header_id" value="<?php echo esc_attr( get_option('fikup_woodmart_header_id') ); ?>" class="regular-text">
                    <p class="description">آی‌دی هدر ساخته شده در Woodmart Header Builder (مثال: <code>header_123456</code>). اگر خالی باشد هدر اصلی لود می‌شود.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">هماهنگ‌سازی (Sync)</th>
                <td>
                    <label>
                        <input type="checkbox" name="fikup_enable_stock_sync" value="1" <?php checked( get_option('fikup_enable_stock_sync'), 1 ); ?>>
                        هماهنگ‌سازی موجودی انبار (Stock) بین فارسی و انگلیسی
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">CSS اختصاصی (حالت EN)</th>
                <td>
                    <textarea name="fikup_custom_css_en" rows="10" class="large-text code"><?php echo esc_textarea( get_option('fikup_custom_css_en') ); ?></textarea>
                    <p class="description">این کدها فقط در صفحات انگلیسی (/en/) لود می‌شوند.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function render_menus_tab() {
        $mappings = get_option( 'fikup_menu_mappings', [] );
        $all_menus = wp_get_nav_menus();
        ?>
        <p>در این بخش مشخص کنید وقتی سایت انگلیسی شد، هر منوی فارسی با چه منویی جایگزین شود.</p>
        <table class="widefat fixed striped" style="max-width: 600px;">
            <thead>
                <tr>
                    <th>منوی اصلی (فارسی)</th>
                    <th>جایگزین انگلیسی</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $all_menus as $menu ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $menu->name ); ?></strong></td>
                        <td>
                            <select name="fikup_menu_mappings[<?php echo $menu->term_id; ?>]" style="width: 100%;">
                                <option value="">-- بدون تغییر --</option>
                                <?php foreach ( $all_menus as $option_menu ) : ?>
                                    <option value="<?php echo $option_menu->term_id; ?>" 
                                        <?php selected( isset($mappings[$menu->term_id]) ? $mappings[$menu->term_id] : '', $option_menu->term_id ); ?>>
                                        <?php echo esc_html( $option_menu->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_strings_tab() {
        $translations = get_option( 'fikup_string_translations', [] );
        ?>
        <p>ترجمه کلمات سیستمی (دکمه‌های ووکامرس، خطاهای فرم و...).</p>
        
        <div id="strings-wrapper">
            <?php 
            if ( ! empty( $translations ) ) {
                foreach ( $translations as $i => $item ) {
                    if(!empty($item['org'])) $this->render_string_row( $i, $item['org'], $item['trans'] );
                }
            }
            // سطر خالی برای افزودن
            $this->render_string_row( 9999, '', '' );
            ?>
        </div>
        <p class="description">بعد از وارد کردن و ذخیره، سطر جدید اضافه می‌شود.</p>
        <?php
    }

    private function render_string_row( $index, $org, $trans ) {
        ?>
        <div class="string-row" style="margin-bottom: 10px; display: flex; gap: 10px;">
            <input type="text" name="fikup_string_translations[<?php echo $index; ?>][org]" value="<?php echo esc_attr( $org ); ?>" placeholder="متن فارسی" class="regular-text">
            <input type="text" name="fikup_string_translations[<?php echo $index; ?>][trans]" value="<?php echo esc_attr( $trans ); ?>" placeholder="ترجمه انگلیسی" class="regular-text">
        </div>
        <?php
    }
}