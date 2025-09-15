<?php
/**
* Plugin Name: JBG Quiz
* Description: Simple 4-choice quiz for Ad posts; unlocks after watched_ok and records pass/fail via REST.
* Version: 0.1.0
* Requires at least: 6.0
* Requires PHP: 7.4
* Author: JibBaghal
* License: GPLv2 or later
* Text Domain: jbg-quiz
*/


if (!defined('ABSPATH')) { exit; }


if (!defined('JBG_QUIZ_FILE')) define('JBG_QUIZ_FILE', __FILE__);
if (!defined('JBG_QUIZ_DIR')) define('JBG_QUIZ_DIR', plugin_dir_path(__FILE__));
if (!defined('JBG_QUIZ_URL')) define('JBG_QUIZ_URL', plugin_dir_url(__FILE__));


spl_autoload_register(function($class){
if (strpos($class, 'JBG\\Quiz\\') !== 0) return;
$rel = str_replace(['JBG\\Quiz\\','\\'], ['', '/'], $class);
$path = JBG_QUIZ_DIR . 'src/' . $rel . '.php';
if (file_exists($path)) require_once $path;
});


add_action('plugins_loaded', function(){
if (!defined('JBG_CORE_VERSION')) {
add_action('admin_notices', function(){
echo '<div class="notice notice-error"><p><strong>JBG Quiz</strong> requires <strong>JBG Core</strong> to be active.</p></div>';
});
return;
}
JBG\Quiz\Bootstrap::init();
});


register_activation_hook(__FILE__, ['JBG\\Quiz\\Bootstrap','activate']);
register_deactivation_hook(__FILE__, ['JBG\\Quiz\\Bootstrap','deactivate']);