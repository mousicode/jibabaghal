<?php
/**
* Plugin Name: JBG Player
* Description: Controlled video player for Ad posts: disables seeking, tracks watch-time, and marks watched_ok via REST.
* Version: 0.1.0
* Requires at least: 6.0
* Requires PHP: 7.4
* Author: JibBaghal
* License: GPLv2 or later
* Text Domain: jbg-player
*/


if (!defined('ABSPATH')) { exit; }


// Require Core
add_action('plugins_loaded', function(){
if (!defined('JBG_CORE_VERSION')) {
add_action('admin_notices', function(){
echo '<div class="notice notice-error"><p><strong>JBG Player</strong> requires <strong>JBG Core</strong> to be active.</p></div>';
});
return;
}
JBG\Player\Bootstrap::init();
});


if (!defined('JBG_PLAYER_FILE')) define('JBG_PLAYER_FILE', __FILE__);
if (!defined('JBG_PLAYER_DIR')) define('JBG_PLAYER_DIR', plugin_dir_path(__FILE__));
if (!defined('JBG_PLAYER_URL')) define('JBG_PLAYER_URL', plugin_dir_url(__FILE__));


spl_autoload_register(function($class){
if (strpos($class, 'JBG\\Player\\') !== 0) return;
$rel = str_replace(['JBG\\Player\\','\\'], ['', '/'], $class);
$path = JBG_PLAYER_DIR . 'src/' . $rel . '.php';
if (file_exists($path)) require_once $path;
});


register_activation_hook(__FILE__, ['JBG\\Player\\Bootstrap','activate']);
register_deactivation_hook(__FILE__, ['JBG\\Player\\Bootstrap','deactivate']);