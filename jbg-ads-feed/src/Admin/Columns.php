<?php
namespace JBG\Ads\Admin;

class Columns {

    public static function columns(array $cols): array {
        // ستون‌های پیش‌فرض را نگه می‌داریم و ستون‌های خودمان را اضافه می‌کنیم
        $cols['jbg_cpv']      = __('CPV', 'jbg-ads');
        $cols['jbg_budget']   = __('Budget Remaining', 'jbg-ads');
        $cols['jbg_views']    = __('Views', 'jbg-ads');
        return $cols;
    }

    public static function render(string $col, int $post_id): void {
        switch ($col) {
            case 'jbg_cpv':
                echo esc_html(number_format_i18n((int) get_post_meta($post_id, 'jbg_cpv', true)));
                break;

            case 'jbg_budget':
                echo esc_html(number_format_i18n((int) get_post_meta($post_id, 'jbg_budget_remaining', true)));
                break;

            case 'jbg_views':
                echo esc_html(number_format_i18n((int) get_post_meta($post_id, 'jbg_views_total', true)));
                break;
        }
    }

    public static function sortable(array $cols): array {
        $cols['jbg_cpv']    = 'jbg_cpv';
        $cols['jbg_budget'] = 'jbg_budget_remaining';
        $cols['jbg_views']  = 'jbg_views_total';
        return $cols;
    }

    /**
     * سورت‌شدن بر پایهٔ متافیلدها را هندل می‌کند.
     */
    public static function handle_sorting($query): void {
        if (!is_admin() || !$query->is_main_query()) return;
        if ($query->get('post_type') !== 'jbg_ad') return;

        $orderby = $query->get('orderby');
        if (in_array($orderby, ['jbg_cpv','jbg_budget_remaining','jbg_views_total'], true)) {
            $query->set('meta_key', $orderby);
            $query->set('orderby', 'meta_value_num');
        }
    }
}
