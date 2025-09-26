<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class LikeUI {

    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void {
        if (!is_singular('jbg_ad')) return;

        // استایل و اسکریپت
        wp_enqueue_style('jbg-like', JBG_ADS_URL . 'assets/css/jbg-like.css', [], '0.1.0');
        wp_enqueue_script('jbg-like', JBG_ADS_URL . 'assets/js/jbg-like.js', [], '0.1.3', true);

        $post_id = (int) get_queried_object_id();
        $count   = (int) get_post_meta($post_id, 'jbg_likes_count', true);

        // آیا کاربر قبلاً لایک کرده؟
        $liked = false;
        if (is_user_logged_in()) {
            $liked = (bool) get_user_meta(get_current_user_id(), 'jbg_liked_' . $post_id, true);
        } else {
            // fallback مهمان‌ها: کوکی ساده
            $cookie_key = 'jbg_liked_' . $post_id;
            if (!empty($_COOKIE[$cookie_key]) && $_COOKIE[$cookie_key] === '1') $liked = true;
        }

        // سلکتورهای محتمل عنوان (قابل افزایش)
        $selectors = [
            '.single-jbg_ad .entry-title',
            '.single-jbg_ad h1.entry-title',
            '.single-jbg_ad h1[itemprop="headline"]',
            '.single-jbg_ad h1',
            'h1.entry-title'
        ];

        wp_localize_script('jbg-like', 'JBG_LIKE', [
            'adId'      => $post_id,
            'count'     => $count,
            'liked'     => $liked ? 1 : 0,
            'rest'      => rest_url('jbg/v1/like/toggle'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'selectors' => $selectors,
        ]);
    }
}
