<?php
namespace JBG\Ads\Rest;

if (!defined('ABSPATH')) exit;

/**
 * بررسی اعتبار کد تخفیف ساخته‌شده از امتیازها
 * مسیر: /wp-json/jbg/v1/coupon/check?code=JBG-XXXX
 */
class CouponCheckController {
    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/coupon/check', [
            'methods'  => 'GET',
            'callback' => [self::class, 'check'],
            'permission_callback' => function () {
                return is_user_logged_in() && current_user_can('jbg_view_reports');
            },
        ]);
    }

    public static function check(\WP_REST_Request $req): \WP_REST_Response {
        $code = sanitize_text_field($req->get_param('code'));
        if (!$code) {
            return new \WP_REST_Response(['ok' => false, 'reason' => 'missing_code'], 400);
        }

        if (!class_exists('\\WC_Coupon')) {
            return new \WP_REST_Response(['ok' => false, 'reason' => 'woocommerce_missing'], 400);
        }

        $coupon = new \WC_Coupon($code);
        if (!$coupon->get_id()) {
            return new \WP_REST_Response(['ok' => false, 'reason' => 'not_found'], 404);
        }

        $amount = (float) $coupon->get_amount();
        $expires = $coupon->get_date_expires();
        $expired = $expires && $expires->getTimestamp() < time();

        return new \WP_REST_Response([
            'ok'       => !$expired,
            'code'     => $code,
            'amount'   => $amount,
            'expired'  => $expired,
            'expiry'   => $expires ? $expires->date_i18n('Y-m-d') : null,
        ], 200);
    }
}
