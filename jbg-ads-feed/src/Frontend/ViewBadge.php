<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

/**
 * ViewBadge: سربرگ سبک برای صفحه تک آگهی
 * - مخفی‌سازی تیترهای پیش‌فرض قالب
 * - نمایش عنوان، برند، بازدید و زمان انتشار
 * - اطمینان از قرار گرفتن هدر «زیر پلیر» با جابه‌جایی DOM در فرانت‌اند
 */
class ViewBadge
{
    /** فرمت‌کردن بازدید به صورت فشرده */
    private static function compact_views(int $n): string {
        if ($n >= 1000000000) { $v = $n/1000000000; $u = ' میلیارد'; }
        elseif ($n >= 1000000){ $v = $n/1000000;    $u = ' میلیون';  }
        elseif ($n >= 1000)   { $v = $n/1000;       $u = ' هزار';    }
        else return (string) $n;
        $v = floor($v*10)/10;
        return rtrim(rtrim(number_format($v,1,'.',''), '0'), '.') . $u;
    }

    /** زمان نسبی انتشار */
    private static function relative_time(int $post_id): string {
        $t = get_post_time('U', true, $post_id);
        $d = time() - (int) $t;
        if ($d < 60)        return 'لحظاتی پیش';
        if ($d < 3600)      return floor($d/60) . ' دقیقه پیش';
        if ($d < 86400)     return floor($d/3600) . ' ساعت پیش';
        if ($d < 86400*30)  return floor($d/86400) . ' روز پیش';
        return get_the_date('', $post_id);
    }

    /** شمارش بازدید با fallback به جدول سفارشی درصورت نبودن متا */
    private static function views_count(int $ad_id): int {
        $meta = (int) get_post_meta($ad_id, 'jbg_views_count', true);
        if ($meta > 0) return $meta;

        global $wpdb;
        $table = $wpdb->prefix . 'jbg_views';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) return 0;

        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE ad_id = %d", $ad_id));
        update_post_meta($ad_id, 'jbg_views_count', $count);
        wp_cache_delete($ad_id, 'post_meta');
        return $count;
    }

    /** رجیستر */
    public static function register(): void {
        // بعد از پلیر (که معمولاً با اولویت ~5 تزریق می‌شود)
        add_filter('the_content', [self::class, 'inject'], 7);
    }

    /** درج هدر سبک در محتوای صفحه تک آگهی */
    public static function inject($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $id     = get_the_ID();
        $views  = self::views_count((int) $id);
        $brandN = wp_get_post_terms($id, 'jbg_brand', ['fields' => 'names']);
        $brand  = (!is_wp_error($brandN) && !empty($brandN)) ? $brandN[0] : '';
        $viewsF = self::compact_views($views) . ' بازدید';
        $when   = self::relative_time($id);

        // CSS لازم
        $style = '<style id="jbg-single-header-css">
            /* عنوان‌های پیش‌فرض قالب را پنهان کن */
            .single-jbg_ad .entry-title,
            .single-jbg_ad h1.entry-title,
            .single-jbg_ad .post-title,
            .single-jbg_ad .elementor-heading-title{display:none !important;}

            /* هدر داخلی؛ داخل .jbg-player-wrapper قرار می‌گیرد */
            .jbg-player-wrapper .jbg-single-header{
                width:100%;margin:10px 0 0;padding:0;box-sizing:border-box;direction:rtl;text-align:right;
            }
            .jbg-single-header .jbg-headrow{display:flex;align-items:baseline;gap:12px}
            .jbg-single-header .jbg-single-title{margin:0;font-size:24px;line-height:1.35;font-weight:800;color:#111827}
            .jbg-single-header .jbg-single-meta{margin-inline-start:auto;display:flex;gap:8px;align-items:center;font-size:14px;color:#374151;flex-wrap:nowrap}
            .jbg-single-header .brand{background:#f1f5f9;color:#111827;border:1px solid #e5e7eb;border-radius:999px;padding:3px 10px;font-weight:600;white-space:nowrap}
            .jbg-single-header .dot{opacity:.55}
            @media (max-width:640px){
                .jbg-single-header .jbg-headrow{flex-direction:column;align-items:flex-end;gap:6px}
                .jbg-single-header .jbg-single-title{font-size:16px}
                .jbg-single-header .jbg-single-meta{font-size:12.5px;margin-inline-start:0;flex-wrap:wrap;justify-content:flex-start}
            }
        </style>';

        // مارک‌آپ هدر
        $header  = '<div class="jbg-single-header">';
        $header .=   '<div class="jbg-headrow">';
        $header .=     '<h1 class="jbg-single-title">'.esc_html(get_the_title($id)).'</h1>';
        $header .=     '<div class="jbg-single-meta">';
        if ($brand) $header .= '<span class="brand">'.esc_html($brand).'</span><span class="dot">•</span>';
        $header .=       '<span>'.esc_html($viewsF).'</span><span class="dot">•</span><span>'.esc_html($when).'</span>';
        $header .=     '</div>';
        $header .=   '</div>';
        $header .= '</div>';

        // اسکریپت کوچک: اگر هدر خارج از رپر پلیر بود، به انتهای آن منتقلش کن
        // این فقط DOM را جابه‌جا می‌کند؛ هیچ منطق بک‌اندی تغییر نمی‌کند.
        $script = '<script id="jbg-single-header-move">
        (function(){
          function move(){try{
            var h=document.querySelector(".jbg-single-header");
            var w=document.querySelector(".jbg-player-wrapper");
            if(h&&w&&!w.contains(h)){ w.appendChild(h); }
          }catch(_){}} 
          if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",move);}else{move();}
        })();
        </script>';

        // یک‌بار CSS را تزریق کن
        static $once = false;
        if (!$once) { $content = $style . $content; $once = true; }

        // هدر را قبل از برگرداندن محتوا اضافه می‌کنیم؛
        // اسکریپت جابه‌جایی تضمین می‌کند در نهایت زیر پلیر بنشیند.
        return $header . $script . $content;
    }
}
