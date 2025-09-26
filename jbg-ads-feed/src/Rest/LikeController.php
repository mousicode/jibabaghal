<?php
namespace JBG\Ads\Rest;
if (!defined('ABSPATH')) exit;

class LikeController {

    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/like/toggle', [
            'methods'  => 'POST',
            'callback' => [self::class, 'toggle'],
            'permission_callback' => function(){ return is_user_logged_in(); },
        ]);
        register_rest_route('jbg/v1', '/like/status', [
            'methods'  => 'GET',
            'callback' => [self::class, 'status'],
            'permission_callback' => '__return_true',
        ]);
    }

    private static function sanitize_id($v): int {
        return max(0, (int)$v);
    }

    public static function status(\WP_REST_Request $req) {
        $ad_id = self::sanitize_id($req->get_param('ad_id'));
        if ($ad_id<=0 || get_post_type($ad_id)!=='jbg_ad') {
            return new \WP_REST_Response(['ok'=>false], 400);
        }
        $count = (int) get_post_meta($ad_id, 'jbg_like_count', true);
        $liked = false;
        if (is_user_logged_in()) {
            $liked = (bool) get_user_meta(get_current_user_id(), 'jbg_liked_'.$ad_id, true);
        }
        return new \WP_REST_Response(['ok'=>true,'count'=>$count,'liked'=>$liked], 200);
    }

    public static function toggle(\WP_REST_Request $req) {
        $user_id = get_current_user_id();
        $ad_id   = self::sanitize_id($req->get_param('ad_id'));
        if ($user_id<=0) {
            return new \WP_REST_Response(['ok'=>false,'message'=>'login_required'], 401);
        }
        if ($ad_id<=0 || get_post_type($ad_id)!=='jbg_ad') {
            return new \WP_REST_Response(['ok'=>false,'message'=>'invalid_ad'], 400);
        }

        $liked = (bool) get_user_meta($user_id, 'jbg_liked_'.$ad_id, true);
        $count = (int) get_post_meta($ad_id, 'jbg_like_count', true);

        if ($liked) {
            // unlike
            delete_user_meta($user_id, 'jbg_liked_'.$ad_id);
            $count = max(0, $count - 1);
            update_post_meta($ad_id, 'jbg_like_count', $count);
            return new \WP_REST_Response(['ok'=>true,'liked'=>false,'count'=>$count], 200);
        } else {
            // like
            update_user_meta($user_id, 'jbg_liked_'.$ad_id, time());
            $count = $count + 1;
            update_post_meta($ad_id, 'jbg_like_count', $count);
            return new \WP_REST_Response(['ok'=>true,'liked'=>true,'count'=>$count], 200);
        }
    }
}
