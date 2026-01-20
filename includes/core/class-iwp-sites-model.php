<?php
/**
 * Sites Model for IWP WooCommerce Integration v2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class IWP_Sites_Model {
    private static $table_name;

    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'iwp_sites';
    }

    public static function create($data) {
        global $wpdb;
        
        if (!self::$table_name) {
            self::init();
        }

        $defaults = array(
            'status' => 'creating',
            'site_type' => 'paid',
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
                'site_type' => sanitize_text_field($site_data['site_type']),
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
            IWP_Logger::error('Failed to create site record', 'sites-model', array('error' => $wpdb->last_error));
            return false;
        }

        return $wpdb->insert_id;
    }

    public static function update($site_id, $data) {
        global $wpdb;
        
        error_log('IWP DEBUG: sites model update() - Called with site_id: ' . $site_id);
        error_log('IWP DEBUG: sites model update() - Data: ' . print_r($data, true));
        
        if (!self::$table_name) {
            self::init();
        }

        // Sanitize data for database storage
        $sanitized_data = array();
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // JSON encode array values for database storage
                $sanitized_data[$key] = wp_json_encode($value);
                error_log('IWP DEBUG: sites model update() - Converted array field "' . $key . '" to JSON');
            } else {
                $sanitized_data[$key] = $value;
            }
        }
        
        $sanitized_data['updated_at'] = current_time('mysql');

        error_log('IWP DEBUG: sites model update() - About to call wpdb->update with sanitized data');
        $result = $wpdb->update(
            self::$table_name,
            $sanitized_data,
            array('site_id' => sanitize_text_field($site_id))
        );
        error_log('IWP DEBUG: sites model update() - wpdb->update result: ' . ($result !== false ? 'SUCCESS' : 'FAILED'));

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
            error_log('IWP DEBUG: sites model count() - About to prepare SQL with values: ' . print_r($where_values, true));
            error_log('IWP DEBUG: sites model count() - SQL: ' . $sql);
            error_log('IWP DEBUG: sites model count() - Values type check: is_array=' . (is_array($where_values) ? 'YES' : 'NO') . ', count=' . (is_array($where_values) ? count($where_values) : 'N/A'));
            
            try {
                $prepared_sql = $wpdb->prepare($sql, $where_values);
                error_log('IWP DEBUG: sites model count() - SQL prepared successfully');
                return intval($wpdb->get_var($prepared_sql));
            } catch (Exception $e) {
                error_log('IWP ERROR: sites model count() - Exception during prepare: ' . $e->getMessage());
                throw $e;
            }
        } else {
            error_log('IWP DEBUG: sites model count() - No where values, executing raw SQL');
            return intval($wpdb->get_var($sql));
        }
    }

    /**
     * Update site plan and track upgrade history
     *
     * @param string $site_id InstaWP site ID
     * @param string $new_plan_id New plan ID
     * @param array $upgrade_data Additional upgrade data
     * @return bool Success status
     */
    public static function update_plan($site_id, $new_plan_id, $upgrade_data = array()) {
        global $wpdb;
        
        if (!self::$table_name) {
            self::init();
        }

        // Get current site data to track upgrade history
        $current_site = self::get_by_site_id($site_id);
        
        if (!$current_site) {
            IWP_Logger::warning('Site not found for plan upgrade, creating new entry', 'sites-model', array(
                'site_id' => $site_id,
                'new_plan_id' => $new_plan_id
            ));
            return self::create_from_upgrade($site_id, $new_plan_id, $upgrade_data);
        }

        // Prepare upgrade history
        $upgrade_history = array();
        
        // Try to get existing upgrade history
        if (!empty($current_site->api_response)) {
            $existing_data = json_decode($current_site->api_response, true);
            if (is_array($existing_data) && isset($existing_data['upgrade_history'])) {
                $upgrade_history = $existing_data['upgrade_history'];
            }
        }

        // Add new upgrade entry
        $upgrade_history[] = array(
            'previous_plan_id' => $current_site->plan_id,
            'new_plan_id' => $new_plan_id,
            'upgraded_at' => current_time('mysql'),
            'order_id' => isset($upgrade_data['order_id']) ? $upgrade_data['order_id'] : null,
            'product_id' => isset($upgrade_data['product_id']) ? $upgrade_data['product_id'] : null,
            'upgrade_response' => isset($upgrade_data['upgrade_response']) ? $upgrade_data['upgrade_response'] : null
        );

        // Prepare API response data with upgrade history
        $api_response_data = array(
            'upgrade_history' => $upgrade_history,
            'last_upgrade' => array(
                'from' => $current_site->plan_id,
                'to' => $new_plan_id,
                'upgraded_at' => current_time('mysql')
            )
        );

        // Merge with existing API response data if any
        if (!empty($current_site->api_response)) {
            $existing_data = json_decode($current_site->api_response, true);
            if (is_array($existing_data)) {
                $api_response_data = array_merge($existing_data, $api_response_data);
            }
        }

        // Update the site record
        $update_data = array(
            'plan_id' => sanitize_text_field($new_plan_id),
            'api_response' => wp_json_encode($api_response_data),
            'updated_at' => current_time('mysql')
        );

        // Add any additional site details if provided in upgrade_data
        if (isset($upgrade_data['site_url']) && !empty($upgrade_data['site_url'])) {
            $update_data['site_url'] = esc_url_raw($upgrade_data['site_url']);
        }
        if (isset($upgrade_data['wp_username']) && !empty($upgrade_data['wp_username'])) {
            $update_data['wp_username'] = sanitize_text_field($upgrade_data['wp_username']);
        }
        if (isset($upgrade_data['wp_password']) && !empty($upgrade_data['wp_password'])) {
            $update_data['wp_password'] = $upgrade_data['wp_password'];
        }
        if (isset($upgrade_data['s_hash']) && !empty($upgrade_data['s_hash'])) {
            $update_data['s_hash'] = sanitize_text_field($upgrade_data['s_hash']);
        }

        $result = $wpdb->update(
            self::$table_name,
            $update_data,
            array('site_id' => sanitize_text_field($site_id)),
            null,
            array('%s')
        );

        if ($result !== false) {
            IWP_Logger::info('Site plan updated successfully', 'sites-model', array(
                'site_id' => $site_id,
                'previous_plan' => $current_site->plan_id,
                'new_plan' => $new_plan_id
            ));
            return true;
        } else {
            IWP_Logger::error('Failed to update site plan', 'sites-model', array(
                'site_id' => $site_id,
                'new_plan_id' => $new_plan_id,
                'error' => $wpdb->last_error
            ));
            return false;
        }
    }

    /**
     * Create new site entry from upgrade data (for sites not in database)
     *
     * @param string $site_id InstaWP site ID
     * @param string $plan_id Plan ID
     * @param array $upgrade_data Upgrade data
     * @return bool Success status
     */
    public static function create_from_upgrade($site_id, $plan_id, $upgrade_data = array()) {
        $site_data = array(
            'site_id' => $site_id,
            'plan_id' => $plan_id,
            'status' => 'completed', // Upgrades are only done on completed sites
            'source' => 'upgrade',
            'order_id' => isset($upgrade_data['order_id']) ? intval($upgrade_data['order_id']) : null,
            'product_id' => isset($upgrade_data['product_id']) ? intval($upgrade_data['product_id']) : null,
            'user_id' => isset($upgrade_data['customer_id']) ? intval($upgrade_data['customer_id']) : 0
        );

        // Add site details if available from the upgrade response
        if (isset($upgrade_data['site_url'])) {
            $site_data['site_url'] = $upgrade_data['site_url'];
        }
        if (isset($upgrade_data['wp_username'])) {
            $site_data['wp_username'] = $upgrade_data['wp_username'];
        }
        if (isset($upgrade_data['wp_password'])) {
            $site_data['wp_password'] = $upgrade_data['wp_password'];
        }
        if (isset($upgrade_data['s_hash'])) {
            $site_data['s_hash'] = $upgrade_data['s_hash'];
        }

        // Mark that this was created from an upgrade
        $api_response_data = array(
            'created_from_upgrade' => true,
            'upgrade_history' => array(
                array(
                    'previous_plan_id' => null,
                    'new_plan_id' => $plan_id,
                    'upgraded_at' => current_time('mysql'),
                    'order_id' => isset($upgrade_data['order_id']) ? $upgrade_data['order_id'] : null,
                    'upgrade_response' => isset($upgrade_data['upgrade_response']) ? $upgrade_data['upgrade_response'] : null
                )
            )
        );
        $site_data['api_response'] = $api_response_data;

        $db_site_id = self::create($site_data);
        
        if ($db_site_id) {
            IWP_Logger::info('Created new site record from upgrade', 'sites-model', array(
                'site_id' => $site_id,
                'plan_id' => $plan_id,
                'db_id' => $db_site_id
            ));
            return true;
        }
        
        return false;
    }

    /**
     * Get upgrade history for a site
     *
     * @param string $site_id InstaWP site ID
     * @return array Upgrade history
     */
    public static function get_upgrade_history($site_id) {
        $site = self::get_by_site_id($site_id);
        
        if (!$site || empty($site->api_response)) {
            return array();
        }

        $api_data = json_decode($site->api_response, true);
        
        if (!is_array($api_data) || !isset($api_data['upgrade_history'])) {
            return array();
        }

        return $api_data['upgrade_history'];
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

    /**
     * Get demo sites by email (for reconciliation)
     *
     * @param string $email Email address
     * @return array Array of site objects
     */
    public static function get_demo_sites_by_email($email) {
        global $wpdb;

        if (!self::$table_name) {
            self::init();
        }

        // Query demo sites with matching email in source_data
        $sites = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . "
             WHERE site_type = 'demo'
             AND order_id IS NULL
             AND JSON_EXTRACT(source_data, '$.email') = %s
             ORDER BY created_at DESC",
            $email
        ));

        return $sites;
    }

    /**
     * Get all demo sites for a user
     *
     * @param int $user_id User ID
     * @return array Array of site objects
     */
    public static function get_demo_sites_by_user($user_id) {
        global $wpdb;

        if (!self::$table_name) {
            self::init();
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . "
             WHERE site_type = 'demo'
             AND user_id = %d
             ORDER BY created_at DESC",
            intval($user_id)
        ));
    }

    /**
     * Mark expired demo sites
     * Updates status of demo sites that have passed their expiry_hours
     *
     * @return int Number of sites marked as expired
     */
    public static function mark_expired_demos() {
        global $wpdb;

        if (!self::$table_name) {
            self::init();
        }

        // Find demo sites that have expired
        $result = $wpdb->query(
            "UPDATE " . self::$table_name . "
             SET status = 'expired', updated_at = NOW()
             WHERE site_type = 'demo'
             AND expiry_hours IS NOT NULL
             AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > expiry_hours
             AND status != 'expired'"
        );

        if ($result !== false && $result > 0) {
            IWP_Logger::info('Marked expired demo sites', 'sites-model', array(
                'count' => $result
            ));
        }

        return $result !== false ? $result : 0;
    }
}