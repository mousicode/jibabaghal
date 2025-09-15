<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

class ViewBadge {

    /** نمایش عدد به‌صورت فشرده (هزار/میلیون/میلیارد) */
    private static function compact_views(int $n): string {
        if ($n >= 1000000000) { $num = $n / 1000000000; $u = ' میلیارد'; }
        elseif ($n >= 1000000){ $num = $n / 1000000;    $u = ' میلیون'; }
        elseif ($n >= 1000)   { $num = $n / 1000;       $u = ' هزار'; }
        else return number_format_i18n($n);

        $s = number_format_i18n($num, 1);
        // حذف .0 فارسی/انگلیسی
        $s = preg_replace('/([0-9۰-۹]+)[\.\,٫]0$/u', '$1', $s);
        return $s . $u;
    }

    /** زمان نسبی انتشار */
    private static function relative_time(int $post_id): string {
        return trim(human_time_diff(get_the_time('U', $post_id), current_time('timestamp'))) . ' پیش';
    }

    /**
     * تعداد بازدید آگهی:
     * 1) ابتدا از متای jbg_views_count
     * 2) اگر صفر/خالی بود، از جدول لاگ‌ها می‌شمارد و متا را سینک می‌کند
     */
    private static function views_count(int $ad_id): int {
        $ad_id = absint($ad_id);
        if ($ad_id <= 0) return 0;

        // 1) از متا (کلید درست)
        $v = (int) get_post_meta($ad_id, 'jbg_views_count', true);
        if ($v > 0) return $v;

        // 2) fallback: شمارش از جدول jbg_views
        global $wpdb;
        $table = $wpdb->prefix . 'jbg_views';
        // اگر جدول هنوز ساخته نشده بود، count را 0 برگردان
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($table_exists !== $table) return 0;

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE ad_id = %d",
            $ad_id
        ));

        // سینک متا برای دفعات بعد
        update_post_meta($ad_id, 'jbg_views_count', $count);
        // پاک کردن کش متای پست تا مقدار جدید فوراً دیده شود
        wp_cache_delete($ad_id, 'post_meta');

        return $count;
    }

    public static function register(): void {
        add_filter('the_content', [self::class, 'inject'], 7);
    }

    public static function inject($content) {
        // فقط روی صفحهٔ تک آگهی و در لوپ اصلی
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $id     = get_the_ID();
        $views  = self::views_count((int) $id); // ← کلید درست + fallback
        $brandN = wp_get_post_terms($id, 'jbg_brand', ['fields' => 'names']);
        $brand  = (!is_wp_error($brandN) && !empty($brandN)) ? $brandN[0] : '';
        $viewsF = self::compact_views($views) . ' بازدید';
        $when   = self::relative_time($id);

        // استایل هدر (یک‌بار درج می‌شود)
        $style = '<style id="jbg-single-header-css">
            /* عنوان‌های پیشفرض تم را مخفی کن */
            .single-jbg_ad .entry-title,
            .single-jbg_ad h1.entry-title,
            .single-jbg_ad .post-title,
            .single-jbg_ad .elementor-heading-title{display:none !important;}

            /* هدر داخل .jbg-player-wrapper قرار می‌گیرد؛ full-width داخلی کافی‌ست */
            .jbg-player-wrapper .jbg-single-header{
                width:100%;
                margin:10px 0 0;
                padding:0;
                box-sizing:border-box;
                direction:rtl;
                text-align:right;
            }

            /* دسکتاپ: عنوان راست، متا چپ در همان خط */
            .jbg-single-header .jbg-headrow{ display:flex; align-items:baseline; gap:12px; }
            .jbg-single-header .jbg-single-title{ margin:0; font-size:24px; line-height:1.35; font-weight:800; color:#111827; }
            .jbg-single-header .jbg-single-meta{ margin-inline-start:auto; display:flex; gap:8px; align-items:center; font-size:14px; color:#374151; flex-wrap:nowrap; }
            .jbg-single-header .brand{ background:#f1f5f9; color:#111827; border:1px solid #e5e7eb; border-radius:999px; padding:3px 10px; font-weight:600; white-space:nowrap; }
            .jbg-single-header .dot{ opacity:.55; }

            /* موبایل: عمودی (اول عنوان، زیرش متا) */
            @media (max-width:640px){
              .jbg-single-header .jbg-headrow{ flex-direction:column; align-items:flex-end; gap:6px; }
              .jbg-single-header .jbg-single-title{ font-size:16px; }
              .jbg-single-header .jbg-single-meta{ font-size:12.5px; margin-inline-start:0; flex-wrap:wrap; justify-content:flex-start; }
            }
        </style>';

        $html  = '<div class="jbg-single-header">';
        $html .=   '<div class="jbg-headrow">';
        $html .=     '<h1 class="jbg-single-title">'.esc_html(get_the_title($id)).'</h1>';
        $html .=     '<div class="jbg-single-meta">';
        if ($brand) $html .= '<span class="brand">'.esc_html($brand).'</span><span class="dot">•</span>';
        $html .=       '<span>'.esc_html($viewsF).'</span><span class="dot">•</span><span>'.esc_html($when).'</span>';
        $html .=     '</div>';
        $html .=   '</div>';
        $html .= '</div>';

        static $once = false;
        if (!$once) { $content = $style . $content; $once = true; }

        // هدر را بعد از پلیر درج می‌کنیم (JS پلیر آن را داخل wrapper می‌برد تا هم‌عرض شود)
        return $content . $html;
    }
}
