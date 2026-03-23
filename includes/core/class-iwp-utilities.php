<?php
/**
 * Utilities class for IWP WooCommerce Integration v2
 *
 * @package IWP
 * @since 0.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Utilities class
 */
class IWP_Utilities {

    /**
     * Log messages
     *
     * @param string $message The message to log
     * @param string $level The log level (info, warning, error, debug)
     */
    public static function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $logger = wc_get_logger();
            $logger->log($level, $message, array('source' => 'iwp-woo-v2'));
        }
    }

    /**
     * Get plugin option
     *
     * @param string $option_name The option name
     * @param mixed $default The default value
     * @return mixed
     */
    public static function get_option($option_name, $default = null) {
        $options = get_option('iwp_options', array());
        return isset($options[$option_name]) ? $options[$option_name] : $default;
    }

    /**
     * Update plugin option
     *
     * @param string $option_name The option name
     * @param mixed $value The option value
     * @return bool
     */
    public static function update_option($option_name, $value) {
        $options = get_option('iwp_options', array());
        $options[$option_name] = $value;
        return update_option('iwp_options', $options);
    }

    /**
     * Delete plugin option
     *
     * @param string $option_name The option name
     * @return bool
     */
    public static function delete_option($option_name) {
        $options = get_option('iwp_options', array());
        if (isset($options[$option_name])) {
            unset($options[$option_name]);
            return update_option('iwp_options', $options);
        }
        return false;
    }

    /**
     * Format currency
     *
     * @param float $amount The amount to format
     * @param string $currency The currency code
     * @return string
     */
    public static function format_currency($amount, $currency = null) {
        if ($currency === null) {
            $currency = get_woocommerce_currency();
        }
        
        return wc_price($amount, array('currency' => $currency));
    }

    /**
     * Get WooCommerce product by ID
     *
     * @param int $product_id The product ID
     * @return WC_Product|false
     */
    public static function get_product($product_id) {
        return wc_get_product($product_id);
    }

    /**
     * Get WooCommerce order by ID (HPOS compatible)
     *
     * @param int $order_id The order ID
     * @return WC_Order|false
     */
    public static function get_order($order_id) {
        return IWP_HPOS::get_order($order_id);
    }

    /**
     * Get orders (HPOS compatible)
     *
     * @param array $args Query arguments
     * @return WC_Order[]
     */
    public static function get_orders($args = array()) {
        return IWP_HPOS::get_orders($args);
    }

    /**
     * Get order count (HPOS compatible)
     *
     * @param array $args Query arguments
     * @return int
     */
    public static function get_order_count($args = array()) {
        return IWP_HPOS::get_order_count($args);
    }

    /**
     * Get orders by customer (HPOS compatible)
     *
     * @param int $customer_id Customer ID
     * @param array $args Additional arguments
     * @return WC_Order[]
     */
    public static function get_orders_by_customer($customer_id, $args = array()) {
        return IWP_HPOS::get_orders_by_customer($customer_id, $args);
    }

    /**
     * Get orders by status (HPOS compatible)
     *
     * @param string|array $status Order status(es)
     * @param array $args Additional arguments
     * @return WC_Order[]
     */
    public static function get_orders_by_status($status, $args = array()) {
        return IWP_HPOS::get_orders_by_status($status, $args);
    }

    /**
     * Get order meta (HPOS compatible)
     *
     * @param int $order_id Order ID
     * @param string $key Meta key
     * @param bool $single Whether to return single value
     * @return mixed
     */
    public static function get_order_meta($order_id, $key, $single = true) {
        return IWP_HPOS::get_order_meta($order_id, $key, $single);
    }

    /**
     * Update order meta (HPOS compatible)
     *
     * @param int $order_id Order ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool
     */
    public static function update_order_meta($order_id, $key, $value) {
        return IWP_HPOS::update_order_meta($order_id, $key, $value);
    }

    /**
     * Delete order meta (HPOS compatible)
     *
     * @param int $order_id Order ID
     * @param string $key Meta key
     * @return bool
     */
    public static function delete_order_meta($order_id, $key) {
        return IWP_HPOS::delete_order_meta($order_id, $key);
    }

    /**
     * Check if HPOS is enabled
     *
     * @return bool
     */
    public static function is_hpos_enabled() {
        return IWP_HPOS::is_hpos_enabled();
    }

    /**
     * Get current user ID
     *
     * @return int
     */
    public static function get_current_user_id() {
        return get_current_user_id();
    }

    /**
     * Check if user is logged in
     *
     * @return bool
     */
    public static function is_user_logged_in() {
        return is_user_logged_in();
    }

    /**
     * Get current timestamp
     *
     * @return int
     */
    public static function get_timestamp() {
        return current_time('timestamp');
    }

    /**
     * Format date
     *
     * @param string|int $date The date to format
     * @param string $format The date format
     * @return string
     */
    public static function format_date($date, $format = 'Y-m-d H:i:s') {
        if (is_numeric($date)) {
            return date($format, $date);
        }
        return date($format, strtotime($date));
    }

    /**
     * Generate random string
     *
     * @param int $length The length of the string
     * @return string
     */
    public static function generate_random_string($length = 10) {
        return wp_generate_password($length, false);
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    public static function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Get WooCommerce version
     *
     * @return string
     */
    public static function get_woocommerce_version() {
        return defined('WC_VERSION') ? WC_VERSION : '';
    }

    /**
     * Send email notification
     *
     * @param string $to The recipient email
     * @param string $subject The email subject
     * @param string $message The email message
     * @param array $headers Optional headers
     * @return bool
     */
    public static function send_email($to, $subject, $message, $headers = array()) {
        $default_headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
        );
        
        $headers = array_merge($default_headers, $headers);
        
        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Get template
     *
     * @param string $template_name The template name
     * @param array $args Template arguments
     * @param string $template_path The template path
     * @param string $default_path The default path
     */
    public static function get_template($template_name, $args = array(), $template_path = '', $default_path = '') {
        if (!empty($args) && is_array($args)) {
            extract($args);
        }

        $located = self::locate_template($template_name, $template_path, $default_path);

        if (!file_exists($located)) {
            return;
        }

        include $located;
    }

    /**
     * Locate template
     *
     * @param string $template_name The template name
     * @param string $template_path The template path
     * @param string $default_path The default path
     * @return string
     */
    public static function locate_template($template_name, $template_path = '', $default_path = '') {
        if (!$template_path) {
            $template_path = 'iwp/';
        }

        if (!$default_path) {
            $default_path = IWP_PLUGIN_PATH . 'templates/';
        }

        // Look within passed path within the theme
        $template = locate_template(array(
            trailingslashit($template_path) . $template_name,
            $template_name
        ));

        // Get default template
        if (!$template) {
            $template = $default_path . $template_name;
        }

        return apply_filters('iwp_locate_template', $template, $template_name, $template_path);
    }

    /**
     * Array to HTML attributes
     *
     * @param array $attributes The attributes array
     * @return string
     */
    public static function array_to_attributes($attributes) {
        $output = '';
        foreach ($attributes as $key => $value) {
            $output .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        return $output;
    }

    /**
     * Check if current page is WooCommerce page
     *
     * @return bool
     */
    public static function is_woocommerce_page() {
        return function_exists('is_woocommerce') && is_woocommerce();
    }

    /**
     * Get cart total
     *
     * @return float
     */
    public static function get_cart_total() {
        if (WC()->cart) {
            return WC()->cart->get_total('');
        }
        return 0;
    }

    /**
     * Get cart count
     *
     * @return int
     */
    public static function get_cart_count() {
        if (WC()->cart) {
            return WC()->cart->get_cart_contents_count();
        }
        return 0;
    }

    /**
     * Clean string for use as filename
     *
     * @param string $string The string to clean
     * @return string
     */
    public static function clean_filename($string) {
        return sanitize_file_name($string);
    }

    /**
     * Get file extension
     *
     * @param string $filename The filename
     * @return string
     */
    public static function get_file_extension($filename) {
        return pathinfo($filename, PATHINFO_EXTENSION);
    }

    /**
     * Check if string is JSON
     *
     * @param string $string The string to check
     * @return bool
     */
    public static function is_json($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Convert array to JSON
     *
     * @param array $array The array to convert
     * @return string|false
     */
    public static function array_to_json($array) {
        return json_encode($array);
    }

    /**
     * Convert JSON to array
     *
     * @param string $json The JSON string
     * @return array|null
     */
    public static function json_to_array($json) {
        return json_decode($json, true);
    }
}