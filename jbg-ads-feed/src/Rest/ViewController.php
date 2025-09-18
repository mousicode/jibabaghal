<?php
namespace JBG\Ads\Rest;
if (!defined('ABSPATH')) exit;

class ViewTrackController {

    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/view/track', [
            'methods'  => 'POST',
            'permission_callback' => function(){ return is_user_logged_in(); },
            'callback' => [self::class, 'track'],
        ]);
    }

    public static function track(\WP_REST_Request $req) {
        $uid = get_current_user_id();
        if (!$uid) return new \WP_Error('jbg_auth','Login required',['status'=>401]);

        $json = json_decode($req->get_body(), true) ?: [];
        $ad_id = isset($json['ad_id']) ? absint($json['ad_id']) : 0;
        if ($ad_id <= 0 || get_post_type($ad_id) !== 'jbg_ad') {
            return new \WP_Error('bad_ad','Invalid ad id', ['status'=>400]);
        }

        // Idempotent: هر کاربر/ویدیو فقط یک بازدید در هر روز
        $ymd = wp_date('Y-m-d');
        $key = "jbg_viewed_{$ad_id}_{$ymd}";
        if (get_user_meta($uid, $key, true)) {
            return new \WP_REST_Response(['ok'=>true,'dedup'=>1], 200);
        }

        // ثبت ساده روی post meta (در صورت داشتن جدول اختصاصی، اینجا INSERT کنید)
        $total = (int) get_post_meta($ad_id, 'jbg_views_total', true);
        $total++;
        update_post_meta($ad_id, 'jbg_views_total', $total);
        update_post_meta($ad_id, 'jbg_views_count', $total);
        update_user_meta($uid, $key, 1);

        return new \WP_REST_Response(['ok'=>true,'total'=>$total], 200);
    }
}
