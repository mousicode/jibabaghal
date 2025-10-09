<?php
namespace JBG\Ads\Rest;

if (!defined('ABSPATH')) exit;

use JBG\Ads\Wallet\Wallet;

class WalletController
{
    public static function register_routes(): void {
        register_rest_route('jbg/v1', '/wallet/initiate', [
            'methods'             => 'POST',
            'permission_callback' => function(){ return is_user_logged_in() && current_user_can('jbg_view_reports'); },
            'callback'            => [self::class, 'initiate'],
        ]);

        register_rest_route('jbg/v1', '/wallet/callback', [
            'methods'             => 'POST',
            'permission_callback' => '__return_true', // کال‌بک درگاه باید بدون لاگین کار کند
            'callback'            => [self::class, 'callback'],
        ]);
    }

    /** شروع پرداخت: یک txn می‌سازد و اجازه می‌دهد افزونهٔ درگاه URL پرداخت را بدهد */
    public static function initiate(\WP_REST_Request $req): \WP_REST_Response {
        $uid = get_current_user_id();
        $amount  = max(0, (int) ($req->get_param('amount') ?? 0));
        $brand_id= max(0, (int) ($req->get_param('brand_id') ?? 0));
        if ($amount <= 0 || $brand_id <= 0) {
            return new \WP_REST_Response(['ok'=>false,'message'=>'مبلغ و برند نامعتبر است.'], 400);
        }

        $txn = [
            'id'       => 'TXN-'.wp_generate_password(10,false,false),
            'user_id'  => $uid,
            'brand_id' => $brand_id,
            'amount'   => $amount,
            'status'   => 'pending',
            'time'     => time(),
        ];

        // ذخیرهٔ موقت txn در متای کاربر (برای سادگی – اگر خواستید جدول بسازیم)
        $pending = get_user_meta($uid, 'jbg_wallet_pending', true);
        if (!is_array($pending)) $pending = [];
        $pending[$txn['id']] = $txn;
        update_user_meta($uid, 'jbg_wallet_pending', $pending);

        // به درگاه: از طریق فیلتر بیرونی (افزونهٔ درگاه شما باید این را بسازد)
        $redirect = apply_filters('jbg_wallet_payment_redirect', '', $txn);
        if (!$redirect) {
            // اگر درگاه فیلتر نداد، پیام راهنما
            return new \WP_REST_Response([
                'ok'=>false,
                'message'=>'درگاه پرداخت متصل نیست. لطفاً فیلتر jbg_wallet_payment_redirect را در افزونهٔ درگاه پیاده‌سازی کنید.'
            ], 200);
        }

        return new \WP_REST_Response(['ok'=>true,'redirect'=>$redirect,'txn_id'=>$txn['id']], 200);
    }

    /** کال‌بک درگاه: افزونهٔ درگاه باید با فیلتر verify تراکنش را تایید کند */
    public static function callback(\WP_REST_Request $req): \WP_REST_Response {
        $txn_id = (string) ($req->get_param('txn_id') ?? '');
        if ($txn_id === '') return new \WP_REST_Response(['ok'=>false,'message'=>'txn_id لازم است.'], 400);

        // باید پیدا کنیم txn مربوط به کدام کاربر است:
        $user = self::locate_user_by_txn($txn_id);
        if (!$user) return new \WP_REST_Response(['ok'=>false,'message'=>'تراکنش یافت نشد.'], 404);

        $uid = (int) $user->ID;
        $pending = get_user_meta($uid, 'jbg_wallet_pending', true);
        $txn = is_array($pending) && isset($pending[$txn_id]) ? $pending[$txn_id] : null;
        if (!$txn) return new \WP_REST_Response(['ok'=>false,'message'=>'تراکنش معتبر نیست.'], 404);

        // تایید پرداخت با فیلتر
        $ok = (bool) apply_filters('jbg_wallet_payment_verify', false, $req->get_params(), $txn);
        if (!$ok) return new \WP_REST_Response(['ok'=>false,'message'=>'پرداخت تایید نشد.'], 400);

        // شارژ
        Wallet::adjust($uid, (int)$txn['brand_id'], (int)$txn['amount'], 'topup', 'gateway_callback', $txn_id);

        // تمیزکاری: از pending حذف کنیم
        unset($pending[$txn_id]);
        update_user_meta($uid, 'jbg_wallet_pending', $pending);

        return new \WP_REST_Response(['ok'=>true], 200);
    }

    private static function locate_user_by_txn(string $txn_id) {
        $users = get_users([
            'meta_key'     => 'jbg_wallet_pending',
            'meta_compare' => 'EXISTS',
            'fields'       => ['ID'],
            'number'       => 200,
        ]);
        foreach ($users as $u) {
            $p = get_user_meta($u->ID, 'jbg_wallet_pending', true);
            if (is_array($p) && isset($p[$txn_id])) return $u;
        }
        return null;
    }
}
