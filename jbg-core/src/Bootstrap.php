<?php
namespace JBG;


use JBG\Admin\Menu;
use JBG\Roles\Capabilities;
use JBG\Rest\HealthController;
use JBG\Logging\Logger;


class Bootstrap {
public static function init(): void {
// Load i18n if needed
load_plugin_textdomain('jbg-core', false, dirname(plugin_basename(JBG_CORE_FILE)) . '/languages');


// Register admin menu & roles/caps
add_action('init', [Capabilities::class, 'register']);
add_action('admin_menu', [Menu::class, 'register']);


// REST: health
add_action('rest_api_init', [HealthController::class, 'register_routes']);


// Ensure uploads/jbg-logs exists
add_action('init', function(){
$uploads = wp_get_upload_dir();
$dir = trailingslashit($uploads['basedir']) . 'jbg-logs';
if (!file_exists($dir)) wp_mkdir_p($dir);
});
}


public static function activate(): void {
// Create roles/caps now
Capabilities::register();


// Basic options
if (!get_option('jbg_core_db_version')) {
add_option('jbg_core_db_version', 1);
}
}


public static function deactivate(): void {
// Nothing destructive; keep roles/logs
}


public static function uninstall(): void {
// On uninstall, leave logs for audit; only remove options if desired
delete_option('jbg_core_db_version');
}
}