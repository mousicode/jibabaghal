<?php
namespace JBG\Quiz\Rest;

class QuizController {
    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/quiz/submit', [
            'methods'  => 'POST',
            'callback' => [self::class, 'handle_submit'],
            // اگر مایل بودی لاگین اجباری باشد، این خط را به fn() => is_user_logged_in() تغییر بده
            'permission_callback' => '__return_true',
        ]);
    }

    /** نرمال‌سازی اعداد فارسی/عربی به ASCII و تبدیل به int */
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
            // ورودی JSON یا x-www-form-urlencoded
            $p = $req->get_json_params();
            if (empty($p)) $p = $req->get_params();

            $ad_id = self::to_ascii_int($p['ad_id']  ?? 0);
            $ans   = self::to_ascii_int($p['answer'] ?? ($p['ans'] ?? ($p['choice'] ?? 0)));

            if ($ad_id <= 0 || $ans < 1 || $ans > 4) {
                return new \WP_REST_Response(['correct'=>false,'message'=>'پارامتر(های) نامعتبر: ad_id/answer'], 400);
            }
            if (get_post_type($ad_id) !== 'jbg_ad') {
                return new \WP_REST_Response(['correct'=>false,'message'=>'آگهی معتبر نیست.'], 400);
            }

            // کلید پاسخ درست
            $rawCorrect = get_post_meta($ad_id, 'jbg_quiz_ans', true);
            $correct    = self::to_ascii_int($rawCorrect);
            if ($correct < 1 || $correct > 4) $correct = 1;

            $ok      = ($ans === $correct);
            $user_id = get_current_user_id();

            // مقدار امتیاز تعریف‌شده برای این ویدیو (در صورت تعریف نُرم: jbg_points)
            $points_defined = (int) get_post_meta($ad_id, 'jbg_points', true);
            if ($points_defined < 0) $points_defined = 0;

            // آیا قبلاً برای این آگهی به این کاربر امتیاز داده شده؟
            $already_awarded = ($user_id > 0)
                ? (int) get_user_meta($user_id, 'jbg_points_awarded_' . $ad_id, true)
                : 0;

            // اگر پاسخ درست است، ایونت را فایر کن تا:
            // - Access::promote_after_pass مرحله‌ی بعد را باز کند
            // - Points::on_quiz_passed امتیاز را (در صورت عدم پرداخت قبلی) ثبت کند
            if ($ok) {
                do_action('jbg_quiz_passed', $user_id, $ad_id);
            }

            // برای UI: مقدار امتیازی که «الان» باید اعلام کنیم
            // (اگر قبلاً برای این آگهی امتیاز گرفته، 0 برگردانیم)
            $awarded_now = ($ok && $user_id > 0 && !$already_awarded) ? $points_defined : 0;

            // در پاسخ می‌توانیم total تقریبی را هم بدهیم (اختیاری):
            $total_before = ($user_id > 0) ? (int) get_user_meta($user_id, 'jbg_points_total', true) : 0;
            $total_after  = $total_before + $awarded_now;

            return new \WP_REST_Response([
                'correct'         => $ok,
                'points_defined'  => $points_defined, // امتیاز تعریف‌شده برای این ویدیو
                'points_awarded'  => $awarded_now,    // همان لحظه چقدر اعتبار گرفت
                'points_total'    => $total_after,    // مجموع تقریبی بعد از این پاسخ
                'already_awarded' => (bool) $already_awarded,
            ], 200);

        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('JBG Quiz submit fatal: '.$e->getMessage());
            }
            return new \WP_REST_Response(['correct'=>false,'message'=>'internal_error'], 500);
        }
    }
}
