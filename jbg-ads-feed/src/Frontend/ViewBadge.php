<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

class ViewBadge
{
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

    /** امتیاز: اگر شورت‌کد ثبت است، اجرا؛ وگرنه از متا بخوان */
    private static function score_badge(int $post_id): string {
        if (function_exists('shortcode_exists') && shortcode_exists('jbg_points_badge')) {
            $out = do_shortcode('[jbg_points_badge id="'.intval($post_id).'"]');
            // اگر خروجی واقعاً رندر شد
            if (is_string($out) && strpos($out, '[') === false) {
                return '<span class="score-badge">'.$out.'</span>';
            }
        }
        // fallback از متاهای رایج
        $score = get_post_meta($post_id, 'jbg_score', true);
        if ($score === '') $score = get_post_meta($post_id, 'points', true);
        if ($score === '') $score = get_post_meta($post_id, 'jbg_points', true);
        if ($score === '' || $score === null) return '';
        $score = is_numeric($score) ? (int)$score : wp_strip_all_tags((string)$score);
        return '<span class="score-badge badge">'.$score.' امتیاز</span>';
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

        // لایک/دیس‌لایک از افزونهٔ جدید
        $like_shortcode = do_shortcode('[posts_like_dislike id=' . $id . ']');

        // CSS چیدمان و مخفی‌سازی‌های لازم
        $style = '<style id="jbg-single-header-css">
          .single-jbg_ad header.wd-single-post-header,
          .single-jbg_ad h1.wd-entities-title,
          .single-jbg_ad .entry-title,
          .single-jbg_ad h1.entry-title,
          .single-jbg_ad .post-title,
          .single-jbg_ad .elementor-heading-title{display:none!important;}
          .single-jbg_ad .jbg-status,
          .single-jbg_ad .jbg-watched,
          .single-jbg_ad .watched{display:none!important;}

          .jbg-player-wrapper .jbg-single-header{width:100%;margin:10px 0 0;padding:0;direction:rtl}
          .jbg-single-header .row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
          .jbg-single-header .col-right{display:flex;flex-direction:column;gap:6px;min-width:40%}
          .jbg-single-header .col-left{display:flex;align-items:center;gap:10px;flex-wrap:wrap;min-width:40%;justify-content:flex-end}
          .jbg-single-header .title{margin:0;font-size:24px;line-height:1.35;font-weight:800;color:#111827}
          .jbg-single-header .sub{display:flex;gap:8px;align-items:center;color:#374151;font-size:14px}
          .jbg-single-header .dot{opacity:.55}
          .jbg-single-header .brand{background:#f1f5f9;color:#111827;border:1px solid #e5e7eb;border-radius:999px;padding:3px 10px;font-weight:600;white-space:nowrap}
          .jbg-single-header .score-badge .badge,
          .jbg-single-header .score-badge{background:#eef2ff;border:1px solid #e5e7eb;border-radius:999px;padding:3px 10px;font-weight:700;color:#1f2937;white-space:nowrap}
          .jbg-single-header .ext-like{display:inline-flex;align-items:center;gap:6px}
          @media (max-width:640px){
            .jbg-single-header .title{font-size:18px}
            .jbg-single-header .sub{font-size:12.5px}
            .jbg-single-header .col-left,.jbg-single-header .col-right{min-width:100%}
          }
        </style>';

        // راست: عنوان + بازدید و زمان
        $right  = '<div class="col-right">';
        $right .=   '<h1 class="title">'.esc_html(get_the_title($id)).'</h1>';
        $right .=   '<div class="sub"><span>'.esc_html($viewsF).'</span><span class="dot">•</span><span>'.esc_html($when).'</span></div>';
        $right .= '</div>';

        // چپ: لایک/دیس‌لایک + برند + امتیاز
        $left   = '<div class="col-left">';
        $left  .=   '<span class="ext-like">'.$like_shortcode.'</span>';
        if ($brand) $left .= '<span class="brand">'.esc_html($brand).'</span>';
        $left  .=   self::score_badge($id);
        $left  .= '</div>';

        $header = '<div class="jbg-single-header"><div class="row">'.$right.$left.'</div></div>';

        // انتقال زیر پلیر
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
