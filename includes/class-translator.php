<?php
defined( 'ABSPATH' ) || exit;

class Fikup_Poly_Translator {

    public function __construct() {
        add_filter( 'gettext', [ $this, 'translate_theme_strings' ], 20, 3 );
        add_filter( 'ngettext', [ $this, 'translate_theme_strings_plural' ], 20, 5 );
    }

    public function translate_theme_strings( $translated, $text, $domain ) {
        if ( ! Fikup_Poly_Language::is_english() ) {
            return $translated;
        }

        // دیکشنری دستی شما
        $translations = [
            'سبد خرید' => 'Cart',
            'جستجو'    => 'Search',
            'تومان'    => '$', // فقط لیبل
            'افزودن به سبد خرید' => 'Add to Cart',
        ];

        if ( isset( $translations[ $text ] ) ) {
            return $translations[ $text ];
        }
        
        // اگر متن از قبل انگلیسی بود، همان را برگردان
        return $translated;
    }
    
    public function translate_theme_strings_plural( $translation, $single, $plural, $number, $domain ) {
         // لاجیک مشابه برای جمع بستن کلمات
         return $translation;
    }
}