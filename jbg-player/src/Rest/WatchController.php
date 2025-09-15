<?php
namespace JBG\Player\Rest;


class WatchController {
public static function register_routes(): void {
register_rest_route('jbg/v1', '/watch-complete', [
'methods' => 'POST',
'callback' => [self::class, 'complete'],
'permission_callback' => function(){ return is_user_logged_in(); },
'args' => [
'ad_id' => ['type'=>'integer','required'=>true],
'watch_pct' => ['type'=>'number','required'=>true],
'session' => ['type'=>'string','required'=>false],
],
]);
}


public static function complete(\WP_REST_Request $req) {
$user = get_current_user_id();
if (!$user) return new \WP_Error('jbg_auth', 'Login required', ['status'=>401]);


$ad_id = (int) $req->get_param('ad_id');
$pct = (float) $req->get_param('watch_pct');
if ($ad_id <= 0 || get_post_type($ad_id) !== 'jbg_ad')
return new \WP_Error('jbg_bad_ad', 'Invalid ad_id', ['status'=>400]);
if ($pct < 0.90) // safety floor
return new \WP_Error('jbg_low_pct', 'Watch percent too low', ['status'=>422]);


// Mark watched_ok for this user/ad
$key = 'jbg_watched_ok_' . $ad_id;
update_user_meta($user, $key, current_time('mysql'));


return new \WP_REST_Response([
'ok' => true,
'ad_id' => $ad_id,
'watched_ok' => true,
'at' => current_time('mysql'),
], 200);
}
}