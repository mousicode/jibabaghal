<?php
namespace JBG\Ads\Taxonomy;

class Brand {
    public static function register(): void {
        register_taxonomy('jbg_brand', ['jbg_ad'], [
            'labels' => [
                'name'               => __('Brands', 'jbg-ads'),
                'singular_name'      => __('Brand',  'jbg-ads'),
                'search_items'       => __('Search Brands', 'jbg-ads'),
                'all_items'          => __('All Brands', 'jbg-ads'),
                'edit_item'          => __('Edit Brand', 'jbg-ads'),
                'update_item'        => __('Update Brand', 'jbg-ads'),
                'add_new_item'       => __('Add New Brand', 'jbg-ads'),
                'new_item_name'      => __('New Brand Name', 'jbg-ads'),
                'menu_name'          => __('Brands', 'jbg-ads'),
                'parent_item'        => __('Parent Brand', 'jbg-ads'),
                'parent_item_colon'  => __('Parent Brand:', 'jbg-ads'),
            ],
            'public'             => true,
            'show_ui'            => true,
            'show_in_rest'       => true,   // برای گوتنبرگ/REST
            'hierarchical'       => true,   // ✅ تغییر به حالت دسته‌ای برای چک‌باکس‌ها در ادمین
            'show_admin_column'  => true,   // ستون خودکار در لیست Adها
            'show_in_quick_edit' => true,   // نمایش در ویرایش سریع
            'rewrite'            => ['slug' => 'brand'], // ساختار لینک بدون تغییر
        ]);
    }
}
