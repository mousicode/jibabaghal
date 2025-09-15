<?php
namespace JBG\Billing\Admin;

class BrandColumn {
    public static function columns($cols){
        $cols['jbg_spent'] = __('Total Spend (Toman)','jbg-billing');
        return $cols;
    }
    public static function render($content, $column, $term_id){
        if ($column === 'jbg_spent') {
            $spent = (int) get_term_meta($term_id, 'jbg_brand_spent', true);
            $content = esc_html(number_format_i18n($spent));
        }
        return $content;
    }
}
