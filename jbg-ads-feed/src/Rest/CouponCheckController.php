<?php
namespace JBG\Ads\Rest;

if (!defined('ABSPATH')) exit;

/**
 * بررسی اعتبار کد تخفیف ساخته‌شده از امتیازها
 * GET /wp-json/jbg/v1/coupon/check?code=JBG-XXXX
 * دسترسی: فقط اسپانسرها (یا هر کسی که capability مرتبط را دارد)
 */
class CouponCheckController {
    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/coupon/check', [
            'methods'  => 'GET',
            'callback' => [self::class, 'check'],
            'permission_callback' => function () {
                // فقط کاربران لاگین با نقش اسپانسر (یا قابلیت معادل) اجازه دارند
                return is_user_logged_in() && ( current_user_can('jbg_view_reports') || in_array('jbg_sponsor', (array) wp_get_current_user()->roles, true) );
            },
        ]);
    }

    public static function check(\WP_REST_Request $req): \WP_REST_Response {
        $raw = (string) ($req->get_param('code') ?? '');
        $raw = trim($raw);
        if ($raw === '') {
            return new \WP_REST_Response(['ok' => false, 'reason' => 'missing_code'], 400);
        }

        if (!class_exists('\\WC_Coupon')) {
            return new \WP_REST_Response(['ok' => false, 'reason' => 'woocommerce_missing'], 400);
        }

        // نرمال‌سازی دقیق مثل ووکامرس
        if (!function_exists('wc_format_coupon_code')) {
            return new \WP_REST_Response(['ok' => false, 'reason' => 'woocommerce_functions_missing'], 500);
        }
        $code = wc_format_coupon_code($raw); // lower-case و حذف فاصله‌ها
        $coupon_id = function_exists('wc_get_coupon_id_by_code') ? wc_get_coupon_id_by_code($code) : 0;

        if (!$coupon_id) {
            // پیدا نشد
            return new \WP_REST_Response([
                'ok'      => false,
                'reason'  => 'not_found',
                'code'    => $code,
            ], 404);
        }

        $coupon = new \WC_Coupon($coupon_id);

        // وضعیت پایه
        $status   = get_post_status($coupon_id);
        $amount   = (float) $coupon->get_amount();
        $expires  = $coupon->get_date_expires();
        $expired  = $expires ? ($expires->getTimestamp() < time()) : false;

        // محدودیت استفاده
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
