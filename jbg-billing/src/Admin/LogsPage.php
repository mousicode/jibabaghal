<?php
namespace JBG\Billing\Admin;


class LogsPage {
public static function register(): void {
add_submenu_page(
'edit.php?post_type=jbg_ad',
__('Billing Logs','jbg-billing'),
__('Billing Logs','jbg-billing'),
'manage_options',
'jbg-billing-logs',
[self::class, 'render']
);
}


public static function render(): void {
if (!current_user_can('manage_options')) { wp_die(__('You do not have permission.')); }
global $wpdb;
$table = $wpdb->prefix . 'jbg_views';
$rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 100", ARRAY_A);
echo '<div class="wrap"><h1>'.esc_html__('Billing Logs','jbg-billing').'</h1>';
if (!$rows) { echo '<p>No logs yet.</p></div>'; return; }
echo '<table class="widefat fixed striped"><thead><tr>'
. '<th>ID</th><th>Ad ID</th><th>User ID</th><th>Amount</th><th>When</th><th>IP</th><th>UA</th>'
. '</tr></thead><tbody>';
foreach ($rows as $r) {
echo '<tr>'
. '<td>'.esc_html($r['id']).'</td>'
. '<td>'.esc_html($r['ad_id']).'</td>'
. '<td>'.esc_html($r['user_id']).'</td>'
. '<td>'.esc_html($r['amount']).'</td>'
. '<td>'.esc_html($r['created_at']).'</td>'
. '<td>'.esc_html($r['ip']).'</td>'
. '<td>'.esc_html($r['ua']).'</td>'
. '</tr>';
}
echo '</tbody></table></div>';
}
}