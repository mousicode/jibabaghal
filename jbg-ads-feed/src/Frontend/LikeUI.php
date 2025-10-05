<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * UI لایک: بارگذاری CSS/JS، مقداردهی JBG_LIKE، و تزریق مارک‌آپ کنار عنوان
 * - فقط در نمای تکی jbg_ad
 * - تزریق فقط یک‌بار و فقط در لوپ اصلی/کوئری اصلی (جلوگیری از نمایش در breadcrumb/SEO)
 */
class LikeUI {

    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_filter('the_title', [self::class, 'inject_into_title'], 20, 2);
    }

    public static function enqueue(): void {
        if (!is_singular('jbg_ad')) return;

        // استایل و اسکریپت
        wp_enqueue_style('jbg-like', JBG_ADS_URL . 'assets/css/jbg-like.css', [], '0.1.0');
        wp_enqueue_script('jbg-like', JBG_ADS_URL . 'assets/js/jbg-like.js', [], '0.1.3', true);

        $ad_id = (int) get_queried_object_id();

        // وضعیت اولیه
        $liked = is_user_logged_in() ? (bool) get_user_meta(get_current_user_id(), 'jbg_liked_'.$ad_id, true) : false;
        $count = (int) get_post_meta($ad_id, 'jbg_like_count', true);

        // مقداردهی که با JS فعلی سازگار است (rest.toggle / rest.status)
        wp_localize_script('jbg-like', 'JBG_LIKE', [
            'rest'   => [
                'toggle' => rest_url('jbg/v1/like/toggle'),
                'status' => rest_url('jbg/v1/like/status'),
            ],
            'nonce'  => wp_create_nonce('wp_rest'),
            'adId'   => $ad_id,
            'liked'  => $liked ? 1 : 0,
            'count'  => $count,
            'logged' => is_user_logged_in() ? 1 : 0,
        ]);
    }

    /** تزریق کنار عنوان (فقط در لوپ اصلی/کوئری اصلی؛ یک‌بار مصرف) */
    public static function inject_into_title($title, $post_id) {
        if (is_admin()) return $title;
        if (get_post_type($post_id) !== 'jbg_ad') return $title;
        if (!is_singular('jbg_ad')) return $title;

        // فقط لوپ اصلی و همان پست جاری
        if (!in_the_loop() || !is_main_query()) return $title;
        if ((int) get_queried_object_id() !== (int) $post_id) return $title;

        // یک‌بار مصرف: بعد از تزریق حذف شود تا جاهای دیگر اعمال نشود
        remove_filter('the_title', [self::class, 'inject_into_title'], 20);

        $count = (int) get_post_meta($post_id, 'jbg_like_count', true);

        // مارک‌آپ مطابق jbg-like.js (id/class ها را تغییر نده)
        $html  = '<span id="jbg-like-inline" class="jbg-like-inline" data-ad="'.esc_attr($post_id).'">'
               . '  <button type="button" class="jbg-like-btn" aria-label="Like">'
               . '    <svg class="heart" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 21s-6.7-4.35-9.33-7C.5 11.82.5 8.5 2.67 6.33a4.67 4.67 0 016.6 0L12 9.05l2.73-2.72a4.67 4.67 0 016.6 0C23.5 8.5 23.5 11.82 21.33 14c-2.63 2.65-9.33 7-9.33 7z"/></svg>'
               . '  </button>'
               . '  <span class="jbg-like-count">'.esc_html(number_format_i18n($count)).'</span>'
               . '</span>';

        return $title . ' ' . $html;
    }
}
