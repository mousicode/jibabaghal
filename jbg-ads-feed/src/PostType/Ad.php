<?php
namespace JBG\Ads\PostType;


class Ad {
public static function register(): void {
$labels = [
'name' => __('Ad Videos','jbg-ads'),
'singular_name' => __('Ad Video','jbg-ads'),
'add_new' => __('Add New','jbg-ads'),
'add_new_item' => __('Add New Ad','jbg-ads'),
'edit_item' => __('Edit Ad','jbg-ads'),
'new_item' => __('New Ad','jbg-ads'),
'view_item' => __('View Ad','jbg-ads'),
'search_items' => __('Search Ads','jbg-ads'),
];
register_post_type('jbg_ad', [
'labels' => $labels,
'public' => true,
'menu_icon' => 'dashicons-video-alt3',
'supports' => ['title','editor','thumbnail'],
'show_in_rest' => true,
'rewrite' => ['slug' => 'ad'],
'capability_type' => 'post',
]);
}
}