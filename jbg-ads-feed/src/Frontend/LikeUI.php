<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * Bootstrap واکنش‌ها: بارگذاری CSS/JS فقط در صفحه تکی آگهی
 * و محلی‌سازی داده‌ها برای jbg-like.js
 */
class LikeUI {
    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void {
        // فقط در صفحهٔ تکی آگهی
        if (!is_singular('jbg_ad')) {
            // صفحات لیستی فقط CSS لازم دارند
            $post = get_post();
            if ($post && has_shortcode((string)$post->post_content, 'jbg_ads')) {
                wp_enqueue_style('jbg-like', JBG_ADS_URL.'assets/css/jbg-like.css', [], '0.2.0');
            }
            return;
        }

        $ad_id = (int) get_queried_object_id();

        // دارایی‌ها
        wp_enqueue_style ('jbg-like', JBG_ADS_URL.'assets/css/jbg-like.css', [], '0.2.0');
        wp_enqueue_script('jbg-like', JBG_ADS_URL.'assets/js/jbg-like.js', [], '0.2.0', true);

        // شمارش‌های اولیه
        $likeCount    = (int) get_post_meta($ad_id, 'jbg_like_count', true);
        $dislikeCount = (int) get_post_meta($ad_id, 'jbg_dislike_count', true);

        // واکنش کاربر فعلی
        $reaction = 'none';
        if (is_user_logged_in()) {
            $u = get_current_user_id();
            $reactions = get_user_meta($u, 'jbg_reactions', true);
            if (is_array($reactions) && isset($reactions[$ad_id])) {
                $reaction = ($reactions[$ad_id] === 'dislike') ? 'dislike' : 'like';
            } else {
                $liked_ids = (array) get_user_meta($u, 'jbg_liked_ids', true);
                $liked_ids = array_map('intval', $liked_ids);
                if (in_array($ad_id, $liked_ids, true)) $reaction = 'like';
            }
        }

        // سِلکتورهای مقاوم برای یافتن عنوان زیر پلیر
        $selectors = [
            '.jbg-single-header .jbg-single-title',
            '.jbg-single-header .jbg-title',
            '.single-jbg_ad .entry-title',
            '.single-jbg_ad h1.entry-title',
            '.single-jbg_ad h1[itemprop="headline"]',
            '.entry-title',
            'h1[itemprop="headline"]',
            'h1'
        ];

        // دادهٔ لازم برای اسکریپت
        wp_localize_script('jbg-like', 'JBG_REACT', [
            'rest'         => rest_url('jbg/v1/reaction'),
            'nonce'        => wp_create_nonce('wp_rest'),
            'adId'         => $ad_id,
            'logged'       => is_user_logged_in(),
            'reaction'     => $reaction,      // like | dislike | none
            'likeCount'    => $likeCount,
            'dislikeCount' => $dislikeCount,
            'selectors'    => $selectors,
        ]);
    }
}
