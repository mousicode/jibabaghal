<?php
namespace JBG\Ads\Progress;

if (!defined('ABSPATH')) exit;

/**
 * وقتی کاربر آزمون را پاس کند و بیلینگ انجام شود (jbg_billed)،
 * مرحلهٔ بعدی برایش باز می‌شود.
 */
class Unlock {
    public static function bootstrap(): void {
        add_action('jbg_billed', [self::class, 'on_billed'], 10, 4);
    }

    public static function on_billed($user_id, $ad_id, $cpv, $budget_remaining): void {
        $user_id = (int) $user_id;
        $ad_id   = (int) $ad_id;
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
