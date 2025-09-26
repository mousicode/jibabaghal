<?php
namespace JBG\Ads\Rest;
if (!defined('ABSPATH')) exit;

/**
 * /jbg/v1/like  [POST]
 * بدنه: { ad_id:int }
 * خروجى: { liked:bool, count:int }
 */
class LikeController {

    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/like', [
            'methods'  => 'POST',
            'callback' => [self::class, 'toggle'],
            'permission_callback' => function(){ return is_user_logged_in(); },
        ]);
    }

    public static function toggle(\WP_REST_Request $req) {
        $uid = get_current_user_id();
        $ad  = (int)($req->get_param('ad_id') ?? 0);
        if ($uid<=0) return new \WP_REST_Response(['message'=>'login'], 401);
        if ($ad<=0 || get_post_type($ad)!=='jbg_ad') {
            return new \WP_REST_Response(['message'=>'bad_ad'], 400);
        }

        $liked = (array) get_user_meta($uid, 'jbg_liked_ids', true);
        $liked = array_map('intval', $liked);

        $count = (int) get_post_meta($ad, 'jbg_like_count', true);

        if (in_array($ad, $liked, true)) {
            // حذف لایک
            $liked = array_values(array_diff($liked, [$ad]));
            update_user_meta($uid, 'jbg_liked_ids', $liked);
            $count = max(0, $count-1);
            update_post_meta($ad, 'jbg_like_count', $count);
            return new \WP_REST_Response(['liked'=>false, 'count'=>$count], 200);
        } else {
            $liked[] = $ad;
            $liked   = array_values(array_unique($liked));
            update_user_meta($uid, 'jbg_liked_ids', $liked);
            $count   = $count+1;
            update_post_meta($ad, 'jbg_like_count', $count);
            return new \WP_REST_Response(['liked'=>true, 'count'=>$count], 200);
        }
    }
}
