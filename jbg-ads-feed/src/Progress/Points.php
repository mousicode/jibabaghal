<?php
namespace JBG\Ads\Progress;
if (!defined('ABSPATH')) exit;

class Points {
    public static function bootstrap(): void {
        add_action('jbg_quiz_passed', [self::class, 'on_quiz_passed'], 10, 2);
    }

    public static function on_quiz_passed(int $user_id, int $ad_id): void {
        if ($user_id <= 0 || $ad_id <= 0) return;

        // اگر قبلاً برای این آگهی امتیاز داده شده، دوباره جمع نکن
        $already = (int) get_user_meta($user_id, 'jbg_points_awarded_' . $ad_id, true);
        if (!$already) {
            $pts = (int) get_post_meta($ad_id, 'jbg_points', true);
            if ($pts > 0) {
                $total = (int) get_user_meta($user_id, 'jbg_points_total', true);
                update_user_meta($user_id, 'jbg_points_total', $total + $pts);
                update_user_meta($user_id, 'jbg_points_awarded_' . $ad_id, time());
            }
        }

        // برای بازسازی پیشرفت: همیشه این‌ها را ثبت کن
        update_user_meta($user_id, 'jbg_quiz_passed_' . $ad_id, time());
        $list = get_user_meta($user_id, 'jbg_quiz_passed_ids', true);
        if (!is_array($list)) $list = [];
        if (!in_array($ad_id, $list, true)) {
            $list[] = $ad_id;
            update_user_meta($user_id, 'jbg_quiz_passed_ids', $list);
        }
    }
}
