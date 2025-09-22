<?php
namespace JBG\Ads\Progress;

if (!defined('ABSPATH')) exit;

class Access {

    /** کشِ ترتیب کلی برای همین درخواست */
    private static array $ordered_ids = [];

    /**
     * لیست همه آگهی‌ها به ترتیب نهایی (menu_order ↑ , date ↑ , ID ↑)
     * برای سازگاری با چندزبانه، lang=all و suppress_filters=true.
     */
    private static function ordered_ids(): array {
        if (!empty(self::$ordered_ids)) {
            return self::$ordered_ids;
        }
        $q = new \WP_Query([
            'post_type'           => 'jbg_ad',
            'post_status'         => 'publish',
            'posts_per_page'      => -1,
            'fields'              => 'ids',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'suppress_filters'    => true,
            'orderby'             => ['menu_order' => 'ASC', 'date' => 'ASC', 'ID' => 'ASC'],
            'lang'                => 'all',
        ]);
        self::$ordered_ids = $q->posts ?: [];
        wp_reset_postdata();
        return self::$ordered_ids;
    }

    /** شمارهٔ مرحلهٔ ویدیو: meta jbg_seq → menu_order → جایگاه در ترتیب کلی (۱‌پایه) */
    public static function seq(int $ad_id): int {
        $seq = (int) get_post_meta($ad_id, 'jbg_seq', true);
        if ($seq > 0) return $seq;

        $menu = (int) get_post_field('menu_order', $ad_id);
        if ($menu > 0) return $menu;

        // fallback مطمئن: جایگاه بر اساس ترتیب کلی
        $ids = self::ordered_ids();
        $pos = array_search($ad_id, $ids, true);
        return ($pos === false) ? 1 : ($pos + 1);
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
        return $seq <= self::unlocked_max((int)$user_id);
    }

    /** شناسهٔ «ویدئوی بعدی» نسبت به یک آگهی فعلی؛ اگر نبود 0 */
    public static function next_ad_id(int $current_id): int {
        $ids = self::ordered_ids();
        $i   = array_search($current_id, $ids, true);
        return ($i !== false && isset($ids[$i+1])) ? (int) $ids[$i+1] : 0;
    }
}
