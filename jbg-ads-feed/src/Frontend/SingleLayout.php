<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * Single jbg_ad layout:
 * - ستون اصلی: Player (prio=5) → ViewBadge (prio=7) → Quiz (prio=8) → Related
 * - بدون سایدبار؛ همه‌چیز زیر هم قرار می‌گیرد.
 */
class SingleLayout {

    public static function register(): void {
        // قبل از Player (priority=5) تا wrapper بیرونی آماده باشد
        add_filter('the_content', [self::class, 'wrap'], 4);
    }

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        // CSS سبک (یک‌بار درج می‌شود)
        $style = '<style id="jbg-single-stack-css">
          .single-jbg_ad .jbg-main-stack{display:block; direction:rtl;}
          .single-jbg_ad .jbg-main-stack .jbg-section{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-top:16px}
          /* Related styles (قبلی‌ها را نگه داشتیم) */
          .jbg-related{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;}
          .jbg-related-title{font-weight:800;margin:0 0 8px 0;font-size:16px;color:#111827;}
          .jbg-related-list{display:flex;flex-direction:column;gap:10px;max-height:80vh;overflow:auto;}
          .jbg-related-item{display:flex;gap:10px;text-decoration:none;border-radius:10px;padding:8px;align-items:center;border:1px solid transparent}
          .jbg-related-item:hover{background:#f8fafc;border-color:#e5e7eb}
          .jbg-related-thumb{width:110px;height:62px;background:#e5e7eb;background-size:cover;background-position:center;border-radius:8px;flex:none}
          .jbg-related-meta{display:flex;flex-direction:column;gap:4px;min-width:0}
          .jbg-related-title-text{font-size:14px;font-weight:700;color:#111827;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
          .jbg-related-sub{font-size:12px;color:#4b5563;display:flex;gap:6px;align-items:center;flex-wrap:wrap}
          .jbg-related-sub .brand{background:#f1f5f9;border:1px solid #e5e7eb;border-radius:999px;padding:2px 8px;font-weight:600}
          .jbg-related-sub .dot{opacity:.55}
        </style>';

        // خروجی مرتبط‌ها (همان شورت‌کد قبلی)
        $related = do_shortcode('[jbg_related limit="10"]');

        // ستون اصلی: محتوا (که شامل Player/Badge/Quiz می‌شود) سپس Related
        $html  = '<div class="jbg-main-stack">';
        $html .=   $content;                // Player@5 → Badge@7 → Quiz@8 در همین content تزریق می‌شوند
        $html .=   '<div class="jbg-section">'.$related.'</div>'; // Related زیر آزمون
        $html .= '</div>';

        static $once=false;
        if (!$once) { $html = $style . $html; $once=true; }

        return $html;
    }
}
