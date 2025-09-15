<?php
namespace JBG\Ads\Admin;


use JBG\Ads\Util\Fundable;


class MetaBox {
public static function register(): void {
add_meta_box('jbg_ad_finance', __('CPV & Budget','jbg-ads'), [self::class,'render'], 'jbg_ad', 'side', 'high');
}


public static function render($post): void {
$cpv = (int) get_post_meta($post->ID, 'jbg_cpv', true);
$bt = (int) get_post_meta($post->ID, 'jbg_budget_total', true);
$br = (int) get_post_meta($post->ID, 'jbg_budget_remaining', true);
$boost = (int) get_post_meta($post->ID, 'jbg_priority_boost', true);
wp_nonce_field('jbg_ad_finance', 'jbg_ad_finance_nonce');
echo '<p><label>CPV (Toman)</label><br><input type="number" name="jbg_cpv" value="'.esc_attr($cpv).'" min="0" step="1" style="width:100%"></p>';
echo '<p><label>Budget Total</label><br><input type="number" name="jbg_budget_total" value="'.esc_attr($bt).'" min="0" step="1" style="width:100%"></p>';
echo '<p><label>Budget Remaining</label><br><input type="number" name="jbg_budget_remaining" value="'.esc_attr($br).'" min="0" step="1" style="width:100%"></p>';
echo '<p><label>Priority Boost (optional)</label><br><input type="number" name="jbg_priority_boost" value="'.esc_attr($boost).'" min="0" step="1" style="width:100%"></p>';
}


public static function save($post_id, $post): void {
if (!isset($_POST['jbg_ad_finance_nonce']) || !wp_verify_nonce($_POST['jbg_ad_finance_nonce'], 'jbg_ad_finance')) return;
if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
if (!current_user_can('edit_post', $post_id)) return;


$cpv = isset($_POST['jbg_cpv']) ? max(0, (int) $_POST['jbg_cpv']) : 0;
$bt = isset($_POST['jbg_budget_total']) ? max(0, (int) $_POST['jbg_budget_total']) : 0;
$br = isset($_POST['jbg_budget_remaining']) ? max(0, (int) $_POST['jbg_budget_remaining']) : 0;
$boost = isset($_POST['jbg_priority_boost']) ? max(0, (int) $_POST['jbg_priority_boost']) : 0;


update_post_meta($post_id, 'jbg_cpv', $cpv);
update_post_meta($post_id, 'jbg_budget_total', $bt);
update_post_meta($post_id, 'jbg_budget_remaining', $br);
update_post_meta($post_id, 'jbg_priority_boost', $boost);


Fundable::refresh_flag($post_id);
}
}