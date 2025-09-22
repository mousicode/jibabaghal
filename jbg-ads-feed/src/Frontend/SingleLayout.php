<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

use JBG\Ads\Progress\Access;

class SingleLayout {

    public static function register(): void {
        add_filter('the_content', [self::class, 'wrap'], 4);
    }

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $user_id = get_current_user_id();
        $ad_id   = get_the_ID();
        $is_open = Access::is_unlocked($user_id, $ad_id);

        // سبک‌های سبک
        $style = '<style id="jbg-single-stack-css">
          .single-jbg_ad .jbg-main-stack{display:block; direction:rtl;}
          .single-jbg_ad .jbg-main-stack .jbg-section{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-top:16px}
          .jbg-locked{background:#fff;border:1px dashed #9ca3af;border-radius:12px;padding:18px;margin-top:16px;color:#374151}
          .jbg-locked .title{font-weight:800;margin-bottom:8px}
          .jbg-locked .note{font-size:13px;color:#6b7280}
        </style>';

        $html  = '<div class="jbg-main-stack">';

        // محتوای اصلی (پلیر/Badge) همیشه رندر می‌شود
        $html .= $content;

        if ($is_open) {
            // آزمون را فقط اگر واقعاً خروجی دارد اضافه کن (تا باکس سفید نداشته باشیم)
            $quiz_html = do_shortcode('[jbg_quiz]');
            if (!empty(trim($quiz_html))) {
                $html .= '<div class="jbg-section">'.$quiz_html.'</div>';
            }
            // مرتبط‌ها
            $html .= '<div class="jbg-section">'. do_shortcode('[jbg_related limit="10"]') .'</div>';
        } else {
            // پیام قفل + مرتبط‌ها
            $seq     = Access::seq($ad_id);
            $allowed = ($user_id>0) ? Access::unlocked_max($user_id) : 1;
            $html .= '<div class="jbg-locked"><div class="title">این ویدیو هنوز باز نشده</div>'
                  .  '<div class="note">برای دسترسی، ابتدا ویدیوی مرحلهٔ '
                  .  '<strong>' . esc_html(max(1, $seq - 1)) . '</strong>'
                  .  ' را کامل ببینید و آزمونش را درست پاسخ دهید.'
                  .  ($user_id>0 ? ' (مرحلهٔ باز شما: ' . esc_html($allowed) . ')' : ' (ابتدا وارد شوید)') 
                  .  '</div></div>';
            $html .= '<div class="jbg-section">'. do_shortcode('[jbg_related limit="10"]') .'</div>';
        }

        $html .= '</div>';

        static $once=false;
        if (!$once) { $html = $style . $html; $once=true; }

        return $html;
    }
}