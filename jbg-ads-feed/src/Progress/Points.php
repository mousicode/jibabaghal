<?php
namespace JBG\Ads\Progress;
if (!defined('ABSPATH')) exit;

class Points {

    /** فقط یک‌بار به‌ازای هر آگهی امتیاز بده، مجموع را به‌روز کن و لاگ بساز */
    public static function award_if_first_time(int $user_id, int $ad_id): void {
        if ($user_id <= 0 || $ad_id <= 0) return;

        // قبلاً برای این آگهی امتیاز داده‌ایم؟
        if (get_user_meta($user_id, 'jbg_points_awarded_' . $ad_id, true)) return;

        $pts = (int) get_post_meta($ad_id, 'jbg_points', true);
        if ($pts <= 0) {
            // هیچ امتیازی تعریف نشده؛ فقط پرچم پاس را ثبت کنیم (برای سازگاری)
            update_user_meta($user_id, 'jbg_points_awarded_' . $ad_id, time());
            return;
        }

        // مجموع کل
        $total = (int) get_user_meta($user_id, 'jbg_points_total', true);
        $total += $pts;
        update_user_meta($user_id, 'jbg_points_total', $total);

        // پرچم award برای این آگهی
        update_user_meta($user_id, 'jbg_points_awarded_' . $ad_id, time());

        // لاگ آخرین امتیازها (برای نمایش تاریخچه)
        $log = get_user_meta($user_id, 'jbg_points_log', true);
        if (!is_array($log)) $log = [];
        $log[] = [
            'ad_id'   => (int) $ad_id,
            'title'   => (string) get_the_title($ad_id),
            'points'  => (int) $pts,
            'time'    => time(),
        ];
        // اندازه لاگ را معقول نگه داریم (مثلاً آخرین 100)
        if (count($log) > 100) $log = array_slice($log, -100);
        update_user_meta($user_id, 'jbg_points_log', $log);
    }

    /** هوک‌های لازم */
    public static function bootstrap(): void {
        // وقتی کاربر کوییز را درست زد:
        add_action('jbg_quiz_passed', function($user_id, $ad_id){
            self::award_if_first_time((int)$user_id, (int)$ad_id);
        }, 10, 2);

        // اگر جای دیگری (بیلینگ) هم پاس تلقی می‌شود:
        add_action('jbg_billed', function($user_id, $ad_id){
            self::award_if_first_time((int)$user_id, (int)$ad_id);
        }, 10, 2);
    }

    /** ابزارهای قابل‌استفاده از سایر کلاس‌ها */
    public static function total(int $user_id): int {
        return (int) get_user_meta($user_id, 'jbg_points_total', true);
    }
    public static function log(int $user_id): array {
        $log = get_user_meta($user_id, 'jbg_points_log', true);
        return is_array($log) ? $log : [];
    }
}
