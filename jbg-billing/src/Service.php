<?php
namespace JBG\Billing;

if (!defined('ABSPATH')) exit;

class Service {

    /**
     * تبدیل عددهای فارسی/عربی به رقم لاتین + حذف کاراکترهای نامرئی
     */
    private static function to_ascii_int($val): int {
        $map = [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            "\xE2\x80\x8E"=>'', "\xE2\x80\x8F"=>'' // LRM/RLM
        ];
        $s = strtr((string) $val, $map);
        $s = preg_replace('/[^0-9]/', '', $s);
        return (int) $s;
    }

    /**
     * اطمینان از وجود جدول لاگ مشاهده/بیلینگ
     */
    private static function ensure_table(): void {
        global $wpdb;
        $table  = $wpdb->prefix . 'jbg_views';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ad_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                amount INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                ip VARCHAR(45) DEFAULT '' NOT NULL,
                ua VARCHAR(255) DEFAULT '' NOT NULL,
                PRIMARY KEY (id),
                KEY ad_id (ad_id),
                KEY user_id (user_id)
            ) {$charset};";
            dbDelta($sql);
        }
    }

    /**
     * افزایش اتمی شمارندهٔ بازدید روی متای پست (بدون وابستگی به کش/مقدار قبلی)
     */
    private static function incr_views_atomic(int $ad_id): void {
        global $wpdb;
        $key = 'jbg_views_count';

        // اگر متا وجود داشته باشد: +۱ به‌صورت مستقیم در DB
        $meta_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s LIMIT 1",
            $ad_id, $key
        ));

        if ($meta_id > 0) {
            // افزایش عددی امن (CAST به UNSIGNED)
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_value = CAST(meta_value AS UNSIGNED) + 1
                 WHERE post_id=%d AND meta_key=%s",
                $ad_id, $key
            ));
        } else {
            // در اولین بار، متا را با مقدار ۱ بساز
            add_post_meta($ad_id, $key, 1, true);
        }

        // پاکسازی کش متای پست تا مقدار جدید بلافاصله دیده شود
        wp_cache_delete($ad_id, 'post_meta');
    }

    /**
     * هندل رویداد قبولی آزمون → تلاش برای بیلینگ
     */
    public static function handle_quiz_pass($user_id, $ad_id): void {
        try {
            self::bill($user_id, $ad_id);
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('JBG Billing error: ' . $e->getMessage());
            }
        }
    }

    /**
     * بیلینگ یک‌بار برای هر (کاربر × آگهی):
     * - بررسی‌ها، کسر بودجه، تنظیم fundable
     * - درج لاگ در DB
     * - افزایش اتمی شمارندهٔ بازدید آگهی
     * - افزایش خرج برند(ها)
     * - شلیک هوک jbg_billed
     */
    public static function bill($user_id, $ad_id): bool {
        $user_id = self::to_ascii_int($user_id);
        $ad_id   = self::to_ascii_int($ad_id);

        if ($user_id <= 0 || $ad_id <= 0) return false;
        if (get_post_type($ad_id) !== 'jbg_ad') return false;

        $cpv = (int) get_post_meta($ad_id, 'jbg_cpv', true);
        if ($cpv <= 0) return false;

        // --- قفل سبک برای جلوگیری از Race (Atomic-ish) ---
        $lock_key = 'jbg_bill_lock_' . $ad_id . '_' . $user_id;
        $got_lock = add_option($lock_key, '1', '', 'no'); // ایجاد فقط در صورت نبود
        if (!$got_lock) {
            // قفل در جریان است؛ درخواست موازی را رد کن
            return false;
        }

        try {
            // جلوگیری از دوباربیلینگ برای همین کاربر/آگهی (داخل قفل)
            $billed_key = 'jbg_billed_' . $ad_id;
            if (get_user_meta($user_id, $billed_key, true)) {
                return false; // idempotent
            }

            // بودجه فعلی و کفایت آن
            $br = (int) get_post_meta($ad_id, 'jbg_budget_remaining', true);
            if ($br < $cpv) {
                update_post_meta($ad_id, 'jbg_is_fundable', 0);
                return false; // بودجه کافی نیست
            }

            // کسر بودجه + تنظیم fundable
            $new_br = max(0, $br - $cpv);
            update_post_meta($ad_id, 'jbg_budget_remaining', $new_br);
            update_post_meta($ad_id, 'jbg_is_fundable', ($new_br >= $cpv) ? 1 : 0);

            // مارک‌کردن به‌عنوان «بیل شده» برای این کاربر
            update_user_meta($user_id, $billed_key, current_time('mysql'));

            // درج لاگ در جدول اختصاصی
            self::ensure_table();
            global $wpdb;
            $table  = $wpdb->prefix . 'jbg_views';
            $ins_ok = $wpdb->insert($table, [
                'ad_id'      => $ad_id,
                'user_id'    => $user_id,
                'amount'     => $cpv,
                'created_at' => current_time('mysql'),
                'ip'         => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
                'ua'         => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 255) : '',
            ], ['%d','%d','%d','%s','%s','%s']);

            if ($ins_ok === false && function_exists('error_log')) {
                error_log('JBG Billing DB insert failed: ' . $wpdb->last_error);
            }

            // ✅ شمارندهٔ بازدید آگهی (اتمی)
            try {
                self::incr_views_atomic($ad_id);
            } catch (\Throwable $e) {
                if (function_exists('error_log')) {
                    error_log('JBG Views counter update error: ' . $e->getMessage());
                }
            }

            // ✅ افزایش خرج برند(ها)ی متصل به آگهی
            try {
                $brand_ids = wp_get_post_terms($ad_id, 'jbg_brand', ['fields' => 'ids']);
                if (!is_wp_error($brand_ids) && is_array($brand_ids)) {
                    foreach ($brand_ids as $tid) {
                        $spent = (int) get_term_meta($tid, 'jbg_brand_spent', true);
                        update_term_meta($tid, 'jbg_brand_spent', $spent + (int) $cpv);
                    }
                }
            } catch (\Throwable $e) {
                if (function_exists('error_log')) {
                    error_log('JBG Brand spend update error: ' . $e->getMessage());
                }
            }

            // هوک عمومی برای گزارش/آنالیتیکس
            do_action('jbg_billed', $user_id, $ad_id, $cpv, $new_br);

            return true;
        } finally {
            // آزادسازی قفل
            delete_option($lock_key);
        }
    }
}
