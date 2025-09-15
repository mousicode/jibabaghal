<?php
namespace JBG\Ads\Rest;

class ViewController {
    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/view/confirm', [
            'methods'  => 'POST',
            'callback' => [self::class, 'confirm'],
            'permission_callback' => function(){ return is_user_logged_in(); },
            'args' => [
                'ad_id' => ['type'=>'integer','required'=>true],
            ],
        ]);
    }

    public static function confirm(\WP_REST_Request $req) {
        $user = get_current_user_id();
        if (!$user) return new \WP_Error('jbg_auth','Login required',['status'=>401]);

        $ad_id = (int) $req->get_param('ad_id');
        if ($ad_id <= 0 || get_post_type($ad_id) !== 'jbg_ad') {
            return new \WP_Error('jbg_bad_ad','Invalid ad_id',['status'=>400]);
        }

        // باید قبلاً تماشا تایید شده باشد
        if (!get_user_meta($user, 'jbg_watched_ok_'.$ad_id, true)) {
            return new \WP_Error('jbg_not_watched','Watch the video first',['status'=>422]);
        }

        // هر کاربر/آگهی فقط یکبار شمرده شود
        $view_key = 'jbg_viewed_'.$ad_id;
        if (!get_user_meta($user, $view_key, true)) {
            $views = (int) get_post_meta($ad_id, 'jbg_views_total', true);
            $views++;
            update_post_meta($ad_id, 'jbg_views_total', $views);
            update_user_meta($user, $view_key, current_time('mysql'));
            do_action('jbg_view_confirmed', $user, $ad_id, $views);
        }

        return new \WP_REST_Response(['ok'=>true], 200);
    }
}
