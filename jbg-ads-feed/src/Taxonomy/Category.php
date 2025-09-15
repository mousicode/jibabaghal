<?php
namespace JBG\Ads\Taxonomy;

class Category {
    public static function register(): void {
        $labels = [
            'name'                       => __('دسته‌بندی‌ها', 'jbg-ads'),
            'singular_name'              => __('دسته‌بندی', 'jbg-ads'),
            'search_items'               => __('جستجوی دسته‌بندی', 'jbg-ads'),
            'all_items'                  => __('همهٔ دسته‌بندی‌ها', 'jbg-ads'),
            'edit_item'                  => __('ویرایش دسته‌بندی', 'jbg-ads'),
            'update_item'                => __('به‌روزرسانی دسته‌بندی', 'jbg-ads'),
            'add_new_item'               => __('افزودن دسته‌بندی جدید', 'jbg-ads'),
            'new_item_name'              => __('نام دسته‌بندی جدید', 'jbg-ads'),
            'menu_name'                  => __('دسته‌بندی‌ها', 'jbg-ads'),
            'parent_item'                => __('دستهٔ مادر', 'jbg-ads'),
            'parent_item_colon'          => __('دستهٔ مادر:', 'jbg-ads'),
        ];

        register_taxonomy('jbg_cat', ['jbg_ad'], [
            'labels'            => $labels,
            'hierarchical'      => true,              // مثل «دسته‌ها»
            'show_ui'           => true,
            'show_admin_column' => true,              // ستون در لیست ادمین
            'show_in_quick_edit'=> true,
            'show_in_rest'      => true,              // گوتنبرگ/REST
            'query_var'         => true,
            'rewrite'           => [
                'slug'         => 'ad-category',
                'with_front'   => false,
                'hierarchical' => true,
            ],
        ]);
    }
}
