<?php
namespace JBG\Ads\Rest;

if (!defined('ABSPATH')) exit;

class ViewTrackController {

    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/view/track', [
            'methods'  => 'POST',
            'permission_callback' => function(){ return is_user_logged_in(); },
            'args' => [
                'ad_id' => ['type'=>'integer', 'required'=>true, 'minimum'=>1],
            ],
            'callback' => [self::class, 'track'],
        ]);
    }

    public static function track(\WP_REST_Request $req) {
        $ad_id = (int) $req->get_param('ad_id');
        $uid   = get_current_user_id();

        if ($ad_id <= 0 || get_post_type($ad_id) !== 'jbg_ad') {
            return new \WP_Error('bad_ad', 'Invalid ad_id', ['status'=>400]);
        }
        if ($uid <= 0) {
            return new \WP_Error('no_user', 'User not logged in', ['status'=>401]);
        }

        // به تم/افزونه‌ها اجازه می‌دهیم تصمیم بگیرند که این بازدید شمرده بشود یا نه
        $should_count = apply_filters('jbg_viewtrack_should_count', true, $ad_id, $uid, $req);
        if ($should_count === false) {
            return new \WP_REST_Response(['ok'=>true, 'counted'=>false, 'reason'=>'filtered'], 200);
        }

        global $wpdb;
        $table = $wpdb->prefix.'jbg_views';

        // پنجره‌ی زمانی ضد تکرار (ساعت) - قابل تنظیم با فیلتر
        $hours   = max(1, (int) apply_filters('jbg_viewtrack_window_hours', 24));
        $cutoff  = gmdate('Y-m-d H:i:s', time() - ($hours * 3600)); // UTC
        // اگر ستون created_at بر اساس timezone سایت ثبت می‌شود، می‌توانی به‌جای gmdate از current_time('mysql') معادل بگیری

        // اگر در بازه‌ی ضدتکرار قبلاً بازدیدی از این کاربر برای این آگهی ثبت شده بود، دوباره ثبت نکن
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE ad_id=%d AND user_id=%d
               AND created_at >= %s
             LIMIT 1",
            $ad_id, $uid, $cutoff
        ));
        if ($exists) {
            return new \WP_REST_Response(['ok'=>true, 'already'=>true, 'counted'=>false], 200);
        }

        // درج لاگ بازدید (amount=0 چون این endpoint بیلینگ نیست)
        $ip = isset($_SERVER['REMOTE_ADDR']) ? substr(sanitize_text_field($_SERVER['REMOTE_ADDR']), 0, 45) : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 255) : '';
        $ins = $wpdb->insert($table, [
            'ad_id'      => $ad_id,
            'user_id'    => $uid,
            'amount'     => 0,
            'created_at' => current_time('mysql'),
            'ip'         => $ip,
            'ua'         => $ua,
        ], ['%d','%d','%d','%s','%s','%s']);

        if ($ins === false) {
            return new \WP_Error('db_insert_failed', 'Insert failed: '.$wpdb->last_error, ['status'=>500]);
        }

        // افزایش اتمی شمارنده‌ی بازدید:
        //  - jbg_views_total = تعداد ایمپرشن‌ها/مشاهده‌ها (صرف‌نظر از بیلینگ)
        //  - jbg_views_count = شمارنده‌ی اصلی که باید فقط بعد از قبولی آزمون/بیلینگ آپدیت شود (اینجا عمداً افزایش نمی‌دهیم)
        self::incr_views_meta($ad_id, 'jbg_views_total');
        // self::incr_views_meta($ad_id, 'jbg_views_count'); // ← حذف شد تا دوباره‌شماری رخ ندهد

        return new \WP_REST_Response(['ok'=>true, 'already'=>false, 'counted'=>true], 200);
    }

    private static function incr_views_meta(int $ad_id, string $key): void {
        global $wpdb;
        $meta_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s LIMIT 1",
            $ad_id, $key
        ));
        if ($meta_id > 0) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_value = CAST(meta_value AS UNSIGNED) + 1
                 WHERE post_id=%d AND meta_key=%s",
                $ad_id, $key
            ));
        } else {
            add_post_meta($ad_id, $key, 1, true);
        }
        wp_cache_delete($ad_id, 'post_meta');
    }
}
