<?php
/**
 * Plugin Name: InstaWP Integration
 * Plugin URI: https://instawp.com
 * Description: A comprehensive WordPress integration plugin for InstaWP that provides enhanced functionality, seamless integration, WooCommerce support, and standalone site creation tools.
 * Version: 2.0.0
 * Author: InstaWP
 * Author URI: https://instawp.com
 * Text Domain: iwp-wp-integration
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
define('IWP_VERSION', '2.0.0');
define('IWP_PLUGIN_FILE', __FILE__);
define('IWP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('IWP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IWP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include the autoloader
require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-autoloader.php';

// Declare HPOS compatibility for WooCommerce integration
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Initialize the plugin
function iwp_init() {
    // Load core classes (needed by both admin and frontend)
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-installer.php';
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-api-client.php';
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-logger.php';
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-service.php';
    
    // Set default settings if this is a fresh installation or settings are missing
    $options = get_option('iwp_options', array());
    if (empty($options) || !isset($options['enable_integration'])) {
        IWP_Installer::set_default_settings();
    }
    
    // Initialize admin interface (admin only)
    if (is_admin()) {
        require_once IWP_PLUGIN_PATH . 'includes/admin/class-iwp-settings-page.php';
        require_once IWP_PLUGIN_PATH . 'includes/admin/class-iwp-admin-simple.php';
        
        // Initialize simple admin
        new IWP_Admin_Simple();
        
        // Initialize WooCommerce product integration (admin only)
        if (class_exists('WooCommerce')) {
            require_once IWP_PLUGIN_PATH . 'includes/integrations/woocommerce/class-iwp-woo-product-integration.php';
            new IWP_Woo_Product_Integration();
        }
    }
    
    // Initialize WooCommerce order processing (both admin and frontend)
    if (class_exists('WooCommerce')) {
        require_once IWP_PLUGIN_PATH . 'includes/integrations/woocommerce/class-iwp-woo-order-processor.php';
        require_once IWP_PLUGIN_PATH . 'includes/integrations/woocommerce/class-iwp-woo-hpos.php';
        
        new IWP_Woo_Order_Processor();
        
        // Initialize WooCommerce Subscriptions integration if available
        if (class_exists('WC_Subscriptions') || function_exists('wcs_get_subscription')) {
            require_once IWP_PLUGIN_PATH . 'includes/integrations/woocommerce/class-iwp-woo-subscriptions-integration.php';
            require_once IWP_PLUGIN_PATH . 'includes/integrations/woocommerce/class-iwp-woo-subscription-site-manager.php';
            
            new IWP_Woo_Subscriptions_Integration();
            
            // Schedule health checks
            add_action('iwp_subscription_health_check', array('IWP_Woo_Subscription_Site_Manager', 'run_health_check'));
            IWP_Woo_Subscription_Site_Manager::schedule_health_checks();
        }
    }
    
    // Initialize site manager and other dependencies (needed for shortcode functionality)
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-site-manager.php';
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-database.php';
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-sites-model.php';
    
    // IWP_Service is already loaded above
    
    // Initialize shortcode functionality (both admin and frontend)
    require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-shortcode.php';
    new IWP_Shortcode();
    
    // Initialize frontend (for customer-facing features)
    if (!is_admin() || wp_doing_ajax()) {
        require_once IWP_PLUGIN_PATH . 'includes/frontend/class-iwp-frontend.php';
        
        // Only initialize if WooCommerce is active
        if (class_exists('WooCommerce')) {
            new IWP_Frontend();
        }
    }
    
    // Temporarily disable old admin system to avoid conflicts
    // if (class_exists('IWP_Main')) {
    //     IWP_Main::instance();
    // }
}

// Enhanced debug logging for wpdb::prepare errors
add_action('plugins_loaded', function() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        // Hook into WordPress error handling to catch wpdb::prepare notices
        add_filter('wp_php_error_args', function($error, $type = '', $message = '', $file = '', $line = '') {
            if (is_array($error) && isset($error['message'])) {
                $message = $error['message'];
                $file = isset($error['file']) ? $error['file'] : '';
                $line = isset($error['line']) ? $error['line'] : '';
            }
            
            if (!empty($message) && strpos($message, 'wpdb::prepare was called incorrectly') !== false) {
                error_log('=== IWP DEBUG: WPDB::PREPARE ERROR CAUGHT ===');
                error_log('Message: ' . $message);
                error_log('File: ' . $file);
                error_log('Line: ' . $line);
                error_log('Backtrace:');
                error_log(wp_debug_backtrace_summary());
                error_log('=== END IWP DEBUG ===');
            }
            return $error;
        }, 10, 5);
        
        // Additional debug output for our plugin specifically
        add_action('init', function() {
            if (strpos($_SERVER['REQUEST_URI'] ?? '', 'wp-admin') !== false || 
                strpos($_SERVER['REQUEST_URI'] ?? '', 'wp-json') !== false) {
                error_log('IWP DEBUG: Admin/AJAX request detected, monitoring for wpdb issues');
            }
        });
    }
});

// Hook into plugins_loaded to ensure dependencies are loaded first
add_action('plugins_loaded', 'iwp_init');

// Activation hook
register_activation_hook(__FILE__, 'iwp_activate');

function iwp_activate() {
    // Create necessary database tables or options
    if (class_exists('IWP_Installer')) {
        IWP_Installer::install();
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'iwp_deactivate');

function iwp_deactivate() {
    // Clean up temporary data
    if (class_exists('IWP_Installer')) {
        IWP_Installer::deactivate();
    }
    
    // Unschedule subscription health checks
    if (class_exists('IWP_Woo_Subscription_Site_Manager')) {
        IWP_Woo_Subscription_Site_Manager::unschedule_health_checks();
    }
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'iwp_uninstall');

function iwp_uninstall() {
    // Clean up all plugin data
    if (class_exists('IWP_Installer')) {
        IWP_Installer::uninstall();
    }
}