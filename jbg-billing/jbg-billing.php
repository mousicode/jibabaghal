<?php
/**
* Plugin Name: JBG Billing
* Description: Deducts CPV from Ad budget when a user passes the quiz. Idempotent per (user, ad). Records logs.
* Version: 0.1.0
* Requires at least: 6.0
* Requires PHP: 7.4
* Author: JibBaghal
* License: GPLv2 or later
* Text Domain: jbg-billing
*/


if (!defined('ABSPATH')) { exit; }


if (!defined('JBG_BILL_FILE')) define('JBG_BILL_FILE', __FILE__);
if (!defined('JBG_BILL_DIR')) define('JBG_BILL_DIR', plugin_dir_path(__FILE__));
if (!defined('JBG_BILL_URL')) define('JBG_BILL_URL', plugin_dir_url(__FILE__));


spl_autoload_register(function($class){
if (strpos($class, 'JBG\\Billing\\') !== 0) return;
$rel = str_replace(['JBG\\Billing\\','\\'], ['', '/'], $class);
$path = JBG_BILL_DIR . 'src/' . $rel . '.php';
if (file_exists($path)) require_once $path;
});


add_action('plugins_loaded', function(){
if (!defined('JBG_CORE_VERSION')) {
add_action('admin_notices', function(){
echo '<div class="notice notice-error"><p><strong>JBG Billing</strong> requires <strong>JBG Core</strong> to be active.</p></div>';
});
return;
}
JBG\Billing\Bootstrap::init();
});


register_activation_hook(__FILE__, ['JBG\\Billing\\Bootstrap','activate']);