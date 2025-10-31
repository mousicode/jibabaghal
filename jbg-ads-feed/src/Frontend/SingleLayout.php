<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

use JBG\Ads\Progress\Access;

class SingleLayout {

    public static function register(): void {
        // 1) جلوی تزریق قدیمی ViewBadge + استایل خارجی را بگیر
        add_action('wp', [self::class, 'detach_viewbadge_and_css'], 1);

        // 2) گارد دسترسی
        add_action('template_redirect', [self::class, 'guard'], 1);

        // 3) خروجی صفحه
        add_filter('the_content', [self::class, 'wrap'], 4);
    }

    public static function detach_viewbadge_and_css(): void {
        if (!is_singular('jbg_ad')) return;

        // غیرفعال‌سازی خروجی ViewBadge
        if (class_exists('\\JBG\\Ads\\Frontend\\ViewBadge')) {
            $GLOBALS['JBG_DISABLE_VIEWBADGE'] = true;
            remove_filter('the_content', ['\\JBG\\Ads\\Frontend\\ViewBadge','inject'], 7);
        }

        // حذف CSS خارجی که ممکن است override کند
        add_action('wp_enqueue_scripts', function () {
            wp_dequeue_style('jbg-video-header');
            wp_deregister_style('jbg-video-header');
        }, 999);
    }

    public static function guard(): void {
        if (!is_singular('jbg_ad')) return;
        $user_id = get_current_user_id();
        $ad_id   = (int) get_queried_object_id();
        if (!Access::is_unlocked($user_id, $ad_id)) {
            remove_all_filters('the_content', 5);
            $GLOBALS['JBG_LOCKED_AD'] = true;
        }
    }

    // ===== helpers (from ViewBadge) =====
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
        if ($d < 60) return 'لحظاتی پیش';
        if ($d < 3600) return floor($d/60) . ' دقیقه پیش';
        if ($d < 86400) return floor($d/3600) . ' ساعت پیش';
        if ($d < 86400*30) return floor($d/86400) . ' روز پیش';
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
    // ====================================

    private static function build_header(int $post_id): string {
        $views  = self::views_count($post_id);
        $brandN = wp_get_post_terms($post_id, 'jbg_brand', ['fields' => 'names']);
        $brand  = (!is_wp_error($brandN) && !empty($brandN)) ? $brandN[0] : '';
        $viewsF = self::compact_views($views) . ' بازدید';
        $when   = self::relative_time($post_id);
        $like   = do_shortcode('[posts_like_dislike id='.$post_id.']');

        $css = '<style id="jbg-single-header-inline">
/* مخفی‌سازی هدرهای قالب */
.single-jbg_ad header.wd-single-post-header,
.single-jbg_ad h1.wd-entities-title,
.single-jbg_ad .entry-title,
.single-jbg_ad h1.entry-title,
.single-jbg_ad .post-title,
.single-jbg_ad .elementor-heading-title{display:none!important;}
.single-jbg_ad .jbg-status,.single-jbg_ad .jbg-watched,.single-jbg_ad .watched{display:none!important;}

/* هدر: ردیفی حتی در موبایل */
.jbg-single-header{
  direction:rtl;width:100%;margin:12px 0 0;padding:14px 16px;background:#fff;
  border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04);
  box-sizing:border-box;display:flex;align-items:center;gap:10px;
  flex-direction:row !important; /* اجباری */
  flex-wrap:wrap !important;
}
.jbg-single-header .hdr-meta,
.jbg-single-header .hdr-actions{
  display:flex;align-items:center;gap:8px;margin:0;
  white-space:nowrap;flex-wrap:nowrap;order:1;flex:0 0 auto !important;
}
/* عنوان: یک ردیف کامل زیر اکشن‌ها در موبایل */
.jbg-single-header .hdr-title{margin:0;order:2;flex:1 1 100% !important}
.jbg-single-header .hdr-title h1{
  margin:0;font-size:20px;line-height:1.6;font-weight:800;color:#0f172a;
  word-break:break-word;white-space:normal!important;overflow:visible!important;text-overflow:clip!important;
}
/* چیپ برند */
.jbg-single-header .chip{
  display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 12px;
  background:#f8fafc;color:#111827;border:1px solid #e5e7eb;border-radius:999px;font-size:13px;font-weight:600;line-height:1
}
.jbg-single-header .chip.brand{background:#eef2ff}
/* لایک/دیس‌لایک بدون پس‌زمینه */
.jbg-single-header .hdr-actions .ext-like{background:transparent;border:none;padding:0;height:auto}
.jbg-single-header .hdr-actions .ext-like>*{margin:0}
/* کارت‌ها + نشان امتیاز */
.single-jbg_ad .jbg-main-stack{display:block;direction:rtl;}
.single-jbg_ad .jbg-main-stack .jbg-section{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-top:16px}
.jbg-locked{background:#fff;border:1px dashed #9ca3af;border-radius:12px;padding:18px;margin-top:16px;color:#374151}
.jbg-locked .title{font-weight:800;margin-bottom:8px}
.jbg-locked .note{font-size:13px;color:#6b7280}
.jbg-points-badge{display:inline-flex;align-items:center;gap:6px;margin-inline-start:8px;
  padding:2px 8px;border-radius:9999px;background:#EEF2FF;color:#3730A3;font-weight:700;font-size:12px;border:1px solid #E0E7FF}
.jbg-points-badge .pt-val{font-weight:800}
/* دسکتاپ */
@media (min-width:768px){
  .jbg-single-header{padding:16px 18px;gap:12px}
  .jbg-single-header .hdr-title{order:0;flex:1 1 auto !important}
  .jbg-single-header .hdr-title h1{font-size:22px}
}
</style>';

        $html  = $css;
        $html .= '<div class="jbg-single-header">';
        $html .=   '<div class="hdr-meta"><span>'.esc_html($viewsF).'</span><span>•</span><span>'.esc_html($when).'</span></div>';
        $html .=   '<div class="hdr-actions"><span class="ext-like">'.$like.'</span>'
                .   ($brand ? '<span class="chip brand">'.esc_html($brand).'</span>' : '')
                .   '</div>';
        $html .=   '<div class="hdr-title"><h1 class="title">'.esc_html(get_the_title($post_id)).'</h1></div>';
        $html .= '</div>';

        return $html;
    }

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $user_id = get_current_user_id();
        $ad_id   = get_the_ID();
        $is_open = Access::is_unlocked($user_id, $ad_id);
        $points  = (int) get_post_meta($ad_id, 'jbg_points', true);

        $out = '<div class="jbg-main-stack">';

        if ($is_open) {
            // پلیر
            $out .= $content;
            // هدر یکپارچه
            $out .= self::build_header($ad_id);

            // نشان امتیاز کنار عنوان
            if ($points > 0) {
                $out .= '<span id="jbg-points-badge" class="jbg-points-badge"><span class="pt-val">'.
                        esc_html($points).'</span> امتیاز</span>';
                $out .= '<script>(function(){try{
                    var b=document.getElementById("jbg-points-badge"); if(!b) return;
                    var t=document.querySelector(".jbg-single-header h1"); if(t){ t.insertAdjacentElement("beforeend", b); }
                }catch(e){}})();</script>';
            }

            // آزمون
            $quiz = do_shortcode('[jbg_quiz]');
            if (trim($quiz)!=='') $out .= '<div class="jbg-section">'.$quiz.'</div>';

            // مرتبط‌ها
            $rel = do_shortcode('[jbg_related limit="10"]');
            if (trim($rel)!=='') $out .= '<div class="jbg-section">'.$rel.'</div>';

        } else {
            // پیام قفل + مرتبط‌ها
            $seq = Access::seq($ad_id);
            $out .= '<div class="jbg-locked"><div class="title">این ویدیو هنوز برای شما باز نیست</div>'
                  . '<div class="note">برای دسترسی، ابتدا ویدیوی مرحلهٔ <strong>'.esc_html(max(1,$seq-1)).'</strong> را کامل ببینید و آزمونش را درست پاسخ دهید.'
                  . ($user_id>0 ? '' : ' (لطفاً ابتدا وارد شوید)') . '</div></div>';

            $rel = do_shortcode('[jbg_related limit="10"]');
            if (trim($rel)!=='') $out .= '<div class="jbg-section">'.$rel.'</div>';
        }

        $out .= '</div>';
        return $out;
    }
}
