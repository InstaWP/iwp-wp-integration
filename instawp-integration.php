<?php
/**
 * Plugin Name: InstaWP Integration
 * Plugin URI: https://instawp.com
 * Description: A comprehensive WordPress integration plugin for InstaWP that provides enhanced functionality, seamless integration, WooCommerce support, and standalone site creation tools.
 * Version: 2.0.0
 * Author: InstaWP
 * Author URI: https://instawp.com
 * Text Domain: instawp-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IWP_WOO_V2_VERSION', '2.0.0');
define('IWP_WOO_V2_PLUGIN_FILE', __FILE__);
define('IWP_WOO_V2_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('IWP_WOO_V2_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IWP_WOO_V2_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include the autoloader
require_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-autoloader.php';

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Initialize the plugin
function iwp_woo_v2_init() {
    // Load the main plugin class using singleton pattern
    if (class_exists('IWP_Woo_V2_Main')) {
        IWP_Woo_V2_Main::instance();
    }
}

// Hook into plugins_loaded to ensure WooCommerce is loaded first
add_action('plugins_loaded', 'iwp_woo_v2_init');

// Activation hook
register_activation_hook(__FILE__, 'iwp_woo_v2_activate');

function iwp_woo_v2_activate() {
    // Create necessary database tables or options
    if (class_exists('IWP_Woo_V2_Installer')) {
        IWP_Woo_V2_Installer::install();
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'iwp_woo_v2_deactivate');

function iwp_woo_v2_deactivate() {
    // Clean up temporary data
    if (class_exists('IWP_Woo_V2_Installer')) {
        IWP_Woo_V2_Installer::deactivate();
    }
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'iwp_woo_v2_uninstall');

function iwp_woo_v2_uninstall() {
    // Clean up all plugin data
    if (class_exists('IWP_Woo_V2_Installer')) {
        IWP_Woo_V2_Installer::uninstall();
    }
}