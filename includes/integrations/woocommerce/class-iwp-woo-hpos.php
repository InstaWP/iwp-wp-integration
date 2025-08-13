<?php
/**
 * HPOS (High Performance Order Storage) compatibility class
 *
 * @package IWP
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_HPOS class
 */
class IWP_Woo_HPOS {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add hooks for HPOS compatibility
        add_action('woocommerce_new_order', array($this, 'handle_new_order'), 10, 2);
        add_action('woocommerce_update_order', array($this, 'handle_order_update'), 10, 2);
        add_action('woocommerce_delete_order', array($this, 'handle_order_delete'), 10, 2);
        add_action('woocommerce_trash_order', array($this, 'handle_order_trash'), 10, 2);
        add_action('woocommerce_untrash_order', array($this, 'handle_order_untrash'), 10, 2);
        
        // Hook into order status changes
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        
        // Hook into order item operations
        add_action('woocommerce_new_order_item', array($this, 'handle_new_order_item'), 10, 3);
        add_action('woocommerce_update_order_item', array($this, 'handle_order_item_update'), 10, 3);
        add_action('woocommerce_delete_order_item', array($this, 'handle_order_item_delete'), 10, 2);
    }

    /**
     * Check if HPOS is enabled
     *
     * @return bool
     */
    public static function is_hpos_enabled() {
        return class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
               \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Get order by ID (HPOS compatible)
     *
     * @param int $order_id Order ID
     * @return WC_Order|false
     */
    public static function get_order($order_id) {
        return wc_get_order($order_id);
    }

    /**
     * Get orders (HPOS compatible)
     *
     * @param array $args Query arguments
     * @return WC_Order[]
     */
    public static function get_orders($args = array()) {
        $default_args = array(
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        );

        $args = wp_parse_args($args, $default_args);

        if (self::is_hpos_enabled()) {
            return wc_get_orders($args);
        } else {
            // Fallback for legacy post-based orders
            return wc_get_orders($args);
        }
    }

    /**
     * Get order count (HPOS compatible)
     *
     * @param array $args Query arguments
     * @return int
     */
    public static function get_order_count($args = array()) {
        $args['return'] = 'ids';
        $args['limit'] = -1;
        
        $orders = self::get_orders($args);
        return count($orders);
    }

    /**
     * Get order IDs (HPOS compatible)
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_order_ids($args = array()) {
        $args['return'] = 'ids';
        return self::get_orders($args);
    }

    /**
     * Get orders by customer (HPOS compatible)
     *
     * @param int $customer_id Customer ID
     * @param array $args Additional arguments
     * @return WC_Order[]
     */
    public static function get_orders_by_customer($customer_id, $args = array()) {
        $args['customer_id'] = $customer_id;
        return self::get_orders($args);
    }

    /**
     * Get orders by status (HPOS compatible)
     *
     * @param string|array $status Order status(es)
     * @param array $args Additional arguments
     * @return WC_Order[]
     */
    public static function get_orders_by_status($status, $args = array()) {
        $args['status'] = $status;
        return self::get_orders($args);
    }

    /**
     * Get orders by date range (HPOS compatible)
     *
     * @param string $start_date Start date
     * @param string $end_date End date
     * @param array $args Additional arguments
     * @return WC_Order[]
     */
    public static function get_orders_by_date_range($start_date, $end_date, $args = array()) {
        $args['date_created'] = $start_date . '...' . $end_date;
        return self::get_orders($args);
    }

    /**
     * Get orders by product (HPOS compatible)
     *
     * @param int $product_id Product ID
     * @param array $args Additional arguments
     * @return WC_Order[]
     */
    public static function get_orders_by_product($product_id, $args = array()) {
        if (self::is_hpos_enabled()) {
            // Use HPOS-compatible query
            $args['meta_query'] = array(
                array(
                    'key' => '_product_id',
                    'value' => $product_id,
                    'compare' => '='
                )
            );
        } else {
            // Legacy query for post-based orders
            $args['meta_query'] = array(
                array(
                    'key' => '_product_id',
                    'value' => $product_id,
                    'compare' => '='
                )
            );
        }
        
        return self::get_orders($args);
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
        $order = self::get_order($order_id);
        
        if (!$order) {
            return false;
        }

        return $order->get_meta($key, $single);
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
        $order = self::get_order($order_id);
        
        if (!$order) {
            return false;
        }

        $order->update_meta_data($key, $value);
        $order->save();
        
        return true;
    }

    /**
     * Delete order meta (HPOS compatible)
     *
     * @param int $order_id Order ID
     * @param string $key Meta key
     * @return bool
     */
    public static function delete_order_meta($order_id, $key) {
        $order = self::get_order($order_id);
        
        if (!$order) {
            return false;
        }

        $order->delete_meta_data($key);
        $order->save();
        
        return true;
    }

    /**
     * Get order items (HPOS compatible)
     *
     * @param int $order_id Order ID
     * @param string $type Item type
     * @return WC_Order_Item[]
     */
    public static function get_order_items($order_id, $type = '') {
        $order = self::get_order($order_id);
        
        if (!$order) {
            return array();
        }

        return $order->get_items($type);
    }

    /**
     * Handle new order
     *
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function handle_new_order($order_id, $order) {
        // Log order creation
        IWP_Utilities::log(sprintf('New order created: %d', $order_id));
        
        // Add custom processing for new orders
        do_action('iwp_new_order', $order_id, $order);
    }

    /**
     * Handle order update
     *
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function handle_order_update($order_id, $order) {
        // Log order update
        IWP_Utilities::log(sprintf('Order updated: %d', $order_id));
        
        // Add custom processing for order updates
        do_action('iwp_order_updated', $order_id, $order);
    }

    /**
     * Handle order delete
     *
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function handle_order_delete($order_id, $order) {
        // Log order deletion
        IWP_Utilities::log(sprintf('Order deleted: %d', $order_id));
        
        // Clean up custom data
        $this->cleanup_order_data($order_id);
        
        // Add custom processing for order deletion
        do_action('iwp_order_deleted', $order_id, $order);
    }

    /**
     * Handle order trash
     *
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function handle_order_trash($order_id, $order) {
        // Log order trash
        IWP_Utilities::log(sprintf('Order trashed: %d', $order_id));
        
        // Add custom processing for order trash
        do_action('iwp_order_trashed', $order_id, $order);
    }

    /**
     * Handle order untrash
     *
     * @param int $order_id Order ID
     * @param WC_Order $order Order object
     */
    public function handle_order_untrash($order_id, $order) {
        // Log order untrash
        IWP_Utilities::log(sprintf('Order untrashed: %d', $order_id));
        
        // Add custom processing for order untrash
        do_action('iwp_order_untrashed', $order_id, $order);
    }

    /**
     * Handle order status change
     *
     * @param int $order_id Order ID
     * @param string $status_from Old status
     * @param string $status_to New status
     * @param WC_Order $order Order object
     */
    public function handle_order_status_change($order_id, $status_from, $status_to, $order) {
        // Log status change
        IWP_Utilities::log(sprintf('Order status changed: %d from %s to %s', $order_id, $status_from, $status_to));
        
        // Add custom processing for status changes
        do_action('iwp_order_status_changed', $order_id, $status_from, $status_to, $order);
    }

    /**
     * Handle new order item
     *
     * @param int $item_id Item ID
     * @param WC_Order_Item $item Item object
     * @param int $order_id Order ID
     * @param array $args Additional arguments (optional)
     */
    public function handle_new_order_item($item_id, $item, $order_id, $args = array()) {
        // Log new order item
        IWP_Utilities::log(sprintf('New order item added: %d to order %d', $item_id, $order_id));
        
        // Add custom processing for new order items
        do_action('iwp_new_order_item', $item_id, $item, $order_id, $args);
    }

    /**
     * Handle order item update
     *
     * @param int $item_id Item ID
     * @param WC_Order_Item $item Item object
     * @param array $args Additional arguments
     * @param array $data Item data (optional)
     */
    public function handle_order_item_update($item_id, $item, $args, $data = array()) {
        // Log order item update
        IWP_Utilities::log(sprintf('Order item updated: %d', $item_id));
        
        // Add custom processing for order item updates
        do_action('iwp_order_item_updated', $item_id, $item, $args, $data);
    }

    /**
     * Handle order item delete
     *
     * @param int $item_id Item ID
     * @param string $item_type Item type
     */
    public function handle_order_item_delete($item_id, $item_type) {
        // Log order item deletion
        IWP_Utilities::log(sprintf('Order item deleted: %d', $item_id));
        
        // Add custom processing for order item deletion
        do_action('iwp_order_item_deleted', $item_id, $item_type);
    }

    /**
     * Clean up order data
     *
     * @param int $order_id Order ID
     */
    private function cleanup_order_data($order_id) {
        global $wpdb;
        
        // Clean up custom order data from plugin tables
        $wpdb->delete(
            $wpdb->prefix . 'iwp_logs',
            array('order_id' => $order_id),
            array('%d')
        );
    }

    /**
     * Get order statistics (HPOS compatible)
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_order_statistics($args = array()) {
        $default_args = array(
            'date_from' => '',
            'date_to' => '',
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold'),
        );

        $args = wp_parse_args($args, $default_args);

        $query_args = array(
            'status' => $args['status'],
            'limit' => -1,
            'return' => 'objects',
        );

        if (!empty($args['date_from']) && !empty($args['date_to'])) {
            $query_args['date_created'] = $args['date_from'] . '...' . $args['date_to'];
        }

        $orders = self::get_orders($query_args);

        $stats = array(
            'total_orders' => count($orders),
            'total_sales' => 0,
            'average_order_value' => 0,
        );

        foreach ($orders as $order) {
            $stats['total_sales'] += $order->get_total();
        }

        if ($stats['total_orders'] > 0) {
            $stats['average_order_value'] = $stats['total_sales'] / $stats['total_orders'];
        }

        return $stats;
    }

    /**
     * Bulk update order meta (HPOS compatible)
     *
     * @param array $order_ids Order IDs
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool
     */
    public static function bulk_update_order_meta($order_ids, $key, $value) {
        if (empty($order_ids) || !is_array($order_ids)) {
            return false;
        }

        foreach ($order_ids as $order_id) {
            self::update_order_meta($order_id, $key, $value);
        }

        return true;
    }

    /**
     * Search orders (HPOS compatible)
     *
     * @param string $search_term Search term
     * @param array $args Additional arguments
     * @return WC_Order[]
     */
    public static function search_orders($search_term, $args = array()) {
        $args['search'] = $search_term;
        return self::get_orders($args);
    }
}