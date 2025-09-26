<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * UI لایک: لود CSS/JS + تزریق (فیلتر) کنار عنوان + رندر کمکى برای کارت‌ها
 */
class LikeUI {

    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_filter('the_title', [self::class, 'inject_inline_title'], 20, 2);
    }

    public static function enqueue(): void {
        // فقط وقتی نیاز داریم
        $need   = false;
        $cur_id = 0;

        if (is_singular('jbg_ad')) {
            $need   = true;
            $cur_id = (int) get_queried_object_id();
        } else {
            $post = get_post();
            if ($post && has_shortcode((string)$post->post_content, 'jbg_ads')) {
                $need = true;
            }
        }
        if (!$need) return;

        // CSS/JS
        wp_enqueue_style(
            'jbg-like',
            JBG_ADS_URL . 'assets/css/jbg-like.css',
            [],
            '0.1.2'
        );
        wp_enqueue_script(
            'jbg-like',
            JBG_ADS_URL . 'assets/js/jbg-like.js',
            [],
            '0.1.7',
            true
        );

        // داده برای JS
        $liked_ids = [];
        if (is_user_logged_in()) {
            $u = get_current_user_id();
            $liked_ids = (array) get_user_meta($u, 'jbg_liked_ids', true);
            $liked_ids = array_map('intval', $liked_ids);
        }
        $cur_count = $cur_id ? (int) get_post_meta($cur_id, 'jbg_like_count', true) : 0;

        wp_localize_script('jbg-like', 'JBG_LIKE', [
            'rest'         => rest_url('jbg/v1/like'),
            'nonce'        => wp_create_nonce('wp_rest'),
            'liked'        => $liked_ids,
            'currentId'    => $cur_id,
            'currentCount' => $cur_count,
        ]);

        // کمی CSS inline برای حالت کنار عنوان
        $inline = '
        .jbg-like-inline{display:inline-flex;gap:6px;align-items:center;margin-inline-start:8px;vertical-align:middle}
        .jbg-like-inline .jbg-like-btn{appearance:none;border:1px solid #e5e7eb;border-radius:9999px;background:#fff;padding:2px 8px;line-height:1.2;font-size:13px;cursor:pointer}
        .jbg-like-inline .jbg-like-btn.is-on{background:#fee2e2;border-color:#fecaca;color:#b91c1c}
        .jbg-like-inline .jbg-like-count{font-size:12px;color:#6b7280}
        ';
        wp_add_inline_style('jbg-like', $inline);
    }

    /** تزریق کنار عنوان با فیلتر (اگر قالب از the_title استفاده کند) */
    public static function inject_inline_title($title, $post_id) {
        if (is_admin()) return $title;
        if (get_post_type($post_id) !== 'jbg_ad') return $title;
        if (!is_singular('jbg_ad')) return $title;
        if (!in_the_loop() || !is_main_query()) return $title;

        return $title . self::inline_anchor((int)$post_id);
    }

    /** مارک‌آپ کوچک کنار عنوان/کارت */
    public static function inline_anchor(int $post_id): string {
        $count = (int) get_post_meta($post_id, 'jbg_like_count', true);
        $is_on = false;
        if (is_user_logged_in()) {
            $u = get_current_user_id();
            $liked = (array) get_user_meta($u, 'jbg_liked_ids', true);
            $is_on = in_array($post_id, array_map('intval', $liked), true);
        }
        $on = $is_on ? ' is-on' : '';
        return '<span class="jbg-like-inline" data-jbg-like-id="'.esc_attr($post_id).'">'
             .    '<button type="button" class="jbg-like-btn'.$on.'" aria-label="پسندیدن">❤</button>'
             .    '<span class="jbg-like-count">'.esc_html($count).'</span>'
             . '</span>';
    }

    /** اگر در کارت‌ها خواستی استفاده کنی */
    public static function small_anchor(int $post_id, string $class = 'jbg-like-inline'): string {
        return self::inline_anchor($post_id);
    }
}
