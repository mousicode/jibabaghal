<?php
namespace JBG\Ads\Rest;

if (!defined('ABSPATH')) exit;

/**
 * بررسی اعتبار کد تخفیف ساخته‌شده از امتیازها
 * GET /wp-json/jbg/v1/coupon/check?code=JBG-XXXX
 * دسترسی: کاربر لاگین که اسپانسر است یا capability گزارش دارد
 */
class CouponCheckController {
    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/coupon/check', [
            'methods'  => 'GET',
            'callback' => [self::class, 'check'],
            'permission_callback' => function () {
                if (!is_user_logged_in()) return false;
                $u = wp_get_current_user();
                return current_user_can('jbg_view_reports') || in_array('jbg_sponsor', (array) $u->roles, true);
            },
        ]);
    }

    /**
     * نرمال‌سازی کد: حذف فاصله/RTL، تبدیل اعداد فارسی/عربی به لاتین، یکسان‌سازی خط تیره
     */
    private static function normalize_code(string $raw): string {
        $s = trim($raw);

        // حذف کاراکترهای نامرئی RTL/LTR و ZWNJ
        $s = preg_replace('/[\x{200C}\x{200D}\x{200E}\x{200F}\x{061C}]/u', '', $s);

        // یکسان‌سازی انواع dash به hyphen-minus
        $s = strtr($s, [
            '–' => '-', // en dash
            '—' => '-', // em dash
            '−' => '-', // minus sign
            '-' => '-', // non-breaking hyphen
        ]);

        // تبدیل ارقام فارسی و عربی به لاتین
        $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $en = ['0','1','2','3','4','5','6','7','8','9'];
        $s = str_replace($fa, $en, $s);
        $s = str_replace($ar, $en, $s);

        // حذف فاصله‌های میانی
        $s = preg_replace('/\s+/', '', $s);

        // نرمال‌سازی ووکامرس
        if (function_exists('wc_format_coupon_code')) {
            $s = wc_format_coupon_code($s);
        } else {
            $s = strtolower($s);
        }
        return $s;
    }

    public static function check(\WP_REST_Request $req): \WP_REST_Response {
        $raw = (string) ($req->get_param('code') ?? '');
        if ($raw === '') {
            return new \WP_REST_Response(['ok' => false, 'reason' => 'missing_code'], 400);
        }

        if (!class_exists('\\WC_Coupon')) {
            return new \WP_REST_Response(['ok' => false, 'reason' => 'woocommerce_missing'], 400);
        }

        $code = self::normalize_code($raw);

        // پیدا کردن کوپن
        $coupon_id = function_exists('wc_get_coupon_id_by_code') ? wc_get_coupon_id_by_code($code) : 0;
        if (!$coupon_id) {
            return new \WP_REST_Response(['ok'=>false,'reason'=>'not_found','code'=>$code], 404);
        }

        $coupon   = new \WC_Coupon($coupon_id);
        $status   = get_post_status($coupon_id);
        $amount   = (float) $coupon->get_amount();
        $expires  = $coupon->get_date_expires();
        $expired  = $expires ? ($expires->getTimestamp() < time()) : false;
        $usage_limit = (int) $coupon->get_usage_limit();
        $usage_count = (int) $coupon->get_usage_count();
        $used_up     = ($usage_limit > 0 && $usage_count >= $usage_limit);

        $ok = ($status === 'publish') && !$expired && !$used_up;

        return new \WP_REST_Response([
            'ok'          => $ok,
            'code'        => $code,
            'amount'      => $amount,
            'expired'     => $expired,
            'expiry'      => $expires ? $expires->date_i18n('Y-m-d') : null,
            'status'      => $status,
            'usage_limit' => $usage_limit,
            'usage_count' => $usage_count,
            'used_up'     => $used_up,
        ], 200);
    }
}
