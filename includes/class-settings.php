<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
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

    public function render_admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'fikup' ) === false ) return;
        ?>
        <div class="notice notice-info is-dismissible">
            <p><strong>نکته مهم برای ترجمه:</strong></p>
            <p>
                در فیلد <strong>"متن اصلی"</strong>، باید دقیقاً کلمه انگلیسی که در کد قالب وجود دارد (مثلاً <code>Cart</code> یا <code>Subtotal</code>) را وارد کنید.<br>
                اگر کلمه فارسی (مثلاً "سبد خرید") را وارد کنید، ترجمه کار نخواهد کرد، زیرا در حالت انگلیسی، وردپرس اصلا کلمات فارسی را نمی‌بیند.
            </p>
        </div>
        <?php
    }

    public function register_settings() {
        // تنظیمات عمومی
        register_setting( 'fikup_poly_general_group', 'fikup_woodmart_header_id' );
        register_setting( 'fikup_poly_general_group', 'fikup_woodmart_footer_id' );
        register_setting( 'fikup_poly_general_group', 'fikup_enable_stock_sync' );
        register_setting( 'fikup_poly_general_group', 'fikup_custom_css_en' );

        // تنظیمات ترجمه (با تابع تمیزکننده)
        register_setting( 'fikup_poly_strings_group', 'fikup_string_translations', [ 
            'type' => 'array',
            'sanitize_callback' => [ $this, 'sanitize_translations' ]
        ] );
    }

    public function sanitize_translations( $input ) {
        $clean = [];
        if ( is_array( $input ) ) {
            foreach ( $input as $item ) {
                if ( ! empty( $item['org'] ) && ! empty( $item['trans'] ) ) {
                    $clean[] = [
                        'org'   => sanitize_text_field( $item['org'] ), // حفظ فاصله و کاراکترها
                        'trans' => sanitize_text_field( $item['trans'] )
                    ];
                }
            }
        }
        return $clean; // بازگرداندن آرایه تمیز و ایندکس شده
    }

    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1>تنظیمات سیستم چندزبانه Fikup</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=fikup-poly&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">تنظیمات اصلی</a>
                <a href="?page=fikup-poly&tab=strings" class="nav-tab <?php echo $active_tab == 'strings' ? 'nav-tab-active' : ''; ?>">ترجمه کلمات</a>
            </h2>
            <form method="post" action="options.php">
                <?php 
                if ( $active_tab == 'general' ) {
                    settings_fields( 'fikup_poly_general_group' );
                    $this->render_general_tab();
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
            <tr>
                <th scope="row">شناسه هدر انگلیسی</th>
                <td><input type="text" name="fikup_woodmart_header_id" value="<?php echo esc_attr( get_option('fikup_woodmart_header_id') ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row">شناسه فوتر انگلیسی</th>
                <td><input type="text" name="fikup_woodmart_footer_id" value="<?php echo esc_attr( get_option('fikup_woodmart_footer_id') ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row">سینک موجودی</th>
                <td><label><input type="checkbox" name="fikup_enable_stock_sync" value="1" <?php checked( get_option('fikup_enable_stock_sync'), 1 ); ?>> فعال‌سازی</label></td>
            </tr>
            <tr>
                <th scope="row">CSS اختصاصی (EN)</th>
                <td><textarea name="fikup_custom_css_en" rows="10" class="large-text code"><?php echo esc_textarea( get_option('fikup_custom_css_en') ); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    private function render_strings_tab() {
        $translations = get_option( 'fikup_string_translations', [] );
        ?>
        <div id="strings-container">
            <table class="widefat fixed striped" style="max-width: 800px;">
                <thead>
                    <tr>
                        <th>متن اصلی (انگلیسی داخل کد)</th>
                        <th>ترجمه نمایشی (انگلیسی دلخواه)</th>
                        <th style="width: 60px;">حذف</th>
                    </tr>
                </thead>
                <tbody id="strings-list">
                    <?php 
                    if ( ! empty( $translations ) && is_array( $translations ) ) {
                        foreach ( $translations as $i => $item ) {
                            $this->render_string_row( $i, $item['org'], $item['trans'] );
                        }
                    }
                    ?>
                </tbody>
            </table>
            <br>
            <button type="button" class="button" id="add-new-row">+ افزودن سطر جدید</button>
        </div>

        <script type="text/template" id="row-template">
            <tr>
                <td><input type="text" name="fikup_string_translations[INDEX][org]" class="widefat" placeholder="مثال: Cart"></td>
                <td><input type="text" name="fikup_string_translations[INDEX][trans]" class="widefat" placeholder="مثال: Bag"></td>
                <td><button type="button" class="button remove-row" style="color: #a00;">X</button></td>
            </tr>
        </script>

        <script>
            jQuery(document).ready(function($) {
                // افزودن سطر
                $('#add-new-row').on('click', function() {
                    var index = $('#strings-list tr').length + 1000; // ایندکس یونیک
                    var template = $('#row-template').html().replace(/INDEX/g, index);
                    $('#strings-list').append(template);
                });

                // حذف سطر (از طریق Delegation برای سطرهای جدید)
                $('#strings-list').on('click', '.remove-row', function() {
                    if(confirm('آیا مطمئن هستید؟ (برای اعمال نهایی باید دکمه ذخیره را بزنید)')) {
                        $(this).closest('tr').remove();
                    }
                });
            });
        </script>
        <?php
    }

    private function render_string_row( $index, $org, $trans ) {
        ?>
        <tr>
            <td><input type="text" name="fikup_string_translations[<?php echo $index; ?>][org]" value="<?php echo esc_attr( $org ); ?>" class="widefat"></td>
            <td><input type="text" name="fikup_string_translations[<?php echo $index; ?>][trans]" value="<?php echo esc_attr( $trans ); ?>" class="widefat"></td>
            <td><button type="button" class="button remove-row" style="color: #a00;">X</button></td>
        </tr>
        <?php
    }
}