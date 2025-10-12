<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

use JBG\Ads\Progress\Access;

class SingleLayout {

    public static function register(): void {
        add_action('template_redirect', [self::class, 'guard'], 1);
        // عمداً 99 تا آخرین فیلتر باشیم
        add_filter('the_content', [self::class, 'wrap'], 99);
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

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) return $content;

        $user_id = get_current_user_id();
        $ad_id   = get_the_ID();
        $is_open = Access::is_unlocked($user_id, $ad_id);
        $points  = (int) get_post_meta($ad_id, 'jbg_points', true);

        // استایل‌ها: کانتینر 1312px + اوورراید المنتور + فول‌بلید برای پلیر
        $style = '<style id="jbg-single-stack-css">
          :root{ --jbg-content-width:1312px; --jbg-content-pad:16px; }

          /* ظرف‌های عمومی افزونه */
          .jbg-grid,.jbg-related-grid,.jbg-points-wrap,.jbg-wallet,.jbg-sponsor-report,.jbg-ad-layout{
            max-width:var(--jbg-content-width) !important;
            margin-left:auto !important;margin-right:auto !important;
            padding-left:var(--jbg-content-pad) !important;padding-right:var(--jbg-content-pad) !important;
            box-sizing:border-box !important;width:100% !important;
          }

          /* ظرف عمومی صفحه */
          .jbg-full-bleed{position:relative;left:50%;right:50%;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);width:100vw}
          .jbg-container{max-width:var(--jbg-content-width);margin:0 auto;padding-left:var(--jbg-content-pad);padding-right:var(--jbg-content-pad);box-sizing:border-box}

          /* ظرف المنتور در صفحه تکی */
          .single-jbg_ad .elementor-section.elementor-section-boxed > .elementor-container{
            max-width:var(--jbg-content-width) !important;
          }

          /* پلیر: فول‌بلید در هر عمقی داخل page-content */
          .single-jbg_ad .page-content .jbg-player-wrapper{
            position:relative;width:100vw;
            left:50%; right:50%;
            margin-left:calc(50% - 50vw); margin-right:calc(50% - 50vw);
            padding-left:calc((100vw - var(--jbg-content-width)) / 2);
            padding-right:calc((100vw - var(--jbg-content-width)) / 2);
            box-sizing:border-box !important;
          }
          /* اطمینان از پهنای 100% برای Plyr و ویدیو */
          .single-jbg_ad .jbg-player-wrapper,
          .single-jbg_ad .jbg-player-wrapper .plyr,
          .single-jbg_ad .jbg-player-wrapper video{
            width:100% !important; max-width:100% !important; display:block;
          }

          /* اگر پلیر بیرون page-content استفاده شود، محدود به 1312px شود */
          .single-jbg_ad :not(.page-content) .jbg-player-wrapper{
            max-width:var(--jbg-content-width);
            margin-left:auto; margin-right:auto; box-sizing:border-box;
          }

          .single-jbg_ad .jbg-main-stack{display:block;direction:rtl;}
          .single-jbg_ad .jbg-main-stack .jbg-section{
            background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-top:16px
          }

          /* بلوک قفل */
          .jbg-locked{background:#fff;border:1px dashed #9ca3af;border-radius:12px;padding:18px;margin-top:16px;color:#374151}
          .jbg-locked .title{font-weight:800;margin-bottom:8px}
          .jbg-locked .note{font-size:13px;color:#6b7280}

          /* مخفی‌سازی تیترهای پیش‌فرض قالب */
          .single-jbg_ad .entry-title,
          .single-jbg_ad h1.entry-title,
          .single-jbg_ad .post-title,
          .single-jbg_ad .elementor-heading-title{display:none !important;}

          /* هدر داخلی تک‌ویدیو */
          .jbg-player-wrapper .jbg-single-header{width:100%;margin:10px 0 0;padding:0;box-sizing:border-box;direction:rtl;text-align:right;}
          .jbg-single-header .jbg-headrow{display:flex;align-items:baseline;gap:12px}
          .jbg-single-header .jbg-single-title{margin:0;font-size:24px;line-height:1.35;font-weight:800;color:#111827}
          .jbg-single-header .jbg-single-meta{margin-inline-start:auto;display:flex;gap:8px;align-items:center;font-size:14px;color:#374151;flex-wrap:nowrap}
          .jbg-single-header .brand{background:#f1f5f9;color:#111827;border:1px solid #e5e7eb;border-radius:999px;padding:3px 10px;font-weight:600;white-space:nowrap}
          .jbg-single-header .dot{opacity:.55}

          /* بج امتیاز کنار عنوان */
          .jbg-points-badge{display:inline-flex;align-items:center;gap:6px;margin-inline-start:8px;
            padding:2px 8px;border-radius:9999px;background:#EEF2FF;color:#3730A3;font-weight:700;font-size:12px;border:1px solid #E0E7FF}
          .jbg-points-badge .pt-val{font-weight:800}

          /* ریست لینک‌ها در لیست «ویدیوهای مرتبط» */
          .jbg-related a,
          .jbg-related a:visited,
          .jbg-related a:hover,
          .jbg-related a:focus{
            text-decoration:none !important;border:0 !important;box-shadow:none !important;background-image:none !important;
          }
          .jbg-related a::before,.jbg-related a::after{display:none !important;content:none !important}

          @media (max-width:640px){
            :root{ --jbg-content-pad:12px; }
            .jbg-single-header .jbg-headrow{flex-direction:column;align-items:flex-end;gap:6px}
            .jbg-single-header .jbg-single-title{font-size:16px}
            .jbg-single-header .jbg-single-meta{font-size:12.5px;margin-inline-start:0;flex-wrap:wrap;justify-content:flex-start}
          }
        </style>';

        $out  = $style;
        $out .= '<div class="jbg-full-bleed"><div class="jbg-container">';
        $out .= '<div class="jbg-main-stack">';

        if ($is_open) {
            // محتوای اصلی (پلیر/هدر که از شورتکد یا قالب می‌آید)
            $out .= $content;

            // بج امتیاز
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
            // قفل بودن
            $seq = Access::seq($ad_id);
            $out .= '<div class="jbg-locked"><div class="title">این ویدیو هنوز برای شما باز نیست</div>'
                  . '<div class="note">برای دسترسی، ابتدا ویدیوی مرحلهٔ <strong>'.esc_html(max(1,$seq-1)).'</strong> را کامل ببینید و آزمونش را درست پاسخ دهید.'
                  . ($user_id>0 ? '' : ' (لطفاً ابتدا وارد شوید)')
                  . '</div></div>';

            // مرتبط‌ها
            $rel_html = do_shortcode('[jbg_related limit="10"]');
            if (trim($rel_html)!=='') $out .= '<div class="jbg-section">'.$rel_html.'</div>';
        }

        $out .= '</div></div></div>';
        return $out;
    }
}
