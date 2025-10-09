<?php
namespace JBG\Ads\Wallet;

if (!defined('ABSPATH')) exit;

class Wallet
{
    /**
     * ساختار ذخیره‌سازی:
     * user_meta('jbg_wallet_balances') = [
     *   <brand_id:int> => ['balance' => int_toman]
     * ]
     *
     * user_meta('jbg_wallet_txn') = لیست آخرین تراکنش‌ها (حداکثر 200 آیتم):
     *   [
     *     ['time'=>ts,'brand_id'=>int,'amount'=>+/-int,'reason'=>'topup|deduct|adjust','note'=>string,'txn_id'=>string]
     *   ]
     */

    public static function get_balances(int $user_id): array {
        $raw = get_user_meta($user_id, 'jbg_wallet_balances', true);
        return is_array($raw) ? $raw : [];
    }

    public static function get_balance(int $user_id, int $brand_id): int {
        $all = self::get_balances($user_id);
        return (int) ($all[$brand_id]['balance'] ?? 0);
    }

    public static function set_balance(int $user_id, int $brand_id, int $amount): void {
        $amount = (int) $amount;
        $all = self::get_balances($user_id);
        $all[$brand_id] = ['balance' => $amount];
        update_user_meta($user_id, 'jbg_wallet_balances', $all);
    }

    public static function adjust(int $user_id, int $brand_id, int $delta, string $reason, string $note = '', string $txn_id = ''): bool {
        $delta = (int) $delta;
        if ($user_id <= 0 || $brand_id <= 0 || $delta === 0) return false;

        $cur = self::get_balance($user_id, $brand_id);
        $new = $cur + $delta; // اجازهٔ منفی (اشاره به بدهی)؛ اگر نمی‌خواهید، max(0, ...) بگذارید.
        self::set_balance($user_id, $brand_id, $new);

        $log = get_user_meta($user_id, 'jbg_wallet_txn', true);
        if (!is_array($log)) $log = [];
        $log[] = [
            'time'     => time(),
            'brand_id' => (int) $brand_id,
            'amount'   => (int) $delta,
            'reason'   => (string) $reason, // topup|deduct|adjust
            'note'     => (string) $note,
            'txn_id'   => (string) $txn_id,
        ];
        if (count($log) > 200) $log = array_slice($log, -200);
        update_user_meta($user_id, 'jbg_wallet_txn', $log);
        return true;
    }

    /** کسر بر اساس CPV هنگام رخداد jbg_billed */
    public static function deduct_on_billed(int $user_id, int $ad_id): void {
        // ad_id → برند(ها)
        $brand_ids = wp_get_post_terms($ad_id, 'jbg_brand', ['fields'=>'ids']);
        if (is_wp_error($brand_ids) || empty($brand_ids)) return;

        // CPV
        $cpv = (int) get_post_meta($ad_id, 'jbg_cpv', true);
        if ($cpv <= 0) return;

        // اسپانسر مالک این برند کیست؟ (فرض: برای هر برند، یک اسپانسر مشخص شده)
        foreach ($brand_ids as $brand_id) {
            $sponsor_id = self::find_brand_sponsor((int) $brand_id);
            if ($sponsor_id > 0) {
                // کسر از کیف‌پول اسپانسر
                self::adjust($sponsor_id, (int) $brand_id, -1 * $cpv, 'deduct', 'billed view of ad#'.$ad_id);
            }
        }
    }

    /** پیدا کردن اسپانسر اصلی یک برند: اولین کاربری که brand_id در meta اش هست */
    public static function find_brand_sponsor(int $brand_id): int {
        $users = get_users([
            'meta_key'   => 'jbg_sponsor_brand_ids',
            'meta_value' => (string) $brand_id,
            'compare'    => 'LIKE',
            'fields'     => ['ID'],
            'number'     => 2,
        ]);
        if (empty($users)) return 0;
        // اگر بیش از یکی برگشت، اولی را برمی‌داریم (می‌توانید بعدها تعیین اسپانسر اصلی را اضافه کنید)
        return (int) $users[0]->ID;
    }
}
