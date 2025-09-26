<?php
/**
 * Plugin Name: JBG Ads & Feed
 * Description: Ad CPT + CPV/Budget meta + prioritized feed (sorted by CPV) for JibBaghal.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: JibBaghal
 * License: GPLv2 or later
 * Text Domain: jbg-ads
 */

if (!defined('ABSPATH')) exit;

if (!defined('JBG_ADS_VERSION')) define('JBG_ADS_VERSION', '0.1.0');
if (!defined('JBG_ADS_FILE'))    define('JBG_ADS_FILE', __FILE__);
if (!defined('JBG_ADS_DIR'))     define('JBG_ADS_DIR', plugin_dir_path(__FILE__));
if (!defined('JBG_ADS_URL'))     define('JBG_ADS_URL', plugin_dir_url(__FILE__));

/** PSR-4-lite autoloader برای فضای‌نام JBG\Ads\* */
spl_autoload_register(function($class){
    if (strpos($class, 'JBG\\Ads\\') !== 0) return;
    $rel  = str_replace(['JBG\\Ads\\', '\\'], ['', '/'], $class);
    $path = JBG_ADS_DIR . 'src/' . $rel . '.php';
    if (is_file($path)) require_once $path;
});

/** پس از بارگذاری همه‌ی افزونه‌ها اجرا می‌شود */
add_action('plugins_loaded', function () {

    // اگر JBG Core فعال نیست، فقط "اخطار" بده ولی مانع اجرا نشو
    if (!defined('JBG_CORE_VERSION')) {
        add_action('admin_notices', function(){
            echo '<div class="notice notice-warning"><p><strong>JBG Ads &amp; Feed:</strong> بهتره افزونه <strong>JBG Core</strong> هم فعال باشه، ولی بدونش هم اجرا می‌شیم.</p></div>';
        });
    }

    // اطمینان از لود شدن Bootstrap قبل از استفاده
    $bootstrap_file = JBG_ADS_DIR . 'src/Bootstrap.php';
    if (is_file($bootstrap_file)) {
        require_once $bootstrap_file;
    }

    if (class_exists('\\JBG\\Ads\\Bootstrap')) {
        \JBG\Ads\Bootstrap::init();
    } else {
        if (function_exists('error_log')) {
            error_log('JBG Ads & Feed: Bootstrap class not found. Check "src/Bootstrap.php" and the namespace "JBG\\Ads".');
        }
        add_action('admin_notices', function(){
            echo '<div class="notice notice-error"><p>خطا: کلاس <code>JBG\\Ads\\Bootstrap</code> پیدا نشد. مسیر/حروف بزرگ-کوچک فایل <code>src/Bootstrap.php</code> را بررسی کنید.</p></div>';
        });
    }
});

/** Activation */
register_activation_hook(__FILE__, function () {
    // برای اطمینان، Bootstrap را لود کن
    $bootstrap_file = JBG_ADS_DIR . 'src/Bootstrap.php';
    if (is_file($bootstrap_file)) require_once $bootstrap_file;

    if (class_exists('\\JBG\\Ads\\Bootstrap')) {
        \JBG\Ads\Bootstrap::activate();
    }
});

/** Deactivation */
register_deactivation_hook(__FILE__, function () {
    if (class_exists('\\JBG\\Ads\\Bootstrap')) {
        \JBG\Ads\Bootstrap::deactivate();
    }
});
