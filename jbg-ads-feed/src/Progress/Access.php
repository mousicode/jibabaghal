<?php
namespace JBG\Ads\Progress;

if (!defined('ABSPATH')) exit;

class Access {
    /** شمارهٔ ترتیب ویدیو: meta jbg_seq → menu_order → 1 */
    public static function seq(int $ad_id): int {
        $seq = (int) get_post_meta($ad_id, 'jbg_seq', true);
        if ($seq > 0) return $seq;
        $menu = (int) get_post_field('menu_order', $ad_id);
        if ($menu > 0) return $menu;
        return 1;
    }

    /** بیشینهٔ مرحلهٔ بازشده برای کاربر (پیش‌فرض 1) */
    public static function unlocked_max(int $user_id): int {
        $v = (int) get_user_meta($user_id, 'jbg_unlocked_max_seq', true);
        return max(1, $v);
    }

    /** آیا این ویدیو برای کاربر باز است؟ (مهمان فقط مرحلهٔ 1 را می‌بیند) */
    public static function is_unlocked(?int $user_id, int $ad_id): bool {
        $seq = self::seq($ad_id);
        if ($user_id <= 0) return ($seq <= 1);
        return $seq <= self::unlocked_max($user_id);
    }
}
