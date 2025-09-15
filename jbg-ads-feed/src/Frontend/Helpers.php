<?php
namespace JBG\Ads\Frontend;

if (!defined('ABSPATH')) exit;

class Helpers {
    /**
     * تعداد بازدید آگهی را برمی‌گرداند.
     * 1) ابتدا از متای jbg_views_count
     * 2) اگر نبود/صفر بود، از جدول لاگ‌ها می‌شمارد و متا را سینک می‌کند.
     */
    public static function views_count(int $ad_id): int {
        $ad_id = absint($ad_id);
        if ($ad_id <= 0) return 0;

        // 1) از متا
        $v = (int) get_post_meta($ad_id, 'jbg_views_count', true);
        if ($v > 0) return $v;

        // 2) fallback: از جدول لاگ‌ها
        global $wpdb;
        $table = $wpdb->prefix . 'jbg_views';
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE ad_id = %d",
            $ad_id
        ));

        // سینک متا برای دفعات بعد
        if ($count >= 0) {
            update_post_meta($ad_id, 'jbg_views_count', $count);
        }
        return $count;
    }
}
