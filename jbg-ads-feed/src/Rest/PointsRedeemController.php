<?php
namespace JBG\Ads\Rest;

if (!defined('ABSPATH')) exit;

use JBG\Ads\Admin\PointsDiscountSettings;
use JBG\Ads\Progress\Points;

/**
 * تبدیل امتیاز کاربر به کد تخفیف ریالی (تومان)
 */
class PointsRedeemController {

    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/points/redeem', [
            'methods'             => 'POST',
            'permission_callback' => function () { return is_user_logged_in(); },
            'callback'            => [self::class, 'redeem'],
        ]);
    }

    public static function redeem(\WP_REST_Request $req): \WP_REST_Response {
        $uid = get_current_user_id();

        // تنظیمات
        $cfg = class_exists('\\JBG\\Ads\\Admin\\PointsDiscountSettings')
            ? PointsDiscountSettings::get()
            : ['points_per_unit'=>1000,'toman_per_unit'=>100000,'min_points'=>1000,'expiry_days'=>7];

        $ppu = max(1,   (int)($cfg['points_per_unit'] ?? 1000));
        $tpu = max(100, (int)($cfg['toman_per_unit']  ?? 100000));
        $min = max(0,   (int)($cfg['min_points']      ?? 1000));
        $exp = max(1,   (int)($cfg['expiry_days']     ?? 7));

        // امتیاز
        $total_points = (int) Points::total($uid);
        if ($total_points < $min) {
            return new \WP_REST_Response([
                'ok'=>false,'reason'=>'not_enough_points','total'=>$total_points,'need'=>$min
            ], 400);
        }

        $units = intdiv($total_points, $ppu);
        if ($units <= 0) {
            return new \WP_REST_Response([
                'ok'=>false,'reason'=>'below_unit','total'=>$total_points,'unit'=>$ppu
            ], 400);
        }

        $amount_toman     = (int) ($units * $tpu);
        $points_to_deduct = (int) ($units * $ppu);
        if ($points_to_deduct > $total_points) $points_to_deduct = $total_points;

        // کسر امتیاز
        Points::deduct($uid, $points_to_deduct, 'تبدیل به کد تخفیف');

        // تاریخ انقضا به‌وقت سایت
        try {
            $expiry_dt = (new \DateTimeImmutable('now', wp_timezone()))->modify('+' . $exp . ' days');
        } catch (\Exception $e) {
            $expiry_dt = new \DateTimeImmutable('+' . $exp . ' days');
        }
        $expiry_str     = $expiry_dt->format('Y-m-d');
        $expiry_for_wc  = $expiry_dt->setTime(23, 59, 59)->getTimestamp(); // انتهای روز به‌صورت timestamp

        // کد
        $code = self::generate_code();

        // ساخت کوپن ووکامرس
        $wc_created = false;
        if (class_exists('\\WC_Coupon')) {
            try {
                $coupon = new \WC_Coupon();
                $coupon->set_code($code);
                $coupon->set_discount_type('fixed_cart');
                $coupon->set_amount((float)$amount_toman);
                $coupon->set_usage_limit(1);
                $coupon->set_date_expires($expiry_for_wc); // اصلاح شد
                $coupon->save();
                $wc_created = true;
            } catch (\Throwable $e) {
                $wc_created = false;
            }
        }

        // ذخیره برای UI
        $list = get_user_meta($uid, 'jbg_coupons', true);
        if (!is_array($list)) $list = [];
        $list[] = [
            'code'   => $code,
            'amount' => $amount_toman,
            'points' => $points_to_deduct,
            'expiry' => $expiry_str,
            'wc'     => $wc_created ? 1 : 0,
            'time'   => time(),
        ];
        update_user_meta($uid, 'jbg_coupons', $list);

        return new \WP_REST_Response([
            'ok'=>true,'code'=>$code,'amount'=>$amount_toman,'points'=>$points_to_deduct,
            'expiry'=>$expiry_str,'wc'=>$wc_created ? 1 : 0, 'total'=>(int) Points::total($uid)
        ], 200);
    }

    private static function generate_code(): string {
        $rand = wp_generate_password(10, false, false);
        $hash = wp_hash($rand . microtime(true) . wp_rand(), 'auth');
        return 'JBG-' . strtoupper(substr(preg_replace('~[^A-Za-z0-9]~', '', $hash), 0, 12));
    }
}
