<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * Two-column layout for single jbg_ad
 * Left: related sidebar  |  Right: main player/content
 * فقط چیدمان؛ هیچ رفتار Player/Badge/Quiz تغییر نمی‌کند.
 */
class SingleLayout {

    public static function register(): void {
        // نکتهٔ مهم: بعد از همهٔ فیلترهایی که محتوا را تزریق می‌کنند اجرا شود
        // (Player با priority≈5، ViewBadge≈7، Quiz≈8)
        add_filter('the_content', [self::class, 'wrap'], 99);
    }

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        // اگر قبلاً wrap شده، دوباره انجام نده
        if (strpos($content, 'jbg-two-col') !== false) return $content;

        // CSS: ستون اول چپ (LTR)، داخل ستون‌ها RTL
        $style = '<style id="jbg-single-2col-css">
            .single-jbg_ad .jbg-two-col{
              direction:ltr; display:grid; grid-template-columns:1fr; gap:24px; align-items:start;
            }
            @media(min-width:768px){
              .single-jbg_ad .jbg-two-col{ grid-template-columns: 360px 1fr; }
            }
            .single-jbg_ad .jbg-col-aside,.single-jbg_ad .jbg-col-main{ direction:rtl; }
            @media(min-width:768px){
              .single-jbg_ad .jbg-col-aside{ position:sticky; top:24px; }
              body.admin-bar .single-jbg_ad .jbg-col-aside{ top:56px; }
            }
            .jbg-related{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:12px;}
            .jbg-related-title{font-weight:800;margin:4px 0 10px;font-size:16px;color:#111827}
            .jbg-related-list{display:flex;flex-direction:column;gap:10px;max-height:78vh;overflow:auto;padding-right:2px}
            .jbg-related-item{display:flex;gap:10px;text-decoration:none;border-radius:12px;padding:8px;align-items:center;border:1px solid transparent}
            .jbg-related-item:hover{background:#f8fafc;border-color:#e5e7eb}
            .jbg-related-thumb{width:120px;height:68px;background:#e5e7eb;background-size:cover;background-position:center;border-radius:10px;flex:none}
            .jbg-related-meta{display:flex;flex-direction:column;gap:4px;min-width:0}
            .jbg-related-title-text{font-size:14px;font-weight:700;color:#111827;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
            .jbg-related-sub{font-size:12px;color:#4b5563;display:flex;gap:6px;align-items:center;flex-wrap:wrap}
            .jbg-related-sub .brand{background:#f1f5f9;border:1px solid #e5e7eb;border-radius:999px;padding:2px 8px;font-weight:600}
            .jbg-related-sub .dot{opacity:.55}
        </style>';

        // سایدبار: شورت‌کد مرتبط‌ها
        $related = do_shortcode('[jbg_related limit="10" title="ویدیوهای مرتبط"]');

        // DOM: سایدبار اول (چپ)، سپس Main (راست). چون container LTR است.
        $html  = '<div class="jbg-two-col">';
        $html .=   '<aside class="jbg-col-aside">'.$related.'</aside>';
        $html .=   '<main class="jbg-col-main">'.$content.'</main>';
        $html .= '</div>';

        // استایل را فقط یک‌بار تزریق کن
        static $once = false;
        if (!$once) { $html = $style . $html; $once = true; }

        return $html;
    }
}
