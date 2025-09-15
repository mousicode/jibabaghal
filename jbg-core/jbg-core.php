<?php
/**
* Plugin Name: JBG Core
* Description: Core library, contracts, security and health for the JibBaghal platform.
* Version: 0.1.0
* Requires at least: 6.0
* Requires PHP: 7.4
* Author: JibBaghal
* License: GPLv2 or later
* Text Domain: jbg-core
*/


if (!defined('ABSPATH')) { exit; }


// ===== Constants =====
if (!defined('JBG_CORE_VERSION')) define('JBG_CORE_VERSION', '0.1.0');
if (!defined('JBG_CORE_FILE')) define('JBG_CORE_FILE', __FILE__);
if (!defined('JBG_CORE_DIR')) define('JBG_CORE_DIR', plugin_dir_path(__FILE__));
if (!defined('JBG_CORE_URL')) define('JBG_CORE_URL', plugin_dir_url(__FILE__));


// ===== Autoloader (PSR-4 lite) =====
spl_autoload_register(function($class){
if (strpos($class, 'JBG\\') !== 0) return;
$rel = str_replace(['JBG\\', '\\'], ['', '/'], $class);
$path = JBG_CORE_DIR . 'src/' . $rel . '.php';
if (file_exists($path)) require_once $path;
});


// ===== Bootstrap =====
register_activation_hook(__FILE__, ['JBG\\Bootstrap', 'activate']);
register_deactivation_hook(__FILE__, ['JBG\\Bootstrap', 'deactivate']);
register_uninstall_hook(__FILE__, ['JBG\\Bootstrap', 'uninstall']);


add_action('plugins_loaded', function(){
JBG\Bootstrap::init();
});