<?php
/**
 * Sites Model for IWP WooCommerce Integration v2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class IWP_Woo_V2_Sites_Model {
    private static $table_name;

    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'iwp_woo_v2_sites';
    }

    public static function create($data) {
        global $wpdb;
        
        if (!self::$table_name) {
            self::init();
        }

        $defaults = array(
            'status' => 'creating',
            'source' => 'woocommerce',
            'is_pool' => 0,
            'is_reserved' => 1,
            'user_id' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $site_data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            self::$table_name,
            array(
                'site_id' => sanitize_text_field($site_data['site_id']),
                'site_url' => !empty($site_data['site_url']) ? esc_url_raw($site_data['site_url']) : null,
                'wp_username' => !empty($site_data['wp_username']) ? sanitize_text_field($site_data['wp_username']) : null,
                'wp_password' => !empty($site_data['wp_password']) ? $site_data['wp_password'] : null,
                'wp_admin_url' => !empty($site_data['wp_admin_url']) ? esc_url_raw($site_data['wp_admin_url']) : null,
                's_hash' => !empty($site_data['s_hash']) ? sanitize_text_field($site_data['s_hash']) : null,
                'status' => sanitize_text_field($site_data['status']),
                'task_id' => !empty($site_data['task_id']) ? sanitize_text_field($site_data['task_id']) : null,
                'snapshot_slug' => !empty($site_data['snapshot_slug']) ? sanitize_text_field($site_data['snapshot_slug']) : null,
                'plan_id' => !empty($site_data['plan_id']) ? sanitize_text_field($site_data['plan_id']) : null,
                'product_id' => !empty($site_data['product_id']) ? intval($site_data['product_id']) : null,
                'order_id' => !empty($site_data['order_id']) ? intval($site_data['order_id']) : null,
                'user_id' => intval($site_data['user_id']),
                'source' => sanitize_text_field($site_data['source']),
                'source_data' => !empty($site_data['source_data']) ? wp_json_encode($site_data['source_data']) : null,
                'is_pool' => intval($site_data['is_pool']),
                'is_reserved' => intval($site_data['is_reserved']),
                'expiry_hours' => !empty($site_data['expiry_hours']) ? intval($site_data['expiry_hours']) : null,
                'api_response' => !empty($site_data['api_response']) ? wp_json_encode($site_data['api_response']) : null,
                'created_at' => $site_data['created_at'],
                'updated_at' => $site_data['updated_at']
            )
        );

        if ($result === false) {
            IWP_Woo_V2_Logger::error('Failed to create site record', 'sites-model', array('error' => $wpdb->last_error));
            return false;
        }

        return $wpdb->insert_id;
    }

    public static function update($site_id, $data) {
        global $wpdb;
        
        if (!self::$table_name) {
            self::init();
        }

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            self::$table_name,
            $data,
            array('site_id' => sanitize_text_field($site_id))
        );

        return $result !== false;
    }

    public static function get_by_site_id($site_id) {
        global $wpdb;
        
        if (!self::$table_name) {
            self::init();
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " WHERE site_id = %s",
            sanitize_text_field($site_id)
        ));
    }

    public static function get_all($args = array()) {
        global $wpdb;
        
        if (!self::$table_name) {
            self::init();
        }

        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM " . self::$table_name . " ORDER BY created_at DESC LIMIT %d OFFSET %d";

        return $wpdb->get_results($wpdb->prepare($sql, intval($args['limit']), intval($args['offset'])));
    }

    public static function get_pending_sites() {
        global $wpdb;
        
        if (!self::$table_name) {
            self::init();
        }

        return $wpdb->get_results(
            "SELECT * FROM " . self::$table_name . " WHERE status IN ('creating', 'progress') AND task_id IS NOT NULL ORDER BY created_at ASC"
        );
    }

    public static function delete($site_id) {
        global $wpdb;
        
        if (!self::$table_name) {
            self::init();
        }

        $result = $wpdb->delete(
            self::$table_name,
            array('site_id' => sanitize_text_field($site_id)),
            array('%s')
        );

        return $result !== false;
    }

    /**
     * Get total count of sites
     *
     * @param array $filters Optional filters
     * @return int Total count
     */
    public static function get_total_count($filters = array()) {
        global $wpdb;
        
        if (!self::$table_name) {
            self::init();
        }

        $where_clauses = array();
        $where_values = array();

        if (!empty($filters['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = sanitize_text_field($filters['status']);
        }

        if (!empty($filters['source'])) {
            $where_clauses[] = "source = %s";
            $where_values[] = sanitize_text_field($filters['source']);
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $sql = "SELECT COUNT(*) FROM " . self::$table_name . " " . $where_sql;

        if (!empty($where_values)) {
            return intval($wpdb->get_var($wpdb->prepare($sql, $where_values)));
        } else {
            return intval($wpdb->get_var($sql));
        }
    }

    /**
     * Get sites by order ID
     *
     * @param int $order_id Order ID
     * @return array Array of site objects
     */
    public static function get_by_order_id($order_id) {
        global $wpdb;
        
        if (!self::$table_name) {
            self::init();
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " WHERE order_id = %d ORDER BY created_at DESC",
            intval($order_id)
        ));
    }

    /**
     * Get sites by user ID
     *
     * @param int $user_id User ID
     * @return array Array of site objects
     */
    public static function get_by_user_id($user_id) {
        global $wpdb;
        
        if (!self::$table_name) {
            self::init();
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " WHERE user_id = %d ORDER BY created_at DESC",
            intval($user_id)
        ));
    }
}