<?php
/**
 * Autoloader for InstaWP Integration Plugin
 *
 * @package IWP
 * @since 0.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Autoloader class
 */
class IWP_Autoloader {

    /**
     * Initialize the autoloader
     */
    public static function init() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * Autoload classes
     *
     * @param string $class_name The class name to load
     */
    public static function autoload($class_name) {
        // Only load classes that start with our prefixes
        if (strpos($class_name, 'IWP_') !== 0) {
            return;
        }

        // Convert class name to file name
        $class_file = str_replace('_', '-', strtolower($class_name));
        $class_file = 'class-' . $class_file . '.php';

        // Define possible paths based on class name patterns
        $paths = array();

        // Core classes
        if (strpos($class_name, 'IWP_Woo_') === false) {
            $paths[] = IWP_PLUGIN_PATH . 'includes/core/';
            $paths[] = IWP_PLUGIN_PATH . 'includes/admin/';
            $paths[] = IWP_PLUGIN_PATH . 'includes/frontend/';
        }
        
        // WooCommerce integration classes
        if (strpos($class_name, 'IWP_Woo_') === 0) {
            $paths[] = IWP_PLUGIN_PATH . 'includes/integrations/woocommerce/';
        }

        // General paths as fallback
        $paths[] = IWP_PLUGIN_PATH . 'includes/';
        $paths[] = IWP_PLUGIN_PATH . 'includes/api/';
        $paths[] = IWP_PLUGIN_PATH . 'includes/integrations/';

        // Try to load the file from each path
        foreach ($paths as $path) {
            $file_path = $path . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }

        // Legacy support: Check if it's an old class name pattern
        if (strpos($class_name, 'IWP_Woo_V2_') === 0) {
            // Try to map old class names to new ones
            $new_class_name = str_replace('IWP_Woo_V2_', 'IWP_', $class_name);
            
            // Check if it's a WooCommerce-specific class
            $woo_specific = array('Order_Processor', 'Product_Integration', 'HPOS');
            foreach ($woo_specific as $woo_class) {
                if (strpos($class_name, $woo_class) !== false) {
                    $new_class_name = str_replace('IWP_Woo_V2_', 'IWP_Woo_', $class_name);
                    break;
                }
            }

            // Try to load with new class name
            if (class_exists($new_class_name)) {
                // Create an alias for backward compatibility
                if (!class_exists($class_name, false)) {
                    class_alias($new_class_name, $class_name);
                }
            }
        }
    }
}

// Initialize the autoloader
IWP_Autoloader::init();