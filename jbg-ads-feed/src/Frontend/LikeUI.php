<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

class LikeUI {

    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        // تزریق inline کنار عنوان، فقط در صفحهٔ تکی jbg_ad
        add_filter('the_title', [self::class, 'inject_inline_into_single_title'], 20, 2);
    }

    public static function enqueue(): void {
        // فقط وقتی لازم است
        $need   = false;
        $cur_id = 0;

        if (is_singular('jbg_ad')) {
            $need   = true;
            $cur_id = (int) get_queried_object_id();
        } else {
            $post = get_post();
            if ($post && has_shortcode((string)$post->post_content, 'jbg_ads')) {
                // برای صفحهٔ لیست فقط CSS کافی است
                wp_enqueue_style('jbg-like', JBG_ADS_URL.'assets/css/jbg-like.css', [], '0.2.0');
                return;
            }
        }
        if (!$need) return;

        // CSS/JS
        wp_enqueue_style('jbg-like', JBG_ADS_URL.'assets/css/jbg-like.css', [], '0.2.0');
        wp_enqueue_script('jbg-like', JBG_ADS_URL.'assets/js/jbg-like.js', [], '0.2.0', true);

        // دادهٔ اولیه
        $likeCount    = (int) get_post_meta($cur_id, 'jbg_like_count', true);
        $dislikeCount = (int) get_post_meta($cur_id, 'jbg_dislike_count', true);

        $reaction = 'none';
        if (is_user_logged_in()) {
            $u = get_current_user_id();
            $reactions = get_user_meta($u, 'jbg_reactions', true);
            if (is_array($reactions) && isset($reactions[$cur_id])) {
                $reaction = ($reactions[$cur_id] === 'dislike') ? 'dislike' : 'like';
            } else {
                // سازگاری: اگر در liked_ids بود
                $liked_ids = (array) get_user_meta($u, 'jbg_liked_ids', true);
                $liked_ids = array_map('intval', $liked_ids);
                if (in_array($cur_id, $liked_ids, true)) $reaction = 'like';
            }
        }

        wp_localize_script('jbg-like', 'JBG_REACT', [
            'rest'         => rest_url('jbg/v1/reaction'),
            'nonce'        => wp_create_nonce('wp_rest'),
            'adId'         => $cur_id,
            'logged'       => is_user_logged_in(),
            'reaction'     => $reaction,
            'likeCount'    => $likeCount,
            'dislikeCount' => $dislikeCount,
        ]);
    }

    // کنار عنوان همان صفحهٔ تکی
    public static function inject_inline_into_single_title($title, $post_id) {
        if (!is_singular('jbg_ad')) return $title;
        if ((int)$post_id !== (int)get_queried_object_id()) return $title;

        $ui =
          '<span id="jbg-react-inline" class="jbg-react-inline" dir="ltr" aria-label="React">'.
            '<button type="button" class="jbg-react-btn up"    aria-pressed="false" title="پسندیدم">'.
              '<svg viewBox="0 0 24 24" width="16" height="16" class="icon"><path d="M2 21h4V9H2v12zM22 9c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L13 0 6.59 6.41C6.21 6.78 6 7.3 6 7.83V19c0 1.1.9 2 2 2h9c.82 0 1.54-.5 1.84-1.22l3-7c.11-.23.16-.48.16-.74V9z"/></svg>'.
              '<span class="cnt like">0</span>'.
            '</button>'.
            '<button type="button" class="jbg-react-btn down"  aria-pressed="false" title="نپسندیدم">'.
              '<svg viewBox="0 0 24 24" width="16" height="16" class="icon"><path d="M22 3h-4v12h4V3zM2 15c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L11 24l6.41-6.41c.38-.37.59-.89.59-1.42V5c0-1.1-.9-2-2-2H7c-.82 0-1.54.5-1.84 1.22l-3 7c-.11.23-.16.48-.16.74V15z"/></svg>'.
              '<span class="cnt dislike">0</span>'.
            '</button>'.
          '</span>';

        return $title . ' ' . $ui;
    }
}
