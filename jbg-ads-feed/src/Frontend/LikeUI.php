<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * Like/Dislike UI bootstrap (DOM-based injection)
 * - Loads CSS/JS only when needed
 * - Localizes initial reaction & counts
 * - No dependency on the_title hook (works with any theme)
 */
class LikeUI {

    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void {
        $need   = false;
        $ad_id  = 0;

        // Single ad page → interactive like/dislike
        if (is_singular('jbg_ad')) {
            $need  = true;
            $ad_id = (int) get_queried_object_id();
        } else {
            // Pages that render the cards shortcode → we still want CSS for counters (no JS needed here)
            $post = get_post();
            if ($post && has_shortcode((string)$post->post_content, 'jbg_ads')) {
                wp_enqueue_style('jbg-like', JBG_ADS_URL.'assets/css/jbg-like.css', [], '0.2.0');
                return;
            }
        }

        if (!$need) return;

        // Enqueue assets
        wp_enqueue_style ('jbg-like', JBG_ADS_URL.'assets/css/jbg-like.css', [], '0.2.0');
        wp_enqueue_script('jbg-like', JBG_ADS_URL.'assets/js/jbg-like.js', [], '0.2.0', true);

        // Read initial counts
        $likeCount    = (int) get_post_meta($ad_id, 'jbg_like_count', true);
        $dislikeCount = (int) get_post_meta($ad_id, 'jbg_dislike_count', true);

        // Detect user's current reaction (like | dislike | none)
        $reaction = 'none';
        if (is_user_logged_in()) {
            $u = get_current_user_id();
            $reactions = get_user_meta($u, 'jbg_reactions', true);
            if (is_array($reactions) && isset($reactions[$ad_id])) {
                $reaction = ($reactions[$ad_id] === 'dislike') ? 'dislike' : 'like';
            } else {
                // Legacy: if previously liked via jbg_liked_ids
                $liked_ids = (array) get_user_meta($u, 'jbg_liked_ids', true);
                $liked_ids = array_map('intval', $liked_ids);
                if (in_array($ad_id, $liked_ids, true)) $reaction = 'like';
            }
        }

        // Localize data for JS
        wp_localize_script('jbg-like', 'JBG_REACT', [
            'rest'         => rest_url('jbg/v1/reaction'), // new reaction endpoint
            'nonce'        => wp_create_nonce('wp_rest'),
            'adId'         => $ad_id,
            'logged'       => is_user_logged_in(),
            'reaction'     => $reaction,      // like | dislike | none
            'likeCount'    => $likeCount,
            'dislikeCount' => $dislikeCount,
            // robust selectors to find the single page title
            'selectors'    => [
                '.single-jbg_ad .entry-title',
                '.single-jbg_ad h1.entry-title',
                '.single-jbg_ad h1[itemprop="headline"]',
                '.jbg-single-header .jbg-single-title',
                '.jbg-single-header .jbg-title',
                '.entry-title',
                'h1[itemprop="headline"]',
                'h1'
            ],
        ]);
    }
}
