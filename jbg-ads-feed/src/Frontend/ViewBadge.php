<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

class ViewBadge {

    // --- helpers ---
    private static function compact_views(int $n): string {
        if ($n >= 1000000000) { $v=$n/1000000000; $u=' میلیارد'; }
        elseif ($n >= 1000000){ $v=$n/1000000;    $u=' میلیون'; }
        elseif ($n >= 1000)   { $v=$n/1000;       $u=' هزار'; }
        else return (string)$n;
        $v = floor($v*10)/10;
        return rtrim(rtrim(number_format($v,1,'.',''), '0'), '.') . $u;
    }

    private static function relative_time(int $post_id): string {
        $t = get_post_time('U', true, $post_id);
        $d = time() - (int)$t;
        if ($d < 60)        return 'لحظاتی پیش';
        if ($d < 3600)      return floor($d/60) . ' دقیقه پیش';
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

    // --- wiring ---
    public static function register(): void {
        // تزریق نزدیک به پلیر و قبل از سایر فیلترهای نمایشی
        add_filter('the_content', [self::class, 'inject'], 7); // :contentReference[oaicite:2]{index=2}
    }

    // --- main render ---
    public static function inject($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $id     = get_the_ID();
        $views  = self::views_count((int)$id);
        $brandN = wp_get_post_terms($id, 'jbg_brand', ['fields'=>'names']);
        $brand  = (!is_wp_error($brandN) && !empty($brandN)) ? $brandN[0] : '';
        $viewsF = self::compact_views($views) . ' بازدید';
        $when   = self::relative_time($id);
        $like   = do_shortcode('[posts_like_dislike id=' . $id . ']');

        // 1) CSS: موبایل ستونی، دسکتاپ ردیفی
        // پیش‌فرض = ستونی. از 992px به بالا = ردیفی.
        $style = '<style id="jbg-single-header-css">
/* حذف هدرهای قالب که با هدر سفارشی تداخل دارند */
.single-jbg_ad header.wd-single-post-header,
.single-jbg_ad h1.wd-entities-title,
.single-jbg_ad .entry-title,
.single-jbg_ad h1.entry-title,
.single-jbg_ad .post-title,
.single-jbg_ad .elementor-heading-title{display:none!important;}
.single-jbg_ad .jbg-status,.single-jbg_ad .jbg-watched,.single-jbg_ad .watched{display:none!important;}

/* ظرف هدر زیر پلیر. موبایل: ستونی */
.jbg-single-header{
  direction:rtl; width:100%;
  margin:12px 0 0; padding:14px 16px;
  background:#fff; border:1px solid #e5e7eb; border-radius:12px;
  box-shadow:0 1px 2px rgba(0,0,0,.04);
  display:flex; flex-direction:column; gap:10px;
  box-sizing:border-box;
}

/* عنوان */
.jbg-single-header .hdr-title{margin:0}
.jbg-single-header .hdr-title .title{
  margin:0; font-size:20px; line-height:1.6; font-weight:800; color:#0f172a;
  word-break:break-word; white-space:normal!important; overflow:visible!important; text-overflow:clip!important;
}

/* متا */
.jbg-single-header .hdr-meta{
  display:flex; align-items:center; gap:8px; margin:0; font-size:13px; color:#6b7280;
}
.jbg-single-header .hdr-meta .dot{opacity:.6}

/* اکشن‌ها */
.jbg-single-header .hdr-actions{
  display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:0;
}
.jbg-single-header .hdr-actions > *{
  display:inline-flex; align-items:center; gap:6px; height:32px; padding:0 12px;
  background:#f8fafc; color:#111827; border:1px solid #e5e7eb; border-radius:999px;
  font-size:13px; font-weight:600; line-height:1;
}
.jbg-single-header .brand{ background:#eef2ff; border-color:#e5e7eb; }

/* دسکتاپ ≥992px: چیدمان ردیفی و هم‌تراز با پلیر */
@media (min-width:992px){
  .jbg-single-header{ padding:16px 18px; gap:12px; flex-direction:row !important; align-items:center; justify-content:space-between; flex-wrap:wrap; }
  .jbg-single-header .hdr-title{ flex:1 1 auto }
  .jbg-single-header .hdr-title .title{ font-size:22px }
}

/* اطمینان از قرارگیری درست زیر پلیر */
.jbg-player-wrapper > .jbg-single-header{ margin-top:12px }
</style>';

        // 2) مارک‌آپ هدر
        $title  = '<div class="hdr-title"><h1 class="title">'.esc_html(get_the_title($id)).'</h1></div>';
        $meta   = '<div class="hdr-meta"><span>'.esc_html($viewsF).'</span><span class="dot">•</span><span>'.esc_html($when).'</span></div>';
        $acts   = '<div class="hdr-actions"><span class="ext-like">'.$like.'</span>'.($brand ? '<span class="brand">'.esc_html($brand).'</span>' : '').'</div>';
        $header = '<div class="jbg-single-header">'.$title.$meta.$acts.'</div>';

        // 3) درج هدر بلافاصله بعد از باکس پلیر
        // ساختار پلیر: .jbg-player-wrapper در محتوا تزریق می‌شود :contentReference[oaicite:3]{index=3}
        $script = '<script>(function(){
  function place(){
    try{
      var w = document.querySelector(".jbg-player-wrapper");
      var h = document.querySelector(".jbg-single-header");
      if(!w || !h) return;
      if(h.parentNode!==w.parentNode){
        if(w.nextSibling){ w.parentNode.insertBefore(h, w.nextSibling); }
        else { w.parentNode.appendChild(h); }
      }
    }catch(e){}
  }
  if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",place);}
  else{place();}
})();</script>';

        static $once=false;
        if(!$once){ $content = $style.$content; $once=true; }

        return $header.$script.$content;
    }
}
