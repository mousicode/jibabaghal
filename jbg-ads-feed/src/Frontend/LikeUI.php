<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * UI لایک: لود CSS/JS و تزریق کنار عنوان با JS (بدون وابستگی به the_title)
 */
class LikeUI {

    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void {
        $need = false;
        $cur_id = 0;

        // صفحه تکی آگهی
        if (is_singular('jbg_ad')) {
            $need = true;
            $cur_id = (int) get_queried_object_id();
        } else {
            // صفحاتی که شورت‌کد کارت‌ها را دارند
            $post = get_post();
            if ($post && has_shortcode((string) $post->post_content, 'jbg_ads')) {
                $need = true;
            }
        }

        if (!$need) return;

        // CSS/JS
        wp_enqueue_style('jbg-like', JBG_ADS_URL . 'assets/css/jbg-like.css', [], '0.1.0');
        wp_enqueue_script('jbg-like', JBG_ADS_URL . 'assets/js/jbg-like.js', [], '0.1.8', true);

        // داده برای JS
        $liked_ids = [];
        if (is_user_logged_in()) {
            $u = get_current_user_id();
            $liked_ids = (array) get_user_meta($u, 'jbg_liked_ids', true);
            $liked_ids = array_map('intval', $liked_ids);
        }
        $cur_count = $cur_id ? (int) get_post_meta($cur_id, 'jbg_like_count', true) : 0;

        wp_localize_script('jbg-like', 'JBG_LIKE', [
            // اگر بعداً به Reaction سوئیچ شد، اینجا را به rest_url('jbg/v1/reaction') تغییر می‌دهیم
            'rest'         => rest_url('jbg/v1/like'),
            'nonce'        => wp_create_nonce('wp_rest'),
            'currentId'    => $cur_id,
            'currentCount' => $cur_count,
            'liked'        => $liked_ids, // آرایهٔ آگهی‌های لایک‌شدهٔ کاربر
            // سلکتورهای مقاوم برای یافتن عنوان
            'selectors'    => [
                '.jbg-single-header .jbg-title',
                '.jbg-single-header h1',
                '.entry-title',
                'h1[itemprop="headline"]',
                '.jbg-title',
                'h1'
            ],
        ]);

        // کمی CSS inline برای حالت کنار عنوان
        $inline = '
        .jbg-like-inline{display:inline-flex;gap:6px;align-items:center;margin-inline-start:8px;vertical-align:middle}
        .jbg-like-inline .jbg-like-btn{appearance:none;border:1px solid #e5e7eb;border-radius:9999px;background:#fff;padding:2px 8px;line-height:1.2;font-size:13px;cursor:pointer}
        .jbg-like-inline .jbg-like-btn.is-on{background:#fee2e2;border-color:#fecaca}
        .jbg-like-inline .jbg-like-count{font-size:12px;color:#6b7280}
        ';
        wp_add_inline_style('jbg-like', $inline);
    }

    /**
     * اگر در کارت‌ها خواستی استفاده کنی (مثلاً داخل ListShortcode)
     */
    public static function small_anchor(int $post_id, string $class = 'jbg-like-inline'): string {
        $count = (int) get_post_meta($post_id, 'jbg_like_count', true);
        return '<span class="'.esc_attr($class).'" data-jbg-like-id="'.esc_attr($post_id).'">'
             .    '<button type="button" class="jbg-like-btn" aria-label="پسندیدن">❤</button>'
             .    '<span class="jbg-like-count">'.esc_html($count).'</span>'
             . '</span>';
    }
}
