<?php
namespace JBG\Ads\Taxonomy;

class Brand {
    public static function register(): void {
        register_taxonomy('jbg_brand', ['jbg_ad'], [
            'labels' => [
                'name'          => __('Brands', 'jbg-ads'),
                'singular_name' => __('Brand',  'jbg-ads'),
                'add_new_item'  => __('Add New Brand', 'jbg-ads'),
                'search_items'  => __('Search Brands', 'jbg-ads'),
            ],
            'public'            => true,
            'show_ui'           => true,
            'show_in_rest'      => true,    // گوتنبرگ/REST
            'hierarchical'      => false,   // مثل برچسب‌ها
            'show_admin_column' => true,    // ستون خودکار در لیست Adها
            'rewrite'           => ['slug'=>'brand'],
        ]);
    }
}
