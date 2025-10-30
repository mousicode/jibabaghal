<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

class ViewBadge {
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

    public static function register(): void {
        add_filter('the_content', [self::class, 'inject'], 7);
    }

    public static function inject($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $id     = get_the_ID();
        $views  = self::views_count((int)$id);
        $brandN = wp_get_post_terms($id, 'jbg_brand', ['fields'=>'names']);
        $brand  = (!is_wp_error($brandN) && !empty($brandN)) ? $brandN[0] : '';
        $viewsF = self::compact_views($views) . ' بازدید';
        $when   = self::relative_time($id);
        $like   = do_shortcode('[posts_like_dislike id=' . $id . ']');

        // استایل ساده و یکسان برای همه‌ی عرض‌ها: عنوان تمام‌عرض و کامل، سپس متا، سپس اکشن‌ها
$style = '<style id="jbg-single-header-css">
  /* پنهان‌کردن تیترهای پیش‌فرض قالب */
  .single-jbg_ad header.wd-single-post-header,
  .single-jbg_ad h1.wd-entities-title,
  .single-jbg_ad .entry-title,
  .single-jbg_ad h1.entry-title,
  .single-jbg_ad .post-title,
  .single-jbg_ad .elementor-heading-title{display:none!important;}
  .single-jbg_ad .jbg-status,.single-jbg_ad .jbg-watched,.single-jbg_ad .watched{display:none!important;}

  /* کانتینر هدر زیر پلیر */
  .jbg-player-wrapper .jbg-single-header{width:100%;margin:10px 0 0;padding:0;direction:rtl}

  /* سه بلاک ساده */
  .jbg-single-header .hdr-title{margin:0 0 6px 0}
  .jbg-single-header .hdr-title h1{
    margin:0;
    max-width:100%;
    white-space:normal !important;
    overflow:visible !important;
    text-overflow:clip !important;
    word-break:break-word;
    font-size:22px;
    line-height:1.5;
    font-weight:800;
    color:#111827;
  }
  .jbg-single-header .hdr-meta{
    display:flex; gap:8px; align-items:center;
    color:#374151; font-size:14px; margin:0 0 8px 0
  }
  .jbg-single-header .dot{opacity:.55}
  .jbg-single-header .hdr-actions{display:flex; gap:10px; align-items:center; justify-content:flex-start}
  .jbg-single-header .brand{background:#f1f5f9;color:#111827;border:1px solid #e5e7eb;border-radius:999px;padding:3px 10px;font-weight:600}
</style>';

        // مارک‌آپ ساده و پایدار
        $title  = '<div class="hdr-title"><h1 class="title">'.esc_html(get_the_title($id)).'</h1></div>';
        $meta   = '<div class="hdr-meta"><span>'.esc_html($viewsF).'</span><span class="dot">•</span><span>'.esc_html($when).'</span></div>';
        $acts   = '<div class="hdr-actions"><span class="ext-like">'.$like.'</span>'.($brand ? '<span class="brand">'.esc_html($brand).'</span>' : '').'</div>';
        $header = '<div class="jbg-single-header">'.$title.$meta.$acts.'</div>';

        // انتقال هدر به داخل wrapper پلیر تا هم‌عرض باشد
        $script = '<script id="jbg-single-header-move">(function(){
          function move(){try{
            var w=document.querySelector(".jbg-player-wrapper");
            var h=document.querySelector(".jbg-single-header");
            if(w&&h&&!w.contains(h)) w.appendChild(h);
          }catch(e){}}
          if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",move);}else{move();}
        })();</script>';

        static $once=false;
        if(!$once){ $content = $style . $content; $once=true; }
        return $header . $script . $content;
    }
}
