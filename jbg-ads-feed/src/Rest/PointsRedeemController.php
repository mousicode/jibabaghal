<?php
namespace JBG\Ads\Rest;
if (!defined('ABSPATH')) exit;

use JBG\Ads\Admin\PointsDiscountSettings;
use JBG\Ads\Progress\Points;

class PointsRedeemController {

    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/points/redeem', [
            'methods'  => 'POST',
            'permission_callback' => function(){ return is_user_logged_in(); },
            'callback' => [self::class, 'redeem'],
        ]);
    }

    public static function redeem(\WP_REST_Request $req) {
        $uid = get_current_user_id();
        $cfg = PointsDiscountSettings::get();

        $total = Points::total($uid);               // کل امتیاز کاربر
        if ($total < $cfg['min_points']) {
            return new \WP_REST_Response(['ok'=>false,'reason'=>'not_enough_points','total'=>$total], 400);
        }

        $units   = intdiv($total, $cfg['points_per_unit']); // چند بار می‌تواند تبدیل کند
        if ($units <= 0) {
            return new \WP_REST_Response(['ok'=>false,'reason'=>'below_unit'], 400);
        }

        $percent = min($units * $cfg['percent_per_unit'], $cfg['max_percent']);
        $points_to_deduct = (int)ceil($percent * $cfg['points_per_unit'] / $cfg['percent_per_unit']); // امتیاز متناظر درصد نهایی
        if ($points_to_deduct > $total) $points_to_deduct = $total;

        // کم‌کردن امتیاز
        Points::deduct($uid, $points_to_deduct, 'redeem');

        // ساخت کد
        $code = self::generate_code();

        $created = false;
        $expiry  = (new \DateTimeImmutable('+'.(int)$cfg['expiry_days'].' days', wp_timezone()))->format('Y-m-d');

        if (class_exists('WC_Coupon')) {
            // WooCommerce: ساخت کوپن درصدی یک‌بار مصرف
            $coupon = new \WC_Coupon();
            $coupon->set_code($code);
            $coupon->set_discount_type('percent');
            $coupon->set_amount($percent);
            $coupon->set_usage_limit(1);
            $coupon->set_date_expires($expiry);
            $coupon->save();
            $created = true;
        }

        // ذخیره در user_meta برای نمایش لیست
        $list = (array) get_user_meta($uid, 'jbg_coupons', true);
        $list[] = [
            'code'    => $code,
            'percent' => $percent,
            'points'  => $points_to_deduct,
            'expiry'  => $expiry,
            'wc'      => $created ? 1 : 0,
            'time'    => time(),
        ];
        update_user_meta($uid, 'jbg_coupons', $list);

        return new \WP_REST_Response([
            'ok'      => true,
            'code'    => $code,
            'percent' => $percent,
            'points'  => $points_to_deduct,
            'expiry'  => $expiry,
            'wc'      => $created ? 1 : 0,
            'total'   => Points::total($uid),
        ], 200);
    }

    private static function generate_code(): string {
        $rand = wp_generate_password(10, false, false);
        return 'JBG-' . strtoupper(wp_hash($rand . microtime(true) . wp_rand(), 'auth'));
    }
}
