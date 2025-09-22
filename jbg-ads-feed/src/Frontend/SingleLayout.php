<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

/**
 * Wrap single jbg_ad content into a two-column layout:
 * - Right (main): player + view badge + quiz (همان خروجی فعلی)
 * - Left (aside): related videos by current category via [jbg_related]
 *
 * هیچ رفتار قبلی را حذف نمی‌کند؛ فقط محتوا را در گرید می‌چیند.
 */
class SingleLayout {

    public static function register(): void {
        // قبل از Player (که با priority=5 تزریق می‌شود) اجرا شود تا wrapper بیرونی ساخته شود
        add_filter('the_content', [self::class, 'wrap'], 4);
    }

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        // استایل‌های سبک و self-contained
        $style = '<style id="jbg-single-2col-css">
            .single-jbg_ad .jbg-two-col{display:grid;grid-template-columns:1fr;gap:24px;align-items:start; direction:rtl;}
            @media(min-width:992px){
              .single-jbg_ad .jbg-two-col{grid-template-columns: 320px 1fr;} /* چپ: سایدبار، راست: ویدیو */
            }
            .single-jbg_ad .jbg-col-aside{order:2;}
            .single-jbg_ad .jbg-col-main{order:1;}
            @media(min-width:992px){
              .single-jbg_ad .jbg-col-aside{order:1;}
              .single-jbg_ad .jbg-col-main{order:2;}
            }
            /* آیتم‌های related */
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

        // سایدبار مرتبط‌ها (از شورت‌کد خودمان)
        $related = do_shortcode('[jbg_related limit="10"]');

        // محتوا (Player + Badge + Quiz) سمت راست
        $html  = '<div class="jbg-two-col">';
        $html .=   '<aside class="jbg-col-aside">'.$related.'</aside>';
        $html .=   '<main class="jbg-col-main">'.$content.'</main>';
        $html .= '</div>';

        static $once=false;
        if (!$once) { $html = $style . $html; $once=true; }

        return $html;
    }
}
