<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

class ViewBadge {
    private static function compact_views(int $n): string {
        if ($n >= 1000000000) { $v = $n / 1000000000; $u = ' میلیارد'; }
        elseif ($n >= 1000000) { $v = $n / 1000000;    $u = ' میلیون'; }
        elseif ($n >= 1000)    { $v = $n / 1000;       $u = ' هزار'; }
        else return (string)$n;
        $v = floor($v * 10) / 10;
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.') . $u;
    }

    private static function relative_time(int $post_id): string {
        $t = get_post_time('U', true, $post_id);
        $d = time() - (int)$t;
        if ($d < 60)        return 'لحظاتی پیش';
        if ($d < 3600)      return floor($d/60) . ' دقیقه پیش'; // ← رفع ایراد: تایپ اشتباه ($د) به ($d)
        if ($d < 86400)     return floor($d/3600) . ' ساعت پیش';
        if ($d < 86400*30)  return floor($d/86400) . ' روز پیش';
        return get_the_date('', $post_id);
    }

    private static function views_count(int $ad_id): int {
        $meta = (int) get_post_meta($ad_id, 'jbg_views_count', true);
        if ($meta > 0) return $meta;
        global $wpdb;
        $table  = $wpdb->prefix . 'jbg_views';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) return 0;
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE ad_id=%d", $ad_id));
        update_post_meta($ad_id, 'jbg_views_count', $count);
        wp_cache_delete($ad_id, 'post_meta');
        return $count;
    }

    public static function register(): void {
        add_filter('the_content', [self::class, 'inject'], 7);
    }

    public static function inject($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $id     = get_the_ID();
        $views  = self::views_count((int) $id);
        $brandN = wp_get_post_terms($id, 'jbg_brand', ['fields' => 'names']);
        $brand  = (!is_wp_error($brandN) && !empty($brandN)) ? $brandN[0] : '';
        $viewsF = self::compact_views($views) . ' بازدید';
        $when   = self::relative_time($id);
        $like   = do_shortcode('[posts_like_dislike id=' . $id . ']');

        /* ─────────────────────────────────────────────────────────────────────────
         * CSS: حذف پس‌زمینهٔ ext-like/brand و «مرکزچین عمودی» همهٔ آیتم‌ها
         * نکته: برخی پلاگین‌های لایک، عناصر داخلی مثل <p> یا .pld-like-dislike-wrap
         *       با margin/height ناهم‌تراز می‌سازند؛ در اینجا ریست/هم‌تراز می‌کنیم.
         * ───────────────────────────────────────────────────────────────────────── */
        $style = '<style id="jbg-single-header-css">
.single-jbg_ad header.wd-single-post-header,
.single-jbg_ad h1.wd-entities-title,
.single-jbg_ad .entry-title,
.single-jbg_ad h1.entry-title,
.single-jbg_ad .post-title,
.single-jbg_ad .elementor-heading-title{display:none!important;}
.single-jbg_ad .jbg-status,.single-jbg_ad .jbg-watched,.single-jbg_ad .watched{display:none!important;}

.jbg-player-wrapper + .jbg-single-header,
.jbg-player-wrapper .jbg-single-header{
  direction:rtl; width:100%; margin:12px 0 0; padding:14px 16px;
  background:#fff; border:1px solid #e5e7eb; border-radius:12px;
  box-shadow:0 1px 2px rgba(0,0,0,.04); box-sizing:border-box;
  display:flex; gap:10px; flex-direction:column!important; align-items:stretch;
}

/* عنوان */
.jbg-single-header .hdr-title{margin:0}
.jbg-single-header .hdr-title h1{
  margin:0; font-size:20px; line-height:1.6; font-weight:800; color:#0f172a;
  word-break:break-word; white-space:normal!important; overflow:visible!important; text-overflow:clip!important;
}

/* متا */
.jbg-single-header .hdr-meta{
  display:flex; align-items:center; gap:8px; font-size:13px; color:#6b7280; margin:0;
}

/* اکشن‌ها */
.jbg-single-header .hdr-actions{
  display:flex; align-items:center; justify-content:flex-start; gap:8px; flex-wrap:wrap; margin:0;
}

/* آیتم‌های اکشن (بدون پس‌زمینه/بوردر و هم‌تراز وسط) */
.jbg-single-header .hdr-actions > *{
  display:inline-flex; align-items:center; justify-content:center;
  gap:6px; height:32px; padding:0 10px; line-height:1;
  background:transparent!important; border:none!important; border-radius:999px;
  color:#111827; font-size:13px; font-weight:600; vertical-align:middle;
}

/* ریست داخلی پلاگین لایک: تراز عمودی کامل */
.jbg-single-header .hdr-actions .ext-like{ display:flex; align-items:center; gap:8px; }
.jbg-single-header .hdr-actions .ext-like > p{ margin:0!important; line-height:1!important; } /* ← حذف فاصلهٔ ناخواستهٔ <p> */
.jbg-single-header .hdr-actions .pld-like-dislike-wrap,
.jbg-single-header .hdr-actions .pld-custom,
.jbg-single-header .hdr-actions .pld-common-wrap{
  display:flex!important; align-items:center!important; gap:6px; height:32px;
}
.jbg-single-header .hdr-actions .pld-common-wrap a,
.jbg-single-header .hdr-actions .pld-common-wrap button{
  display:inline-flex; align-items:center; justify-content:center; height:28px; line-height:28px; padding:0 8px;
}
.jbg-single-header .hdr-actions svg,
.jbg-single-header .hdr-actions i{ vertical-align:middle; }

/* برند شفاف و تراز وسط */
.jbg-single-header .hdr-actions .brand{
  background:transparent!important; border:none!important; color:#4f46e5; display:inline-flex; align-items:center;
}

/* دسکتاپ */
@media (min-width:768px){
  .jbg-single-header{
    padding:16px 18px; gap:12px; flex-direction:row!important; align-items:center; flex-wrap:wrap; justify-content:space-between;
  }
  .jbg-single-header .hdr-title{flex:1 1 auto}
  .jbg-single-header .hdr-meta{order:2}
  .jbg-single-header .hdr-actions{order:3; align-items:center;}
  .jbg-single-header .hdr-title h1{font-size:22px}
}
</style>';

        $title = '<div class="hdr-title"><h1 class="title">' . esc_html(get_the_title($id)) . '</h1></div>';
        $meta  = '<div class="hdr-meta"><span>' . esc_html($viewsF) . '</span><span class="dot">•</span><span>' . esc_html($when) . '</span></div>';
        $acts  = '<div class="hdr-actions"><span class="ext-like">' . $like . '</span>' . ($brand ? '<span class="brand">' . esc_html($brand) . '</span>' : '') . '</div>';
        $header = '<div class="jbg-single-header">' . $title . $meta . $acts . '</div>';

        // انتقال هدر به بعد از پلیر
        $script = '<script id="jbg-single-header-move">(function(){
  function move(){
    try{
      var w = document.querySelector(".jbg-player-wrapper");
      var h = document.querySelector(".jbg-single-header");
      if(!w || !h) return;
      var p = w.parentNode;
      if(p && h.parentNode !== p){
        if(w.nextSibling){ p.insertBefore(h, w.nextSibling); }
        else { p.appendChild(h); }
      }
    }catch(e){}
  }
  if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",move);}
  else{move();}
})();</script>';

        static $once = false;
        if (!$once) { $content = $style . $content; $once = true; }
        return $header . $script . $content;
    }
}
