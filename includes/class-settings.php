<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        
        // نمایش هشدار در کل پنل ادمین
        add_action( 'admin_notices', [ $this, 'render_admin_notice' ] );
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

    /**
     * نمایش هشدار زرد رنگ در بالای پنل مدیریت
     */
    public function render_admin_notice() {
        // فقط به مدیر کل نمایش داده شود
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // بررسی اینکه آیا در صفحه پلاگین هستیم یا خیر (اختیاری: برای اینکه همه جا مزاحم نباشد)
        // اگر می‌خواهید همیشه باشد، شرط screen را بردارید.
        $screen = get_current_screen();
        if ( strpos( $screen->id, 'fikup' ) === false && strpos( $screen->id, 'update' ) === false && strpos( $screen->id, 'themes' ) === false ) {
             return;
        }

        ?>
        <div class="notice notice-warning is-dismissible" style="border-right: 4px solid #f0ad4e;">
            <p><strong>⚠️ هشدار نگهداری (Fikup Poly):</strong></p>
            <p>
                مدیر محترم، سیستم چندزبانه سایت به هسته قالب <strong>WoodMart</strong> متصل است.
                <br>
                لطفاً <strong>قبل از آپدیت قالب</strong>، فایل راهنمای فنی (<code>TECHNICAL_GUIDE.txt</code>) در پوشه پلاگین را مطالعه کنید.
                <br>
                اگر بعد از آپدیت، هدر یا فوتر انگلیسی پرید، احتمالاً نام هوک‌های قالب تغییر کرده است.
            </p>
        </div>
        <?php
    }

    public function register_settings() {
        // --- گروه ۱: تنظیمات اصلی (General) ---
        // نام گروه را تغییر دادیم به fikup_poly_general_group
        register_setting( 'fikup_poly_general_group', 'fikup_woodmart_header_id' );
        register_setting( 'fikup_poly_general_group', 'fikup_woodmart_footer_id' );
        register_setting( 'fikup_poly_general_group', 'fikup_enable_stock_sync' );
        register_setting( 'fikup_poly_general_group', 'fikup_custom_css_en' );

        // --- گروه ۲: ترجمه کلمات (Strings) ---
        // نام گروه را تغییر دادیم به fikup_poly_strings_group
        register_setting( 'fikup_poly_strings_group', 'fikup_string_translations', [ 'type' => 'array' ] );
    }

    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1>تنظیمات سیستم چندزبانه Fikup</h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=fikup-poly&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">تنظیمات اصلی (هدر & فوتر & سینک)</a>
                <a href="?page=fikup-poly&tab=strings" class="nav-tab <?php echo $active_tab == 'strings' ? 'nav-tab-active' : ''; ?>">ترجمه کلمات</a>
            </h2>

            <form method="post" action="options.php">
                <?php 
                // اینجا بسته به تب فعال، گروه تنظیمات مناسب را صدا می‌زنیم
                if ( $active_tab == 'general' ) {
                    settings_fields( 'fikup_poly_general_group' ); // <--- فقط گروه عمومی
                    $this->render_general_tab();
                } elseif ( $active_tab == 'strings' ) {
                    settings_fields( 'fikup_poly_strings_group' ); // <--- فقط گروه ترجمه
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
                    <p class="description">
                        وارد <strong>WoodMart > Header Builder</strong> شوید. هدر انگلیسی خود را بسازید. 
                        آی‌دی آن را (مثلاً <code>header_123456</code>) اینجا وارد کنید.
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">شناسه فوتر انگلیسی (HTML Block)</th>
                <td>
                    <input type="text" name="fikup_woodmart_footer_id" value="<?php echo esc_attr( get_option('fikup_woodmart_footer_id') ); ?>" class="regular-text">
                    <p class="description">
                        وارد <strong>HTML Blocks</strong> شوید و یک فوتر انگلیسی بسازید. 
                        آی‌دی پست آن (مثلاً <code>458</code>) را اینجا وارد کنید.
                    </p>
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
                    <textarea name="fikup_custom_css_en" rows="10" class="large-text code" placeholder=".elementor-widget { direction: ltr; }"><?php echo esc_textarea( get_option('fikup_custom_css_en') ); ?></textarea>
                    <p class="description">کد CSS برای اصلاح چیدمان فقط در نسخه انگلیسی.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function render_strings_tab() {
        $translations = get_option( 'fikup_string_translations', [] );
        ?>
        <p>ترجمه کلمات سیستمی که دسترسی به ویرایش آن‌ها ندارید (مثل متن دکمه‌ها).</p>
        <div id="strings-wrapper">
            <?php 
            if ( ! empty( $translations ) ) {
                foreach ( $translations as $i => $item ) {
                    if(!empty($item['org'])) $this->render_string_row( $i, $item['org'], $item['trans'] );
                }
            }
            $this->render_string_row( 9999, '', '' );
            ?>
        </div>
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