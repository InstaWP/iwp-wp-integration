<?php
/**
 * Autoloader for IWP WooCommerce Integration v2
 *
 * @package IWP_Woo_V2
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_V2_Autoloader class
 */
class IWP_Woo_V2_Autoloader {

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
        // Only load classes that start with our prefix
        if (strpos($class_name, 'IWP_Woo_V2_') !== 0) {
            return;
        }

        // Convert class name to file name
        $class_file = str_replace('_', '-', strtolower($class_name));
        $class_file = 'class-' . $class_file . '.php';

        // Define possible paths
        $paths = array(
            IWP_WOO_V2_PLUGIN_PATH . 'includes/',
            IWP_WOO_V2_PLUGIN_PATH . 'includes/admin/',
            IWP_WOO_V2_PLUGIN_PATH . 'includes/frontend/',
            IWP_WOO_V2_PLUGIN_PATH . 'includes/api/',
            IWP_WOO_V2_PLUGIN_PATH . 'includes/integrations/',
        );

        // Try to load the file from each path
        foreach ($paths as $path) {
            $file_path = $path . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }
    }
}

// Initialize the autoloader
IWP_Woo_V2_Autoloader::init();