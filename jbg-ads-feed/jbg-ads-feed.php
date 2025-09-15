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

if (!defined('ABSPATH')) { exit; }

if (!defined('JBG_ADS_VERSION')) define('JBG_ADS_VERSION', '0.1.0');
if (!defined('JBG_ADS_FILE'))    define('JBG_ADS_FILE', __FILE__);
if (!defined('JBG_ADS_DIR'))     define('JBG_ADS_DIR', plugin_dir_path(__FILE__));
if (!defined('JBG_ADS_URL'))     define('JBG_ADS_URL', plugin_dir_url(__FILE__));

// ── PSR-4 lite autoloader for this plugin's namespace ──────────────────────────
spl_autoload_register(function($class){
    if (strpos($class, 'JBG\\Ads\\') !== 0) return;
    $rel  = str_replace(['JBG\\Ads\\', '\\'], ['', '/'], $class);
    $path = JBG_ADS_DIR . 'src/' . $rel . '.php';
    if (file_exists($path)) require_once $path;
});

// ── Runtime bootstrap (after all plugins are loaded) ───────────────────────────
add_action('plugins_loaded', function () {
    // Check dependency AFTER other plugins loaded
    if (!defined('JBG_CORE_VERSION')) {
        add_action('admin_notices', function(){
            echo '<div class="notice notice-error"><p><strong>JBG Ads &amp; Feed</strong> requires <strong>JBG Core</strong> to be active. Please activate Core first.</p></div>';
        });
        return;
    }
    JBG\Ads\Bootstrap::init();
});

// ── Activation: enforce dependency then run our activator ──────────────────────
register_activation_hook(__FILE__, function () {
    // Make sure is_plugin_active is available
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if (!is_plugin_active('jbg-core/jbg-core.php')) {
        // Deactivate ourselves and abort with a helpful message
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            'JBG Ads &amp; Feed requires the <strong>JBG Core</strong> plugin. ' .
            'Activate Core first, then activate Ads &amp; Feed.',
            'Plugin dependency check',
            ['back_link' => true]
        );
    }

    // Dependency satisfied → call our activation routine
    if (class_exists('JBG\\Ads\\Bootstrap')) {
        JBG\Ads\Bootstrap::activate();
    }
});

// ── Deactivation hook ──────────────────────────────────────────────────────────
register_deactivation_hook(__FILE__, function () {
    if (class_exists('JBG\\Ads\\Bootstrap')) {
        JBG\Ads\Bootstrap::deactivate();
    }
});
