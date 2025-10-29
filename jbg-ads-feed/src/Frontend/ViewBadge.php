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

        // تمام CSS اینجا
        $style = '<style id="jbg-single-header-css">
          /* مخفی‌سازی تیترهای پیش‌فرض قالب و watched% */
          .single-jbg_ad header.wd-single-post-header,
          .single-jbg_ad h1.wd-entities-title,
          .single-jbg_ad .entry-title,
          .single-jbg_ad h1.entry-title,
          .single-jbg_ad .post-title,
          .single-jbg_ad .elementor-heading-title{display:none!important;}
          .single-jbg_ad .jbg-status,.single-jbg_ad .jbg-watched,.single-jbg_ad .watched{display:none!important;}

          /* هدر سفارشی زیر پلیر */
          .jbg-player-wrapper .jbg-single-header{width:100%;margin:10px 0 0;padding:0;direction:rtl}
          .jbg-single-header .row{display:flex;align-items:center;gap:12px;flex-wrap:nowrap;justify-content:space-between;width:100%}

          /* راست: عنوان + متا در یک ردیف (دسکتاپ) */
          .jbg-single-header .col-right{display:flex;align-items:center;gap:10px;flex:1 1 auto;min-width:0}
          .jbg-single-header .title{
            margin:0;max-width:100%;
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
            font-size:24px;line-height:1.35;font-weight:800;color:#111827;
          }
          .jbg-single-header .sub{display:flex;gap:8px;align-items:center;color:#374151;font-size:14px;white-space:nowrap}
          .jbg-single-header .dot{opacity:.55}

          /* چپ: لایک/دیس‌لایک + برند */
          .jbg-single-header .col-left{display:flex;align-items:center;gap:10px;flex:0 0 auto;min-width:0;justify-content:flex-end;margin-inline-start:auto}
          .jbg-single-header .ext-like{display:inline-flex;align-items:center;gap:6px}
          .jbg-single-header .brand{background:#f1f5f9;color:#111827;border:1px solid #e5e7eb;border-radius:999px;padding:3px 10px;font-weight:600;white-space:nowrap}

          /* موبایل: عنوان تمام‌عرض و چندخطی، سپس متا، سپس لایک/برند */
          @media (max-width:640px){
            .jbg-single-header .row{flex-direction:column;align-items:stretch;gap:6px}
            .jbg-single-header .col-right{order:1;width:100%;justify-content:flex-start;min-width:0}
            .jbg-single-header .title{font-size:18px;white-space:normal;overflow:visible;text-overflow:clip;line-height:1.4;width:100%}
            .jbg-single-header .sub{font-size:12.5px;margin-top:2px;white-space:nowrap}
            .jbg-single-header .col-left{order:3;width:100%;justify-content:flex-start;gap:10px}
          }
        </style>';

        $right  = '<div class="col-right">'
                . '<h1 class="title">'.esc_html(get_the_title($id)).'</h1>'
                . '<div class="sub"><span>'.esc_html($viewsF).'</span><span class="dot">•</span><span>'.esc_html($when).'</span></div>'
                . '</div>';

        $left   = '<div class="col-left"><span class="ext-like">'.$like.'</span>'
                . ($brand ? '<span class="brand">'.esc_html($brand).'</span>' : '')
                . '</div>';

        $header = '<div class="jbg-single-header"><div class="row">'.$right.$left.'</div></div>';

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
