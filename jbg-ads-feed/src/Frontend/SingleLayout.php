<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

use JBG\Ads\Progress\Access;

class SingleLayout {

    public static function register(): void {
        // باید قبل از پلیر (priority=5) اجرا شود
        add_filter('the_content', [self::class, 'wrap'], 4);
    }

    public static function wrap($content) {
        if (!is_singular('jbg_ad') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $user_id = get_current_user_id();
        $ad_id   = get_the_ID();
        $seq     = Access::seq($ad_id);
        $is_open = Access::is_unlocked($user_id, $ad_id);

        $style = '<style id="jbg-single-stack-css">
          .single-jbg_ad .jbg-main-stack{display:block;direction:rtl;}
          .single-jbg_ad .jbg-main-stack .jbg-section{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-top:16px}
          .jbg-locked{background:#fff;border:1px dashed #9ca3af;border-radius:12px;padding:18px;margin-top:16px;color:#374151}
          .jbg-locked .title{font-weight:800;margin-bottom:8px}
          .jbg-locked .note{font-size:13px;color:#6b7280}
        </style>';

        $html  = '<div class="jbg-main-stack">';

        if ($is_open) {
            // ✅ ویدیو باز است: محتوا+آزمون+مرتبط‌ها
            $html .= $content;

            $quiz_html = do_shortcode('[jbg_quiz]');
            if (!empty(trim($quiz_html))) {
                $html .= '<div class="jbg-section">'.$quiz_html.'</div>';
            }

            $rel_html = do_shortcode('[jbg_related limit="10"]');
            if (!empty(trim($rel_html))) {
                $html .= '<div class="jbg-section">'.$rel_html.'</div>';
            }
        } else {
            // 🚫 ویدیو قفل است: قبل از هر چیز جلوی تزریق پلیر را بگیر
            if (class_exists('\\JBG\\Player\\Frontend\\Renderer')) {
                remove_filter('the_content', ['\\JBG\\Player\\Frontend\\Renderer', 'inject_player'], 5);
            }

            $allowed = ($user_id>0) ? Access::unlocked_max($user_id) : 1;
            $prev    = max(1, $seq - 1);

            $html .= '<div class="jbg-locked">'
                  .  '<div class="title">این ویدیو هنوز برای شما باز نیست</div>'
                  .  '<div class="note">برای دسترسی، ابتدا ویدیوی مرحلهٔ <strong>'.esc_html($prev).'</strong> را کامل ببینید و آزمونش را درست پاسخ دهید.'
                  .  ($user_id>0 ? ' مرحلهٔ باز فعلی شما: <strong>'.esc_html($allowed).'</strong>' : ' (لطفاً ابتدا وارد شوید) ')
                  .  '</div>'
                  .  '</div>';

            $rel_html = do_shortcode('[jbg_related limit="10"]');
            if (!empty(trim($rel_html))) {
                $html .= '<div class="jbg-section">'.$rel_html.'</div>';
            }
        }

        $html .= '</div>';

        static $once=false;
        if (!$once) { $html = $style.$html; $once=true; }

        return $html;
    }
}
