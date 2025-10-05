<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * UI لایک: لود CSS/JS + تزریق (فقط یک‌بار و فقط در عنوان نمای تکی) + مارک‌آپ کوچک
 * - سازگار با ساختار فعلی JBG_LIKE (adId/count/liked/rest/selectors)
 * - جلوگیری از تزریق در breadcrumb/SEO title/ویجت‌ها با چک in_the_loop/is_main_query و remove_filter
 */
class LikeUI {

    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        // فقط اگر قالب از the_title برای عنوان اصلی استفاده کند، کنار عنوان تزریق می‌کنیم
        add_filter('the_title', [self::class, 'inject_inline_title'], 20, 2);
    }

    public static function enqueue(): void {
        // فقط در صفحهٔ تکی آگهی
        if (!is_singular('jbg_ad')) return;

        // استایل/اسکریپت
        wp_enqueue_style('jbg-like', JBG_ADS_URL . 'assets/css/jbg-like.css', [], '0.1.0');
        // توجه: نسخه را مطابق ریپو نگه می‌داریم تا کش به‌هم نخورد
        wp_enqueue_script('jbg-like', JBG_ADS_URL . 'assets/js/jbg-like.js', [], '0.1.3', true);

        $post_id = (int) get_queried_object_id();
        // شمارش لایک فعلی
        // توجه: در برخی کمیت‌ها کلید jbg_like_count است؛ اگر جایی jbg_likes_count بود، بنا بر ریپو شمارندهٔ اصلی jbg_like_count در مارک‌آپ استفاده می‌شود.
        $count   = (int) get_post_meta($post_id, 'jbg_like_count', true);

        // آیا کاربر فعلی قبلاً لایک کرده؟
        $liked = false;
        if (is_user_logged_in()) {
            // پیاده‌سازی فعلی شما: نگه‌داری وضعیت کاربر با کلید per-ad یا آرایه liked_ids (سازگار با هر دو)
            $liked = (bool) get_user_meta(get_current_user_id(), 'jbg_liked_' . $post_id, true);
            if (!$liked) {
                $liked_ids = (array) get_user_meta(get_current_user_id(), 'jbg_liked_ids', true);
                $liked = in_array($post_id, array_map('intval', $liked_ids), true);
            }
        } else {
            // fallback مهمان‌ها: کوکی ساده
            $cookie_key = 'jbg_liked_' . $post_id;
            if (!empty($_COOKIE[$cookie_key]) && $_COOKIE[$cookie_key] === '1') $liked = true;
        }

        // سلکتورهای محتمل عنوان (طبق نسخهٔ فعلی ریپو)
        $selectors = [
            '.single-jbg_ad .entry-title',
            '.single-jbg_ad h1.entry-title',
            '.single-jbg_ad h1[itemprop="headline"]',
            '.single-jbg_ad h1',
            'h1.entry-title'
        ];

        // داده برای JS (سازگار با ریپو)
        wp_localize_script('jbg-like', 'JBG_LIKE', [
            'adId'      => $post_id,
            'count'     => $count,
            'liked'     => $liked ? 1 : 0,
            // مسیر REST فعلی شما (toggle)
            'rest'      => rest_url('jbg/v1/like'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'selectors' => $selectors,
        ]);

        // کمی CSS inline برای حالت کنار عنوان (بدون تغییر ساختار اصلی)
        $inline = '
        .jbg-like-inline{display:inline-flex;gap:6px;align-items:center;margin-inline-start:8px;vertical-align:middle}
        .jbg-like-inline .jbg-like-btn{appearance:none;border:1px solid #e5e7eb;border-radius:9999px;background:#fff;padding:2px 8px;line-height:1.2;font-size:13px;cursor:pointer}
        .jbg-like-inline .jbg-like-btn.is-on{background:#fee2e2;border-color:#fecaca;color:#b91c1c}
        .jbg-like-inline .jbg-like-count{font-size:12px;color:#6b7280}
        ';
        wp_add_inline_style('jbg-like', $inline);
    }

    /** تزریق کنار عنوان با فیلتر (فقط یک‌بار، فقط در لوپ اصلیِ کوئری اصلیِ نمای تکی) */
    public static function inject_inline_title($title, $post_id) {
        // جلوگیری از تزریق در ادمین/پست‌تایپ دیگر/نمای غیرتکی
        if (is_admin()) return $title;
        if (get_post_type($post_id) !== 'jbg_ad') return $title;
        if (!is_singular('jbg_ad')) return $title;

        // فقط برای لوپ اصلیِ کوئری اصلی (تا روی breadcrumb/SEO/ویجت‌ها تاثیر نگذارد)
        if (!in_the_loop() || !is_main_query()) return $title;

        // فقط همان پست جاری
        if ((int) get_queried_object_id() !== (int) $post_id) return $title;

        // یک‌بار مصرف: بعد از تزریق، فیلتر را برمی‌داریم تا دوباره در کانتکست دیگری اعمال نشود
        remove_filter('the_title', [self::class, 'inject_inline_title'], 20);

        // مارک‌آپ قلب + شمارنده (سازگار با پیاده‌سازی فعلی)
        $anchor = self::inline_anchor((int)$post_id);

        // عنوان + UI کنار هم
        return $title . $anchor;
    }

    /** مارک‌آپ کوچک کنار عنوان/کارت (سازگار با داده‌های فعلی) */
    public static function inline_anchor(int $post_id): string {
        $count = (int) get_post_meta($post_id, 'jbg_like_count', true);

        $is_on = false;
        if (is_user_logged_in()) {
            $u = get_current_user_id();
            $liked = (array) get_user_meta($u, 'jbg_liked_ids', true);
            $is_on = in_array($post_id, array_map('intval', $liked), true);

            // اگر مدل per-ad نیز استفاده شده باشد، آن را هم لحاظ می‌کنیم
            if (!$is_on) {
                $is_on = (bool) get_user_meta($u, 'jbg_liked_' . $post_id, true);
            }
        } else {
            $cookie_key = 'jbg_liked_' . $post_id;
            if (!empty($_COOKIE[$cookie_key]) && $_COOKIE[$cookie_key] === '1') $is_on = true;
        }

        $on = $is_on ? ' is-on' : '';

        return '<span class="jbg-like-inline" data-jbg-like-id="'.esc_attr($post_id).'">'
             .    '<button type="button" class="jbg-like-btn'.$on.'" aria-label="پسندیدن">❤</button>'
             .    '<span class="jbg-like-count">'.esc_html($count).'</span>'
             . '</span>';
    }

    /** نسخهٔ کوچک برای استفاده در کارت‌ها (در حال حاضر همان مارک‌آپ اصلی را برمی‌گرداند) */
    public static function small_anchor(int $post_id, string $class = 'jbg-like-inline'): string {
        return self::inline_anchor($post_id);
    }
}
