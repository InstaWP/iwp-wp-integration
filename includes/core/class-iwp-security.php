<?php
/**
 * Enhanced Security Helper Class
 *
 * @package IWP
 * @since 0.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Security class
 * 
 * Centralized security validation and sanitization methods
 */
class IWP_Security {

    /**
     * Validate AJAX request with nonce and capability check
     *
     * @param string $nonce_action The nonce action to verify
     * @param string $capability The required user capability
     * @param string $nonce_field The POST field containing the nonce (default: 'nonce')
     * @return bool True if valid, exits with error if not
     */
    public static function validate_ajax_request($nonce_action, $capability = 'manage_options', $nonce_field = 'nonce') {
        // Check nonce
        if (!wp_verify_nonce($_POST[$nonce_field] ?? '', $nonce_action)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'iwp-wp-integration')
            ));
        }

        // Check user capabilities
        if (!current_user_can($capability)) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'iwp-wp-integration')
            ));
        }

        return true;
    }

    /**
     * Validate AJAX request and return WP_Error instead of dying
     *
     * @param string $nonce_action The nonce action to verify
     * @param string $capability The required user capability
     * @param string $nonce_field The POST field containing the nonce
     * @return bool|WP_Error True if valid, WP_Error if not
     */
    public static function validate_ajax_request_soft($nonce_action, $capability = 'manage_options', $nonce_field = 'nonce') {
        // Check nonce
        if (!wp_verify_nonce($_POST[$nonce_field] ?? '', $nonce_action)) {
            return new WP_Error('nonce_failed', __('Security check failed.', 'iwp-wp-integration'));
        }

        // Check user capabilities
        if (!current_user_can($capability)) {
            return new WP_Error('insufficient_permissions', __('Insufficient permissions.', 'iwp-wp-integration'));
        }

        return true;
    }

    /**
     * Sanitize array of POST data
     *
     * @param array $fields Array of field names and their sanitization types
     * @return array Sanitized data array
     */
    public static function sanitize_post_data($fields) {
        $sanitized = array();

        foreach ($fields as $field_name => $sanitization_type) {
            $value = $_POST[$field_name] ?? '';

            switch ($sanitization_type) {
                case 'text':
                    $sanitized[$field_name] = sanitize_text_field($value);
                    break;
                case 'email':
                    $sanitized[$field_name] = sanitize_email($value);
                    break;
                case 'url':
                    $sanitized[$field_name] = esc_url_raw($value);
                    break;
                case 'textarea':
                    $sanitized[$field_name] = sanitize_textarea_field($value);
                    break;
                case 'key':
                    $sanitized[$field_name] = sanitize_key($value);
                    break;
                case 'user':
                    $sanitized[$field_name] = sanitize_user($value);
                    break;
                case 'int':
                    $sanitized[$field_name] = intval($value);
                    break;
                case 'float':
                    $sanitized[$field_name] = floatval($value);
                    break;
                case 'bool':
                    $sanitized[$field_name] = (bool) $value;
                    break;
                case 'array':
                    $sanitized[$field_name] = is_array($value) ? $value : array();
                    break;
                default:
                    $sanitized[$field_name] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Validate domain format
     *
     * @param string $domain
     * @return bool
     */
    public static function validate_domain($domain) {
        // Remove protocol if present
        $domain = preg_replace('#^https?://#', '', $domain);
        
        // Basic domain validation
        return filter_var('http://' . $domain, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Check if user can access order (for frontend operations)
     *
     * @param int $order_id
     * @param int $user_id Optional user ID, defaults to current user
     * @return bool
     */
    public static function can_user_access_order($order_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // Admin users can access all orders
        if (current_user_can('manage_woocommerce')) {
            return true;
        }

        // Check if order exists
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        // For logged-in users, check if they own the order
        if ($user_id > 0) {
            return $order->get_customer_id() == $user_id;
        }

        // For guests, additional validation would be needed (session, etc.)
        return false;
    }

    /**
     * Generate secure nonce for specific action and context
     *
     * @param string $action
     * @param mixed $context Additional context (order ID, user ID, etc.)
     * @return string
     */
    public static function create_nonce($action, $context = '') {
        $nonce_action = $action;
        if (!empty($context)) {
            $nonce_action .= '_' . $context;
        }
        return wp_create_nonce($nonce_action);
    }

    /**
     * Verify nonce for specific action and context
     *
     * @param string $nonce
     * @param string $action
     * @param mixed $context Additional context (order ID, user ID, etc.)
     * @return bool
     */
    public static function verify_nonce($nonce, $action, $context = '') {
        $nonce_action = $action;
        if (!empty($context)) {
            $nonce_action .= '_' . $context;
        }
        return wp_verify_nonce($nonce, $nonce_action);
    }

    /**
     * Rate limiting check for API operations
     *
     * @param string $action
     * @param int $user_id
     * @param int $limit Number of requests allowed
     * @param int $window Time window in seconds
     * @return bool True if allowed, false if rate-limited
     */
    public static function check_rate_limit($action, $user_id = null, $limit = 10, $window = 300) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $cache_key = 'iwp_rate_limit_' . $action . '_' . $user_id;
        $requests = get_transient($cache_key);

        if (false === $requests) {
            // First request in window
            set_transient($cache_key, 1, $window);
            return true;
        }

        if ($requests >= $limit) {
            return false;
        }

        // Increment counter
        set_transient($cache_key, $requests + 1, $window);
        return true;
    }

    /**
     * Sanitize and validate site creation data
     *
     * @param array $data Raw site data
     * @return array|WP_Error Sanitized data or error
     */
    public static function sanitize_site_data($data) {
        $sanitized = array();
        $errors = array();

        // Required fields
        $required_fields = array('name', 'admin_email');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = sprintf(__('Field %s is required.', 'iwp-wp-integration'), $field);
            }
        }

        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }

        // Sanitize fields
        $sanitized['name'] = sanitize_text_field($data['name']);
        $sanitized['admin_email'] = sanitize_email($data['admin_email']);
        $sanitized['title'] = isset($data['title']) ? sanitize_text_field($data['title']) : $sanitized['name'];
        $sanitized['admin_username'] = isset($data['admin_username']) ? sanitize_user($data['admin_username']) : 'admin';
        $sanitized['admin_password'] = isset($data['admin_password']) ? $data['admin_password'] : wp_generate_password(12, false);

        // Validate email
        if (!is_email($sanitized['admin_email'])) {
            return new WP_Error('invalid_email', __('Invalid email address.', 'iwp-wp-integration'));
        }

        return $sanitized;
    }

    /**
     * Log security events
     *
     * @param string $event
     * @param array $context
     */
    public static function log_security_event($event, $context = array()) {
        $user_id = get_current_user_id();
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $log_data = array(
            'event' => $event,
            'user_id' => $user_id,
            'user_ip' => $user_ip,
            'timestamp' => current_time('mysql'),
            'context' => $context
        );

        error_log('IWP Security Event: ' . wp_json_encode($log_data));
    }

    /**
     * Check if current request is from admin area
     *
     * @return bool
     */
    public static function is_admin_request() {
        return is_admin() && !wp_doing_ajax();
    }

    /**
     * Check if current request is AJAX
     *
     * @return bool
     */
    public static function is_ajax_request() {
        return wp_doing_ajax();
    }

    /**
     * Check if current request is from frontend
     *
     * @return bool
     */
    public static function is_frontend_request() {
        return !is_admin() && !wp_doing_ajax() && !wp_doing_cron();
    }
}