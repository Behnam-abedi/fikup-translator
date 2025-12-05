<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Translator {

    private $translations = [];

    public function __construct() {
        // 1. بارگذاری ترجمه‌ها از دیتابیس (فقط یک‌بار)
        $this->translations = get_option( 'fikup_string_translations', [] );

        // 2. فیلتر ترجمه روی فرانت‌اند
        add_filter( 'gettext', [ $this, 'translate_strings' ], 20, 3 );
        
        // 3. اضافه کردن منوی تنظیمات به ادمین
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
    }

    /**
     * هسته اصلی ترجمه: اگر انگلیسی بودیم، متن را عوض کن
     */
    public function translate_strings( $translated, $text, $domain ) {
        // شرط حیاتی: فقط در حالت انگلیسی اجرا شود
        if ( ! Fikup_Poly_Language::is_english() ) {
            return $translated;
        }

        // اگر ترجمه‌ای برای این متن خاص در دیتابیس ثبت شده بود
        if ( isset( $this->translations[ $text ] ) && ! empty( $this->translations[ $text ] ) ) {
            return $this->translations[ $text ];
        }

        return $translated;
    }

    /**
     * اضافه کردن زیرمنو به بخش تنظیمات
     */
    public function add_admin_menu() {
        add_options_page(
            'Fikup Translations',    // عنوان صفحه
            'ترجمه کلمات (Fikup)',   // نام منو
            'manage_options',        // دسترسی
            'fikup-translations',    // اسلاگ
            [ $this, 'render_settings_page' ] // تابع نمایش
        );
    }

    /**
     * ساختن ظاهر صفحه تنظیمات و ذخیره کردن فرم
     */
    public function render_settings_page() {
        // --- بخش ذخیره‌سازی ---
        if ( isset( $_POST['fikup_save_strings'] ) && check_admin_referer( 'fikup_trans_nonce' ) ) {
            $originals = isset( $_POST['original'] ) ? $_POST['original'] : [];
            $trans     = isset( $_POST['translation'] ) ? $_POST['translation'] : [];
            
            $clean_data = [];
            for ( $i = 0; $i < count( $originals ); $i++ ) {
                $org = sanitize_text_field( wp_unslash( $originals[$i] ) );
                $trn = sanitize_text_field( wp_unslash( $trans[$i] ) );
                
                if ( ! empty( $org ) && ! empty( $trn ) ) {
                    $clean_data[ $org ] = $trn;
                }
            }
            
            update_option( 'fikup_string_translations', $clean_data );
            $this->translations = $clean_data; // آپدیت متغیر لوکال
            echo '<div class="notice notice-success is-dismissible"><p>ترجمه‌ها با موفقیت ذخیره شدند.</p></div>';
        }

        // --- بخش نمایش HTML ---
        ?>
        <div class="wrap">
            <h1>مدیریت ترجمه کلمات (مخصوص نسخه انگلیسی)</h1>
            <p>در اینجا کلمات فارسی را وارد کنید و معادل انگلیسی آن‌ها را بنویسید. این تغییرات فقط در آدرس <code>/en/</code> اعمال می‌شوند.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'fikup_trans_nonce' ); ?>
                
                <table class="widefat fixed" id="fikup-trans-table" style="max-width: 800px; margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th>متن اصلی (فارسی)</th>
                            <th>ترجمه انگلیسی</th>
                            <th style="width: 50px;">حذف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ( ! empty( $this->translations ) ) {
                            foreach ( $this->translations as $org => $eng ) {
                                ?>
                                <tr>
                                    <td><input type="text" name="original[]" value="<?php echo esc_attr( $org ); ?>" class="widefat"></td>
                                    <td><input type="text" name="translation[]" value="<?php echo esc_attr( $eng ); ?>" class="widefat"></td>
                                    <td><button type="button" class="button remove-row" style="color: #a00;">X</button></td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                        <tr class="empty-row">
                            <td><input type="text" name="original[]" placeholder="مثال: سبد خرید" class="widefat"></td>
                            <td><input type="text" name="translation[]" placeholder="مثال: Cart" class="widefat"></td>
                            <td><button type="button" class="button remove-row">X</button></td>
                        </tr>
                    </tbody>
                </table>

                <button type="button" class="button" id="add-row">+ افزودن سطر جدید</button>
                <br><br>
                <input type="submit" name="fikup_save_strings" value="ذخیره تغییرات" class="button button-primary button-hero">
            </form>

            <script>
                // اسکریپت ساده برای اضافه کردن سطر جدید بدون رفرش
                document.getElementById('add-row').addEventListener('click', function() {
                    var table = document.getElementById('fikup-trans-table').getElementsByTagName('tbody')[0];
                    var newRow = table.rows[table.rows.length - 1].cloneNode(true);
                    
                    // خالی کردن اینپوت‌های سطر جدید
                    var inputs = newRow.getElementsByTagName('input');
                    for(var i=0; i<inputs.length; i++) { inputs[i].value = ''; }
                    
                    table.appendChild(newRow);
                    bindRemoveButtons();
                });

                function bindRemoveButtons() {
                    var btns = document.getElementsByClassName('remove-row');
                    for(var i=0; i<btns.length; i++) {
                        btns[i].onclick = function() {
                            if(document.querySelectorAll('#fikup-trans-table tbody tr').length > 1) {
                                this.closest('tr').remove();
                            } else {
                                alert('حداقل یک سطر باید باقی بماند.');
                            }
                        };
                    }
                }
                bindRemoveButtons();
            </script>
        </div>
        <?php
    }
}