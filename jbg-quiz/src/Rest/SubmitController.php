<?php
/**
 * REST: Quiz Submit (secured + validated)
 */
namespace JBG\Quiz\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use JBG\Security\Auth; // برای permission_callback

class SubmitController
{
    public static function register_routes(): void
    {
        register_rest_route('jbg/v1', '/quiz/submit', [
            'methods'  => 'POST',
            'callback' => [self::class, 'handle'],
            // الزام لاگین/nonce با منطق هسته
            'permission_callback' => Auth::rest_permission(),
            'args' => [
                'ad_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($v) {
                        return (absint($v) > 0);
                    },
                ],
                'answer' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($v) {
                        $v = absint($v);
                        return ($v >= 1 && $v <= 4);
                    },
                ],
            ],
        ]);
    }

    public static function handle(WP_REST_Request $req): WP_REST_Response|WP_Error
    {
        $ad_id = absint($req->get_param('ad_id'));
        $ans   = absint($req->get_param('answer'));

        if ($ad_id <= 0) {
            return new WP_Error('bad_request', 'Invalid ad_id', ['status' => 400]);
        }
        if ($ans < 1 || $ans > 4) {
            return new WP_Error('bad_request', 'Invalid answer', ['status' => 400]);
        }

        // پاسخ صحیح در متای jbg_quiz_ans ذخیره می‌شود (۱..۴)
        $correct = (int) get_post_meta($ad_id, 'jbg_quiz_ans', true);
        if ($correct < 1 || $correct > 4) {
            return new WP_Error('server', 'Quiz is not configured properly', ['status' => 500]);
        }

        $ok = ($ans === $correct);

        // اگر پاسخ درست است، ایونت سروری شلیک شود (بیلینگ/آمار)
        if ($ok) {
            $user_id = get_current_user_id();
            /**
             * جابجایی شمارنده/بیلینگ را وصل کنید:
             * do_action('jbg_quiz_passed', $user_id, $ad_id);
             */
            do_action('jbg_quiz_passed', $user_id, $ad_id);
        }

        return new WP_REST_Response([
            'correct' => (bool) $ok,
            'ad_id'   => $ad_id,
        ], 200);
    }
}
