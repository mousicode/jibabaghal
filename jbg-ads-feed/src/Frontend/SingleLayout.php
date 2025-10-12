<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

use JBG\Ads\Progress\Access;

class SingleLayout {

    public static function register(): void {
        // گارد قبل از رندر
        add_action('template_redirect', [self::class, 'guard'], 1);
        // نکته مهم: پرایوریتی را ۹۹ گذاشتیم تا آخرین فیلتر باشیم و همه‌چیز (از جمله پلیر) داخل رَپر بیاید
        add_filter('the_content', [self::class, 'wrap'], 99);
    }

    public static function guard(): void {
        if (!is_singular('jbg_ad')) return;
        $user_id = get_current_user_id();
        $ad_id   = (int) get_queried_object_id();
        if (!Access::is_unlocked($user_id, $ad_id)) {
            // هر فیلتر the_content با priority=5 که معمولاً پلیر inject می‌کند را بردار
            remove_all_filters('the_content', 5);
            $GLOBALS['JBG_LOCKED_AD'] = true;
        }
    }

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $user_id = get_current_user_id();
        $ad_id   = get_the_ID();
        $is_open = Access::is_unlocked($user_id, $ad_id);
        $points  = (int) get_post_meta($ad_id, 'jbg_points', true); // امتیاز تعریف‌شده برای این ویدیو

        // استایل‌ها: کانتینر 1312px + فول‌بلید + اوورراید المنتور + فallback برای پلیر
        $style = '<style id="jbg-single-stack-css">
          :root{ --jbg-content-width:1312px; --jbg-content-pad:16px; }

          /* فول‌بلید + کانتینر استاندارد سایت */
          .jbg-full-bleed{position:relative;left:50%;right:50%;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);width:100vw}
          .jbg-container{max-width:var(--jbg-content-width);margin:0 auto;padding-left:var(--jbg-content-pad);padding-right:var(--jbg-content-pad);box-sizing:border-box}

          /* اوورراید ظرف المنتور در صفحهٔ تکی آگهی */
          .single-jbg_ad .elementor-section.elementor-section-boxed > .elementor-container{
            max-width:var(--jbg-content-width) !important;
          }

          /* فallback: اگر پلیر بیرون از رَپر بود، خودش constrain شود */
          .single-jbg_ad .jbg-player-wrapper{
            max-width:var(--jbg-content-width) !important;
            margin-left:auto !important;
            margin-right:auto !important;
            padding-left:var(--jbg-content-pad) !important;
            padding-right:var(--jbg-content-pad) !important;
            box-sizing:border-box !important;
            width:100% !important;
          }

          .single-jbg_ad .jbg-main-stack{display:block;direction:rtl;}
          .single-jbg_ad .jbg-main-stack .jbg-section{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-top:16px}

          .jbg-locked{background:#fff;border:1px dashed #9ca3af;border-radius:12px;padding:18px;margin-top:16px;color:#374151}
          .jbg-locked .title{font-weight:800;margin-bottom:8px}
          .jbg-locked .note{font-size:13px;color:#6b7280}

          /* نشان امتیاز کنار عنوان */
          .jbg-points-badge{display:inline-flex;align-items:center;gap:6px;margin-inline-start:8px;
            padding:2px 8px;border-radius:9999px;background:#EEF2FF;color:#3730A3;font-weight:700;font-size:12px;border:1px solid #E0E7FF}
          .jbg-points-badge .pt-val{font-weight:800}
        </style>';

        // رَپر عرض استاندارد (همه‌چیز را می‌پیچیم، شامل پلیر که حالا قبل‌تر inject شده)
        $out  = $style;
        $out .= '<div class="jbg-full-bleed"><div class="jbg-container">';
        $out .= '<div class="jbg-main-stack">';

        if ($is_open) {
            // محتوای اصلی (شامل پلیر/هدر که دیگر فیلترها قبل از ما تزریق کرده‌اند)
            $out .= $content;

            // اگر امتیازی تعریف شده، نشان امتیاز را به کنار عنوان تزریق کن
            if ($points > 0) {
                $badge = '<span id="jbg-points-badge" class="jbg-points-badge" data-points="'.esc_attr($points).'"><span class="pt-val">'.
                         esc_html($points).'</span> امتیاز</span>';
                $out .= $badge;
                $out .= '<script>(function(){try{
                  var b=document.getElementById("jbg-points-badge"); if(!b) return;
                  var sels=[".jbg-single-header h1",".jbg-single-header .title",".entry-title",".jbg-title",".single-title","h1"];
                  var t=null; for(var i=0;i<sels.length;i++){ t=document.querySelector(sels[i]); if(t) break; }
                  if(t){ t.insertAdjacentElement("beforeend", b); }
                }catch(e){}})();</script>';
            }

            // آزمون
            $quiz_html = do_shortcode('[jbg_quiz]');
            if (trim($quiz_html)!=='') $out .= '<div class="jbg-section">'.$quiz_html.'</div>';

            // مرتبط‌ها
            $rel_html = do_shortcode('[jbg_related limit="10"]');
            if (trim($rel_html)!=='') $out .= '<div class="jbg-section">'.$rel_html.'</div>';
        } else {
            // پیام قفل
            $seq = Access::seq($ad_id);
            $out .= '<div class="jbg-locked"><div class="title">این ویدیو هنوز برای شما باز نیست</div>'
                  . '<div class="note">برای دسترسی، ابتدا ویدیوی مرحلهٔ <strong>'.esc_html(max(1,$seq-1)).'</strong> را کامل ببینید و آزمونش را درست پاسخ دهید.'
                  . ($user_id>0 ? '' : ' (لطفاً ابتدا وارد شوید)')
                  . '</div></div>';

            // مرتبط‌ها
            $rel_html = do_shortcode('[jbg_related limit="10"]');
            if (trim($rel_html)!=='') $out .= '<div class="jbg-section">'.$rel_html.'</div>';
        }

        $out .= '</div>'; // .jbg-main-stack
        $out .= '</div></div>'; // .jbg-container .jbg-full-bleed

        return $out;
    }
}
