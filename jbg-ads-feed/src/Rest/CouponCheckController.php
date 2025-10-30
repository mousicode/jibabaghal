<?php
namespace JBG\Ads\Rest;

if (!defined('ABSPATH')) exit;

/**
 * بررسی اعتبار کد تخفیف
 * GET /wp-json/jbg/v1/coupon/check?code=JBG-XXXX
 * دسترسی: هر کاربر لاگین (شورت‌کد فقط برای لاگین‌ها نمایش داده می‌شود)
 */
class CouponCheckController {
    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/coupon/check', [
            'methods'  => 'GET',
            'callback' => [self::class, 'check'],
            'permission_callback' => function () {
                return is_user_logged_in(); // قبلاً: فقط اسپانسر/قابلیت jbg_view_reports
            },
        ]);
    }

    /** نرمال‌سازی کد: اعداد فارسی/عربی، dash، کاراکترهای RTL، فاصله‌ها */
    private static function normalize_code(string $raw): string {
        $s = trim($raw);
        $s = preg_replace('/[\x{200C}\x{200D}\x{200E}\x{200F}\x{061C}]/u', '', $s); // ZWNJ/ZWB/LRM/RLM/ALM
        $s = strtr($s, ['–'=>'-','—'=>'-','−'=>'-','-'=>'-']); // انواع dash
        $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $en = ['0','1','2','3','4','5','6','7','8','9'];
        $s = str_replace($fa, $en, $s);
        $s = str_replace($ar, $en, $s);
        $s = preg_replace('/\s+/', '', $s);
        return function_exists('wc_format_coupon_code') ? wc_format_coupon_code($s) : strtolower($s);
    }

    public static function check(\WP_REST_Request $req): \WP_REST_Response {
        $raw = (string) ($req->get_param('code') ?? '');
        if ($raw === '') return new \WP_REST_Response(['ok'=>false,'reason'=>'missing_code'], 400);
        if (!class_exists('\\WC_Coupon')) return new \WP_REST_Response(['ok'=>false,'reason'=>'woocommerce_missing'], 400);

        $code = self::normalize_code($raw);
        $id   = function_exists('wc_get_coupon_id_by_code') ? wc_get_coupon_id_by_code($code) : 0;
        if (!$id) return new \WP_REST_Response(['ok'=>false,'reason'=>'not_found','code'=>$code], 404);

        $coupon = new \WC_Coupon($id);
        $status = get_post_status($id);
        $amount = (float) $coupon->get_amount();
        $expires= $coupon->get_date_expires();
        $expired = $expires ? ($expires->getTimestamp() < time()) : false;

        $usage_limit = (int) $coupon->get_usage_limit();
        $usage_count = (int) $coupon->get_usage_count();
        $used_up     = ($usage_limit > 0 && $usage_count >= $usage_limit);

        return new \WP_REST_Response([
            'ok'          => ($status === 'publish') && !$expired && !$used_up,
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
