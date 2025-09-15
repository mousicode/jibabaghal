<?php
namespace JBG\Quiz\Rest;

class SubmitController {
    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/quiz/submit', [
            'methods'  => 'POST',
            'callback' => [self::class, 'handle'],
            'permission_callback' => '__return_true', // اگر لاگین لازم است، این را سفارشی کنید
            'args' => [
                'ad_id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($v){ return absint($v) > 0; },
                ],
                'answer' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'minimum'           => 1,
                    'maximum'           => 4,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($v){
                        $v = absint($v);
                        return ($v >= 1 && $v <= 4);
                    },
                ],
            ],
        ]);
    }

    public static function handle(\WP_REST_Request $req) {
        try {
            // ورودی را هم از JSON و هم از فرم بگیر
            $p     = $req->get_json_params();
            if (empty($p)) $p = $req->get_params();

            $ad_id = isset($p['ad_id'])   ? absint($p['ad_id'])   : 0;
            $ans   = isset($p['answer'])  ? absint($p['answer'])  : 0;

            if ($ad_id <= 0 || $ans < 1 || $ans > 4) {
                return new \WP_REST_Response(['correct'=>false, 'message'=>'پارامتر نامعتبر است.'], 400);
            }
            if (get_post_type($ad_id) !== 'jbg_ad') {
                return new \WP_REST_Response(['correct'=>false, 'message'=>'آگهی معتبر نیست.'], 400);
            }

            $correct = (int) get_post_meta($ad_id, 'jbg_quiz_correct', true);
            $ok      = ($ans === $correct);

            if ($ok) {
                /**
                 * جابجایی پول/ثبت بازدید با افزونه Billing
                 * 注意: اگر پرداخت فقط با کاربر لاگین‌شده مجاز است، get_current_user_id() باید > 0 باشد.
                 */
                do_action('jbg_quiz_passed', get_current_user_id(), $ad_id);
            }

            return new \WP_REST_Response(['correct'=>$ok], 200);
        } catch (\Throwable $e) {
            if (function_exists('error_log')) error_log('JBG Quiz submit fatal: '.$e->getMessage());
            return new \WP_REST_Response(['correct'=>false, 'message'=>'internal_error'], 500);
        }
    }
}
