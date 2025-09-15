<?php
namespace JBG\Quiz\Rest;

class QuizController {
    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/quiz/submit', [
            'methods'  => 'POST',
            'callback' => [self::class, 'handle_submit'],
            'permission_callback' => '__return_true',
        ]);
    }

    // مبدل اعداد فارسی/عربی به لاتین
    private static function to_ascii_int($val): int {
        $map = [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            "\xE2\x80\x8E"=>'', "\xE2\x80\x8F"=>'' // LRM/RLM
        ];
        $s = strtr((string)$val, $map);
        $s = preg_replace('/[^0-9]/', '', $s);
        return (int) $s;
    }

    public static function handle_submit(\WP_REST_Request $req) {
        try {
            $p = $req->get_json_params();
            if (empty($p)) $p = $req->get_params();

            // ad_id و answer را امن بخوان
            $ad_id = self::to_ascii_int($p['ad_id']  ?? 0);
            $ans   = self::to_ascii_int($p['answer'] ?? ($p['ans'] ?? ($p['choice'] ?? 0)));

            if ($ad_id <= 0 || $ans < 1 || $ans > 4) {
                return new \WP_REST_Response(['correct'=>false,'message'=>'پارامتر(های) نامعتبر: ad_id/answer'], 400);
            }
            if (get_post_type($ad_id) !== 'jbg_ad') {
                return new \WP_REST_Response(['correct'=>false,'message'=>'آگهی معتبر نیست.'], 400);
            }

            // مقدار صحیح را از متا بخوان و به لاتین تبدیل/کلمپ کن
            $rawCorrect = get_post_meta($ad_id, 'jbg_quiz_correct', true);
            $correct = self::to_ascii_int($rawCorrect);
            if ($correct < 1 || $correct > 4) $correct = 1;

            $ok = ($ans === $correct);

            if ($ok) {
                do_action('jbg_quiz_passed', get_current_user_id(), $ad_id);
            }

            return new \WP_REST_Response(['correct'=>$ok], 200);

        } catch (\Throwable $e) {
            if (function_exists('error_log')) error_log('JBG Quiz submit fatal: '.$e->getMessage());
            return new \WP_REST_Response(['correct'=>false,'message'=>'internal_error'], 500);
        }
    }
}
