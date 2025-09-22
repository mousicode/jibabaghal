<?php
namespace JBG\Ads\Progress;
if (!defined('ABSPATH')) exit;

class Unlock {
    public static function bootstrap(): void {
        // قبلاً فقط این بود:
        add_action('jbg_billed', [self::class, 'on_billed'], 10, 4);

        // ← جدید: با پاس شدن آزمون هم باز کن (حتی اگر بیلینگ انجام نشود / تکراری باشد)
        add_action('jbg_quiz_passed', [self::class, 'on_pass'], 9, 2);
    }

    public static function on_pass($user_id, $ad_id): void {
        $user_id = (int) $user_id; $ad_id = (int) $ad_id;
        if ($user_id <= 0 || $ad_id <= 0) return;

        $cur_seq = Access::seq($ad_id);
        $next    = $cur_seq + 1;

        $key = 'jbg_unlocked_max_seq';
        $old = (int) get_user_meta($user_id, $key, true);
        if ($next > $old) {
            update_user_meta($user_id, $key, $next);
        }
    }

    public static function on_billed($user_id, $ad_id, $cpv, $budget_remaining): void {
        $user_id = (int) $user_id; $ad_id = (int) $ad_id;
        if ($user_id <= 0 || $ad_id <= 0) return;

        $cur_seq = Access::seq($ad_id);
        $next    = $cur_seq + 1;

        $key = 'jbg_unlocked_max_seq';
        $old = (int) get_user_meta($user_id, $key, true);
        if ($next > $old) {
            update_user_meta($user_id, $key, $next);
        }
    }
}
