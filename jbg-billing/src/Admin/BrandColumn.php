<?php
namespace JBG\Billing\Admin;

class BrandColumn {
    public static function columns($cols){
        // موجود: جمع هزینه
        $cols['jbg_spent']  = __('Total Spend (Toman)','jbg-billing');
        // جدید: جمع شارژهای کیف‌پول برند
        $cols['jbg_topups'] = __('Total Top-ups (Toman)','jbg-billing');
        return $cols;
    }

    public static function render($content, $column, $term_id){
        if ($column === 'jbg_spent') {
            $spent = (int) get_term_meta($term_id, 'jbg_brand_spent', true);
            $content = esc_html(number_format_i18n($spent));
        } elseif ($column === 'jbg_topups') {
            $sum = self::calc_topups_for_brand((int)$term_id);
            $content = esc_html(number_format_i18n($sum));
        }
        return $content;
    }

    /**
     * جمع شارژهای انجام‌شده برای یک برند از روی لاگ کیف‌پول اسپانسرها.
     * ساختار لاگ: user_meta('jbg_wallet_txn') = [
     *   ['time'=>ts,'brand_id'=>int,'amount'=>+/-int,'reason'=>'topup|deduct|adjust', ...]
     * ]
     */
    private static function calc_topups_for_brand(int $brand_id): int {
        if ($brand_id <= 0) return 0;

        // کاربران اسپانسر مرتبط با این برند
        $users = get_users([
            'meta_key'   => 'jbg_sponsor_brand_ids',
            'meta_value' => (string) $brand_id,
            'compare'    => 'LIKE',
            'fields'     => ['ID'],
            'number'     => 500,
        ]);
        if (empty($users)) return 0;

        $total = 0;
        foreach ($users as $u) {
            $log = get_user_meta((int)$u->ID, 'jbg_wallet_txn', true);
            if (!is_array($log)) continue;

            foreach ($log as $tx) {
                $tx_brand  = (int) ($tx['brand_id'] ?? 0);
                $tx_amount = (int) ($tx['amount'] ?? 0);
                $tx_reason = (string) ($tx['reason'] ?? '');
                if ($tx_brand === $brand_id && $tx_reason === 'topup' && $tx_amount > 0) {
                    $total += $tx_amount;
                }
            }
        }
        return $total;
    }
}
