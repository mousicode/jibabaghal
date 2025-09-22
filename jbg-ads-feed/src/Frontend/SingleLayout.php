<?php
namespace JBG\Ads\Frontend;
if (!defined('ABSPATH')) exit;

use JBG\Ads\Progress\Access;

class SingleLayout {

    public static function register(): void {
        // گارد قبل از رندر
        add_action('template_redirect', [self::class, 'guard'], 1);
        // محتوا
        add_filter('the_content', [self::class, 'wrap'], 4);
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

        $style = '<style id="jbg-single-stack-css">
          .single-jbg_ad .jbg-main-stack{display:block;direction:rtl;}
          .single-jbg_ad .jbg-main-stack .jbg-section{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-top:16px}
          .jbg-locked{background:#fff;border:1px dashed #9ca3af;border-radius:12px;padding:18px;margin-top:16px;color:#374151}
          .jbg-locked .title{font-weight:800;margin-bottom:8px}
          .jbg-locked .note{font-size:13px;color:#6b7280}
        </style>';

        $html = '<div class="jbg-main-stack">';

        if ($is_open) {
            $html .= $content;

            $quiz_html = do_shortcode('[jbg_quiz]');
            if (trim($quiz_html)!=='') $html .= '<div class="jbg-section">'.$quiz_html.'</div>';

            $rel_html = do_shortcode('[jbg_related limit="10"]');
            if (trim($rel_html)!=='') $html .= '<div class="jbg-section">'.$rel_html.'</div>';
        } else {
            $seq = Access::seq($ad_id);
            $html .= '<div class="jbg-locked"><div class="title">این ویدیو هنوز برای شما باز نیست</div>'
                   . '<div class="note">برای دسترسی، ابتدا ویدیوی مرحلهٔ <strong>'.esc_html(max(1,$seq-1)).'</strong> را کامل ببینید و آزمونش را درست پاسخ دهید.'
                   . ($user_id>0 ? '' : ' (لطفاً ابتدا وارد شوید)')
                   . '</div></div>';

            $rel_html = do_shortcode('[jbg_related limit="10"]');
            if (trim($rel_html)!=='') $html .= '<div class="jbg-section">'.$rel_html.'</div>';
        }

        $html .= '</div>';
        static $once=false; if(!$once){ $html=$style.$html; $once=true; }
        return $html;
    }
}
