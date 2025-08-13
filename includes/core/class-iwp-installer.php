<?php
/**
 * Installer class for InstaWP Integration
 *
 * @package IWP
 * @since 0.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Installer class
 */
class IWP_Installer {

    /**
     * DB updates and callbacks that need to be run per version
     *
     * @var array
     */
    private static $db_updates = array(
        '0.0.1' => array(
            'iwp_create_tables',
            'iwp_create_options',
        ),
        '2.1.0' => array(
            array('IWP_Installer', 'cleanup_old_product_meta'),
            array('IWP_Installer', 'set_default_auto_create_setting'),
        ),
    );

    /**
     * Install the plugin
     */
    public static function install() {
        if (!defined('IWP_INSTALLING')) {
            define('IWP_INSTALLING', true);
        }

        // Check if we have the minimum requirements
        self::check_requirements();

        // Create tables
        self::create_tables();

        // Create default options
        self::create_options();

        // Create files/directories
        self::create_files();

        // Update version
        self::update_version();

        // Trigger action
        do_action('iwp_installed');
    }

    /**
     * Check requirements
     */
    private static function check_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die(
                esc_html__('InstaWP Integration requires PHP 7.4 or higher.', 'iwp-wp-integration'),
                esc_html__('Requirements Error', 'iwp-wp-integration'),
                array('back_link' => true)
            );
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            wp_die(
                esc_html__('InstaWP Integration requires WordPress 5.0 or higher.', 'iwp-wp-integration'),
                esc_html__('Requirements Error', 'iwp-wp-integration'),
                array('back_link' => true)
            );
        }

    }

    /**
     * Create tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tables = array(
            // Example table for storing plugin-specific data
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}iwp_logs (
                id bigint(20) unsigned NOT NULL auto_increment,
                user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                order_id bigint(20) unsigned NULL DEFAULT NULL,
                action varchar(100) NOT NULL,
                message text NOT NULL,
                data longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY order_id (order_id),
                KEY action (action),
                KEY created_at (created_at)
            ) $charset_collate;",

            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}iwp_settings (
                id bigint(20) unsigned NOT NULL auto_increment,
                setting_key varchar(100) NOT NULL,
                setting_value longtext,
                autoload varchar(20) NOT NULL DEFAULT 'yes',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY setting_key (setting_key)
            ) $charset_collate;",

            // HPOS compatible order data table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}iwp_woo_order_data (
                id bigint(20) unsigned NOT NULL auto_increment,
                order_id bigint(20) unsigned NOT NULL,
                order_type varchar(20) NOT NULL DEFAULT 'shop_order',
                meta_key varchar(255) NOT NULL,
                meta_value longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY order_id (order_id),
                KEY order_type (order_type),
                KEY meta_key (meta_key),
                KEY created_at (created_at)
            ) $charset_collate;",

            // Sites tracking table for real-time status updates
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}iwp_sites (
                id bigint(20) unsigned NOT NULL auto_increment,
                site_id varchar(100) NOT NULL,
                site_url varchar(255) NULL,
                wp_username varchar(100) NULL,
                wp_password varchar(255) NULL,
                wp_admin_url varchar(255) NULL,
                s_hash varchar(255) NULL,
                status varchar(20) NOT NULL DEFAULT 'creating',
                task_id varchar(100) NULL,
                snapshot_slug varchar(100) NULL,
                plan_id varchar(100) NULL,
                product_id bigint(20) unsigned NULL,
                order_id bigint(20) unsigned NULL,
                user_id bigint(20) unsigned NULL DEFAULT 0,
                source varchar(50) NOT NULL DEFAULT 'woocommerce',
                source_data longtext NULL,
                is_pool tinyint(1) NOT NULL DEFAULT 0,
                is_reserved tinyint(1) NOT NULL DEFAULT 1,
                expiry_hours int(11) NULL DEFAULT NULL,
                api_response longtext NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY site_id (site_id),
                KEY status (status),
                KEY order_id (order_id),
                KEY user_id (user_id),
                KEY source (source),
                KEY task_id (task_id),
                KEY created_at (created_at),
                KEY updated_at (updated_at)
            ) $charset_collate;"
        );

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($tables as $table) {
            dbDelta($table);
        }
    }

    /**
     * Create default options
     */
    private static function create_options() {
        $default_options = array(
            'version' => IWP_VERSION,
            'installed_at' => current_time('mysql'),
            // General Settings - All enabled by default for better user experience
            'enable_integration' => 'yes',
            'auto_create_sites' => 'yes', 
            'use_site_id_parameter' => 'yes',
            // Debug Settings - Reasonable defaults
            'debug_mode' => 'no',
            'log_level' => 'info',
            // Legacy support
            'enabled' => 'yes',
            'auto_create_sites_on_purchase' => 'yes',
        );

        $existing_options = get_option('iwp_options', array());
        $options = array_merge($default_options, $existing_options);

        update_option('iwp_options', $options);
    }

    /**
     * Create files and directories
     */
    private static function create_files() {
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $iwp_upload_dir = $upload_dir['basedir'] . '/iwp';

        if (!file_exists($iwp_upload_dir)) {
            wp_mkdir_p($iwp_upload_dir);
        }

        // Create .htaccess file to protect directory
        $htaccess_file = $iwp_upload_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = 'deny from all';
            file_put_contents($htaccess_file, $htaccess_content);
        }

        // Create index.php file
        $index_file = $iwp_upload_dir . '/index.php';
        if (!file_exists($index_file)) {
            $index_content = '<?php // Silence is golden';
            file_put_contents($index_file, $index_content);
        }
    }

    /**
     * Update version
     */
    private static function update_version() {
        delete_option('iwp_version');
        add_option('iwp_version', IWP_VERSION);
    }

    /**
     * Set default settings for new installations or reset to defaults
     * Can be called from admin if needed
     */
    public static function set_default_settings() {
        $current_options = get_option('iwp_options', array());
        
        // Only update if API key exists (don't reset everything)
        $api_key = isset($current_options['api_key']) ? $current_options['api_key'] : '';
        
        $default_options = array(
            // Keep API key if it exists
            'api_key' => $api_key,
            // Set all checkboxes to enabled by default
            'enable_integration' => 'yes',
            'auto_create_sites' => 'yes',
            'use_site_id_parameter' => 'yes',
            // Reasonable debug defaults
            'debug_mode' => 'no',
            'log_level' => 'info',
        );
        
        update_option('iwp_options', $default_options);
        
        return true;
    }

    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('iwp_daily_cleanup');
        wp_clear_scheduled_hook('iwp_weekly_report');
        wp_clear_scheduled_hook('iwp_check_pending_sites');

        // Flush rewrite rules
        flush_rewrite_rules();

        // Trigger action
        do_action('iwp_deactivated');
    }

    /**
     * Uninstall the plugin
     */
    public static function uninstall() {
        global $wpdb;

        // Delete options
        delete_option('iwp_options');
        delete_option('iwp_version');

        // Delete tables
        $tables = array(
            $wpdb->prefix . 'iwp_logs',
            $wpdb->prefix . 'iwp_settings',
            $wpdb->prefix . 'iwp_order_data',
            $wpdb->prefix . 'iwp_sites'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        // Delete transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_iwp_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_iwp_%'");

        // Delete user meta
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'iwp_%'");

        // Delete files
        $upload_dir = wp_upload_dir();
        $iwp_upload_dir = $upload_dir['basedir'] . '/iwp';
        
        if (is_dir($iwp_upload_dir)) {
            self::delete_directory($iwp_upload_dir);
        }

        // Trigger action
        do_action('iwp_uninstalled');
    }

    /**
     * Delete directory recursively
     *
     * @param string $dir The directory to delete
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Update database
     */
    public static function update_database() {
        $current_version = get_option('iwp_version');
        
        if (!$current_version) {
            self::install();
            return;
        }

        foreach (self::$db_updates as $version => $update_callbacks) {
            if (version_compare($current_version, $version, '<')) {
                foreach ($update_callbacks as $update_callback) {
                    if (is_callable($update_callback)) {
                        call_user_func($update_callback);
                    }
                }
            }
        }

        self::update_version();
    }

    /**
     * Check if database needs updating
     *
     * @return bool
     */
    public static function needs_database_update() {
        $current_version = get_option('iwp_version');
        
        if (!$current_version) {
            return true;
        }

        return version_compare($current_version, IWP_VERSION, '<');
    }

    /**
     * Clean up old product meta keys from product-specific auto-create setting
     */
    public static function cleanup_old_product_meta() {
        global $wpdb;
        
        error_log('InstaWP Integration: Cleaning up old _iwp_auto_create_site meta keys');
        
        // Delete all _iwp_auto_create_site meta keys from products
        $result = $wpdb->delete(
            $wpdb->postmeta,
            array('meta_key' => '_iwp_auto_create_site'),
            array('%s')
        );
        
        if ($result !== false) {
            error_log("InstaWP Integration: Successfully removed {$result} old auto-create meta keys");
        } else {
            error_log('InstaWP Integration: Failed to remove old auto-create meta keys: ' . $wpdb->last_error);
        }
    }

    /**
     * Set global auto-create setting to enabled (default)
     * This maintains existing behavior for users upgrading
     */
    public static function set_default_auto_create_setting() {
        $options = get_option('iwp_options', array());
        
        // Only set if not already configured
        if (!isset($options['auto_create_sites_on_purchase'])) {
            $options['auto_create_sites_on_purchase'] = 'yes';
            update_option('iwp_options', $options);
            error_log('InstaWP Integration: Set default auto-create setting to enabled');
        }
    }
}