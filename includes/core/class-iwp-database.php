<?php
/**
 * Database Helper Class
 *
 * @package IWP
 * @since 0.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Database class
 * 
 * Centralized database operations for order meta, options, and common queries
 */
class IWP_Database {

    /**
     * Append data to order meta array
     *
     * @param int $order_id
     * @param string $meta_key
     * @param mixed $data
     * @return bool
     */
    public static function append_order_meta($order_id, $meta_key, $data) {
        $existing_data = get_post_meta($order_id, $meta_key, true);
        
        if (!is_array($existing_data)) {
            $existing_data = array();
        }
        
        $existing_data[] = $data;
        
        return update_post_meta($order_id, $meta_key, $existing_data);
    }

    /**
     * Update order meta with validation
     *
     * @param int $order_id
     * @param string $meta_key
     * @param mixed $data
     * @param bool $merge_arrays Whether to merge if existing data is array
     * @return bool
     */
    public static function update_order_meta($order_id, $meta_key, $data, $merge_arrays = false) {
        // Validate order exists
        if (!wc_get_order($order_id)) {
            return false;
        }

        if ($merge_arrays) {
            $existing_data = get_post_meta($order_id, $meta_key, true);
            if (is_array($existing_data) && is_array($data)) {
                $data = array_merge($existing_data, $data);
            }
        }

        return update_post_meta($order_id, $meta_key, $data);
    }

    /**
     * Get all InstaWP sites for an order
     *
     * @param int $order_id
     * @return array
     */
    public static function get_order_sites($order_id) {
        $sites = array();

        // Get sites from different meta keys (for compatibility)
        $created_sites = get_post_meta($order_id, '_iwp_created_sites', true);
        if (is_array($created_sites)) {
            $sites = array_merge($sites, $created_sites);
        }

        $sites_created = get_post_meta($order_id, '_iwp_sites_created', true);
        if (is_array($sites_created)) {
            foreach ($sites_created as $site_data) {
                if (isset($site_data['site_data'])) {
                    $sites[] = $site_data['site_data'];
                }
            }
        }

        return $sites;
    }

    /**
     * Get mapped domains for an order
     *
     * @param int $order_id
     * @return array
     */
    public static function get_order_domains($order_id) {
        $domains = get_post_meta($order_id, '_iwp_mapped_domains', true);
        return is_array($domains) ? $domains : array();
    }

    /**
     * Add mapped domain to order
     *
     * @param int $order_id
     * @param array $domain_info
     * @return bool
     */
    public static function add_order_domain($order_id, $domain_info) {
        return self::append_order_meta($order_id, '_iwp_mapped_domains', $domain_info);
    }

    /**
     * Get plugin options with caching
     *
     * @param string $key Optional specific key to retrieve
     * @return mixed
     */
    public static function get_plugin_options($key = null) {
        static $options = null;

        if ($options === null) {
            $options = get_option('iwp_options', array());
        }

        if ($key !== null) {
            return isset($options[$key]) ? $options[$key] : null;
        }

        return $options;
    }

    /**
     * Update plugin option
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function update_plugin_option($key, $value) {
        $options = self::get_plugin_options();
        $options[$key] = $value;
        return update_option('iwp_options', $options);
    }

    /**
     * Get products with InstaWP snapshots configured
     *
     * @param int $limit
     * @return array
     */
    public static function get_snapshot_products($limit = -1) {
        return get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'numberposts' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_iwp_selected_snapshot',
                    'compare' => 'EXISTS'
                )
            )
        ));
    }

    /**
     * Get users for dropdown (with caching)
     *
     * @param int $limit
     * @return array
     */
    public static function get_users_for_dropdown($limit = 50) {
        $cache_key = 'iwp_users_dropdown_' . $limit;
        $cached_users = get_transient($cache_key);

        if ($cached_users !== false) {
            return $cached_users;
        }

        $users = get_users(array(
            'number' => $limit,
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));

        $formatted_users = array();
        foreach ($users as $user) {
            $formatted_users[$user->ID] = sprintf(
                '%s (%s) - %s',
                $user->display_name,
                $user->user_login,
                $user->user_email
            );
        }

        // Cache for 5 minutes
        set_transient($cache_key, $formatted_users, 300);

        return $formatted_users;
    }

    /**
     * Get orders with InstaWP sites
     *
     * @param array $args
     * @return array
     */
    public static function get_orders_with_sites($args = array()) {
        $default_args = array(
            'status' => array('completed', 'processing'),
            'limit' => 20,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_iwp_created_sites',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_iwp_sites_created',
                    'compare' => 'EXISTS'
                )
            )
        );

        $args = wp_parse_args($args, $default_args);
        return wc_get_orders($args);
    }

    /**
     * Clean up old transients
     *
     * @param string $prefix Optional prefix to filter transients
     */
    public static function cleanup_transients($prefix = 'iwp_') {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_' . $prefix . '%',
            '_transient_timeout_' . $prefix . '%'
        ));
    }

    /**
     * Get site creation statistics
     *
     * @return array
     */
    public static function get_site_creation_stats() {
        global $wpdb;

        $stats = array(
            'total_orders_with_sites' => 0,
            'total_sites_created' => 0,
            'sites_by_status' => array(),
            'sites_by_month' => array()
        );

        // Get orders with sites
        $orders_query = "
            SELECT COUNT(DISTINCT p.ID) as count 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'shop_order' 
            AND (pm.meta_key = '_iwp_created_sites' OR pm.meta_key = '_iwp_sites_created')
        ";

        $stats['total_orders_with_sites'] = $wpdb->get_var($orders_query);

        return $stats;
    }

    /**
     * Create custom table for plugin logs
     *
     * @return bool
     */
    public static function create_logs_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'iwp_logs';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
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
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        return dbDelta($sql);
    }

    /**
     * Log plugin activity to custom table
     *
     * @param string $action
     * @param string $message
     * @param array $data
     * @param int $order_id
     * @param int $user_id
     * @return bool
     */
    public static function log_activity($action, $message, $data = array(), $order_id = null, $user_id = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'iwp_logs';

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        return $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'order_id' => $order_id,
                'action' => $action,
                'message' => $message,
                'data' => wp_json_encode($data),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Get plugin activity logs
     *
     * @param array $args
     * @return array
     */
    public static function get_activity_logs($args = array()) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'iwp_logs';

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'action' => null,
            'user_id' => null,
            'order_id' => null,
            'start_date' => null,
            'end_date' => null
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array('1=1');
        $where_values = array();

        if ($args['action']) {
            $where_conditions[] = 'action = %s';
            $where_values[] = $args['action'];
        }

        if ($args['user_id']) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }

        if ($args['order_id']) {
            $where_conditions[] = 'order_id = %d';
            $where_values[] = $args['order_id'];
        }

        if ($args['start_date']) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['start_date'];
        }

        if ($args['end_date']) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['end_date'];
        }

        $where_clause = implode(' AND ', $where_conditions);

        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        if (!empty($where_values)) {
            error_log('IWP DEBUG: database get_activity_logs() - About to prepare SQL with values: ' . print_r($where_values, true));
            error_log('IWP DEBUG: database get_activity_logs() - Query: ' . $query);
            error_log('IWP DEBUG: database get_activity_logs() - Values type check: is_array=' . (is_array($where_values) ? 'YES' : 'NO') . ', count=' . (is_array($where_values) ? count($where_values) : 'N/A'));
            
            try {
                $query = $wpdb->prepare($query, $where_values);
                error_log('IWP DEBUG: database get_activity_logs() - SQL prepared successfully');
            } catch (Exception $e) {
                error_log('IWP ERROR: database get_activity_logs() - Exception during prepare: ' . $e->getMessage());
                throw $e;
            }
        }

        return $wpdb->get_results($query);
    }

    /**
     * Check if table exists
     *
     * @param string $table_name
     * @return bool
     */
    public static function table_exists($table_name) {
        global $wpdb;

        $query = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
        return $wpdb->get_var($query) === $table_name;
    }

    /**
     * Get database schema version
     *
     * @return string
     */
    public static function get_schema_version() {
        return get_option('iwp_db_version', '1.0.0');
    }

    /**
     * Update database schema version
     *
     * @param string $version
     * @return bool
     */
    public static function update_schema_version($version) {
        return update_option('iwp_db_version', $version);
    }
}