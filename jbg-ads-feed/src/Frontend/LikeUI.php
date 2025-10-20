<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

/**
 * Like/Dislike UI bootstrap (DOM-based injection)
 * - Loads CSS/JS only when needed
 * - Localizes initial reaction & counts
 * - Works with any theme header/title
 */
class LikeUI
{
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        $need  = false;
        $ad_id = 0;

        // Single ad page → interactive like/dislike
        if (is_singular('jbg_ad')) {
            $need  = true;
            $ad_id = (int) get_queried_object_id();
        } else {
            // Pages with cards shortcode → only CSS
            $post = get_post();
            if ($post && has_shortcode((string)$post->post_content, 'jbg_ads')) {
                wp_enqueue_style('jbg-like', JBG_ADS_URL.'assets/css/jbg-like.css', [], '0.2.0');
                return;
            }
        }

        if (!$need) return;

        // Assets
        wp_enqueue_style ('jbg-like', JBG_ADS_URL.'assets/css/jbg-like.css', [], '0.2.0');
        wp_enqueue_script('jbg-like', JBG_ADS_URL.'assets/js/jbg-like.js', [], '0.2.0', true);

        // Initial counts
        $likeCount    = (int) get_post_meta($ad_id, 'jbg_like_count', true);
        $dislikeCount = (int) get_post_meta($ad_id, 'jbg_dislike_count', true);

        // Detect current user reaction
        $reaction = 'none';
        if (is_user_logged_in()) {
            $u = get_current_user_id();
            $reactions = get_user_meta($u, 'jbg_reactions', true);
            if (is_array($reactions) && isset($reactions[$ad_id])) {
                $reaction = ($reactions[$ad_id] === 'dislike') ? 'dislike' : 'like';
            } else {
                // Legacy fallback: liked_ids → like
                $liked_ids = (array) get_user_meta($u, 'jbg_liked_ids', true);
                $liked_ids = array_map('intval', $liked_ids);
                if (in_array($ad_id, $liked_ids, true)) $reaction = 'like';
            }
        }

        // Robust selectors to find the title in different themes/layouts
        $selectors = [
            // موجود قبلی
            '.single-jbg_ad .entry-title',
            '.single-jbg_ad h1.entry-title',
            '.single-jbg_ad h1[itemprop="headline"]',
            '.jbg-single-header .jbg-title',
            'h1[itemprop="headline"]',
            '.entry-title',
            'h1',
            // افزوده برای چیدمان فعلی
            '.jbg-single-title',                              // عنوانی که زیر ویدیو رندر می‌کنیم
            '.wd-single-post-header .wd-entities-title h1',   // هدر وودمارت در برخی اسکین‌ها
        ];

        // Localize data for JS (reaction endpoint)
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
