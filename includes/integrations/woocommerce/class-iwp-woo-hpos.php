<?php
/**
 * HPOS (High Performance Order Storage) compatibility class
 *
 * @package IWP
 * @since 0.0.1
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
     * Resolve the first argument of the meta helpers to a loaded WC_Abstract_Order.
     *
     * Accepts either an order ID (int or numeric string) or an already-loaded
     * WC_Abstract_Order (which covers WC_Order, WC_Subscription,
     * WC_Order_Refund). Callers in loops should pass the object directly to
     * avoid a redundant wc_get_order() lookup per iteration.
     *
     * Strict input contract:
     *   - WC_Abstract_Order instance         → returned as-is.
     *   - Positive numeric (int or "123")    → loaded; returns false (debug
     *                                          log) if no order exists.
     *   - Anything else (empty, 0, negative,
     *     non-numeric, array, null, object)  → warn-log the bad type, false.
     *
     * Assumes WooCommerce is loaded — the class file is only required by
     * iwp-wp-integration.php inside class_exists('WooCommerce').
     *
     * @param int|numeric-string|WC_Abstract_Order $order_or_id
     * @return WC_Abstract_Order|false
     */
    private static function resolve_order_arg($order_or_id) {
        if ($order_or_id instanceof WC_Abstract_Order) {
            return $order_or_id;
        }

        if (is_numeric($order_or_id) && ((int) $order_or_id) > 0) {
            $order = self::get_order((int) $order_or_id);
            if ($order instanceof WC_Abstract_Order) {
                return $order;
            }
            if (class_exists('IWP_Logger')) {
                IWP_Logger::debug('HPOS helper: order not found for ID', 'hpos-compat', array(
                    'order_id' => (int) $order_or_id,
                ));
            }
            return false;
        }

        // Anything else is a caller bug — log loud so it surfaces in dev.
        if (class_exists('IWP_Logger')) {
            IWP_Logger::warning('HPOS helper: invalid order argument', 'hpos-compat', array(
                'type'  => is_object($order_or_id) ? get_class($order_or_id) : gettype($order_or_id),
                'value' => is_scalar($order_or_id) ? (string) $order_or_id : null,
            ));
        }
        return false;
    }

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
     * Get orders — transparent HPOS/CPT compat wrapper around wc_get_orders().
     *
     * Callers pass a normal wc_get_orders() arg array, INCLUDING `meta_query`
     * in its standard WP_Query shape. This wrapper handles the data-store and
     * legacy-data quirks so callers don't have to branch on is_hpos_enabled().
     *
     * Why we don't pass `meta_query` through to wc_get_orders() on either store:
     *
     *   - CPT data store: the base `WC_Data_Store_WP::get_wp_query_args()`
     *     explicitly strips `meta_query` from $query_vars
     *     (class-wc-data-store-wp.php:284 — `'meta_query' === $key` → continue).
     *     WC 9.2+ additionally emits _doing_it_wrong when it sees the arg
     *     (class-wc-order-data-store-cpt.php:1076-1100). So `meta_query`
     *     never filters anything on CPT via the top-level arg.
     *
     *   - HPOS authoritative: `OrdersTableQuery` DOES support `meta_query`
     *     natively (OrdersTableQuery.php:791-793 → OrdersTableMetaQuery),
     *     but it only joins wp_wc_orders_meta. Orders whose IWP meta was
     *     written by pre-HPOS-fix plugin builds (via update_post_meta()) have
     *     their values in wp_postmeta and get silently skipped by the native
     *     HPOS query.
     *
     * Unified strategy (no branching on data store):
     *
     *   1. Extract `meta_query` from $args before calling wc_get_orders().
     *   2. Fetch orders using only first-class args (customer_id, status, limit, …).
     *      These are supported identically by both data stores with no notices.
     *   3. Evaluate the meta filter in PHP via self::get_order_meta(). That
     *      reader already handles the active-store / legacy-postmeta fallback
     *      and opportunistically migrates legacy values forward on first read,
     *      so a legacy order becomes visible once and repairs its own storage
     *      on the way through.
     *
     * Supported meta_query shape: an array of clauses each of the form
     *   array('key' => '<meta_key>', 'compare' => 'EXISTS')
     * plus an optional top-level 'relation' key (treated as OR, which is this
     * plugin's only usage). Clauses with other `compare` operators are logged
     * and skipped — extend the evaluator when a caller needs richer operators
     * rather than silently mis-filtering.
     *
     * Performance / memory: because the meta filter runs in PHP on the result
     * of the wc_get_orders() call, callers should bound the query with
     * first-class args (customer_id, status, limit, date_created, …) before
     * relying on meta_query. Passing `limit => -1` without any other scope is
     * safe only for small result sets (e.g. per-customer order lists). For
     * site-wide queries on large stores, paginate or add a customer/status
     * scope.
     *
     * @param array $args Query arguments.
     * @return WC_Order[]|int[] WC_Order objects by default; order IDs when the
     *                          caller passes 'return' => 'ids'.
     */
    public static function get_orders($args = array()) {
        $default_args = array(
            'limit'   => -1,
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'objects',
        );

        $args = wp_parse_args($args, $default_args);

        // Pull meta_query out before wc_get_orders() runs. Under CPT this
        // avoids the 9.2 notice; under HPOS this avoids the legacy-postmeta
        // blind spot. We evaluate it ourselves below.
        $meta_query = array();
        if (!empty($args['meta_query']) && is_array($args['meta_query'])) {
            $meta_query = $args['meta_query'];
            unset($args['meta_query']);
        }

        // Collect the keys to EXISTS-check. The plugin's meta_query usage is
        // limited to EXISTS clauses (optionally OR'd), so a flat list is
        // enough. A non-EXISTS compare indicates a caller this helper isn't
        // designed for; we log and skip that clause rather than pretend.
        $exists_keys = array();
        foreach ($meta_query as $clause_key => $clause) {
            if ($clause_key === 'relation') {
                continue; // Top-level relation — we always OR EXISTS keys.
            }
            if (!is_array($clause) || !isset($clause['key'])) {
                continue;
            }
            $compare = isset($clause['compare']) ? strtoupper($clause['compare']) : '=';
            if ($compare !== 'EXISTS') {
                if (class_exists('IWP_Logger')) {
                    IWP_Logger::warning('get_orders received unsupported meta_query compare; ignoring clause', 'hpos-compat', array(
                        'compare' => $compare,
                        'key'     => $clause['key'],
                    ));
                }
                continue;
            }
            $exists_keys[] = $clause['key'];
        }

        // PHP-side meta filtering needs the hydrated WC_Order. If the caller
        // asked for 'ids' we temporarily switch to objects, filter, then
        // project back to IDs at the end.
        $original_return = isset($args['return']) ? $args['return'] : 'objects';
        if (!empty($exists_keys)) {
            $args['return'] = 'objects';
        }

        $orders = wc_get_orders($args);

        // Fast path: no meta filter requested, or WC returned nothing to filter.
        if (empty($exists_keys) || empty($orders)) {
            return $orders;
        }

        $before_count = count($orders);
        $filtered     = array();
        foreach ($orders as $order) {
            foreach ($exists_keys as $meta_key) {
                // self::get_order_meta() — not $order->get_meta() — so the
                // wp_postmeta fallback + forward-migrate runs for legacy
                // values. We pass the $order object directly; the resolver
                // short-circuits on the instanceof branch so there's no
                // redundant wc_get_order() lookup per iteration. empty()
                // covers every "not set" shape ('', null, [], false) — the
                // filtered meta keys in this plugin are always arrays, so
                // the 0 / '0' false-positives of empty() don't bite.
                if (!empty(self::get_order_meta($order, $meta_key))) {
                    $filtered[] = $order;
                    break; // OR semantics — any match is enough.
                }
            }
        }

        if (class_exists('IWP_Logger')) {
            IWP_Logger::debug('get_orders meta_query filter applied', 'hpos-compat', array(
                'exists_keys'  => $exists_keys,
                'hpos_enabled' => self::is_hpos_enabled(),
                'before_count' => $before_count,
                'after_count'  => count($filtered),
            ));
        }

        // Restore the caller's requested return shape.
        if ($original_return === 'ids') {
            return array_map(function ($order) { return $order->get_id(); }, $filtered);
        }

        return $filtered;
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
     * Get order meta through the active WC data store, with legacy postmeta fallback.
     *
     * Lookup order:
     *   1. $order->get_meta($key) — reads from the data store the order was
     *      hydrated from (wp_wc_orders_meta under HPOS, wp_postmeta under CPT).
     *   2. get_post_meta($order_id, $key, true) — fallback for values written
     *      by pre-HPOS-fix plugin builds that used update_post_meta() directly.
     *      On a hit under HPOS, the value is migrated forward via
     *      $order->update_meta_data() + save_meta_data() so subsequent reads
     *      resolve via the active store without re-hitting this fallback.
     *
     * @param int|numeric-string|WC_Abstract_Order $order_or_id
     *        Order ID or a loaded order object. Pass the object in loops to
     *        avoid a redundant wc_get_order() lookup per iteration.
     * @param string $key    Meta key.
     * @param bool   $single Whether to return a single value.
     * @return mixed The meta value, or false when the order cannot be
     *               resolved and neither source has a value.
     */
    public static function get_order_meta($order_or_id, $key, $single = true) {
        $order = self::resolve_order_arg($order_or_id);
        if (!$order) {
            return false;
        }

        // Primary: active data store (HPOS or CPT) via the WC order object.
        $value = $order->get_meta($key, $single);
        if (!empty($value)) {
            return $value;
        }

        // Legacy fallback: pre-HPOS-fix plugin builds wrote via update_post_meta().
        // Under authoritative HPOS that data never reached wp_wc_orders_meta, so
        // $order->get_meta() returns empty above. Read wp_postmeta directly and,
        // if we find something, migrate it into the active store so the next
        // read resolves cleanly without re-entering this branch.
        $order_id = $order->get_id();
        $legacy   = get_post_meta($order_id, $key, $single);
        if (empty($legacy)) {
            return $value; // Preserve the original empty shape ('' or array()).
        }

        if (class_exists('IWP_Logger')) {
            IWP_Logger::info('Migrating legacy postmeta to active data store', 'hpos-compat', array(
                'order_id'     => $order_id,
                'key'          => $key,
                'hpos_enabled' => self::is_hpos_enabled(),
                'value_type'   => is_array($legacy) ? 'array' : gettype($legacy),
            ));
        }

        $order->update_meta_data($key, $legacy);
        $order->save_meta_data();

        return $legacy;
    }

    /**
     * Update order meta through the active WC data store (HPOS-safe).
     *
     * Uses $order->save_meta_data() rather than $order->save() so only the
     * meta change is persisted — no status-change or order-update lifecycle
     * hooks fire. Under HPOS writes to wp_wc_orders_meta; under CPT writes
     * to wp_postmeta; under HPOS compat/sync mode WC mirrors to both tables
     * on the same save.
     *
     * @param int|numeric-string|WC_Abstract_Order $order_or_id Order ID or loaded order.
     * @param string                               $key         Meta key.
     * @param mixed                                $value       Meta value.
     * @return bool True on success, false when the order cannot be resolved.
     */
    public static function update_order_meta($order_or_id, $key, $value) {
        $order = self::resolve_order_arg($order_or_id);
        if (!$order) {
            return false;
        }

        $order->update_meta_data($key, $value);
        $order->save_meta_data();

        if (class_exists('IWP_Logger')) {
            IWP_Logger::debug('Order meta written via active data store', 'hpos-compat', array(
                'order_id'     => $order->get_id(),
                'key'          => $key,
                'hpos_enabled' => self::is_hpos_enabled(),
                'value_type'   => is_array($value) ? 'array' : gettype($value),
            ));
        }

        return true;
    }

    /**
     * Delete order meta through the active WC data store (HPOS-safe).
     *
     * Uses $order->save_meta_data() rather than $order->save() so only the
     * meta removal is persisted — no status-change or order-update lifecycle
     * hooks fire. Under HPOS removes from wp_wc_orders_meta; under CPT removes
     * from wp_postmeta; under HPOS compat/sync mode WC mirrors the delete.
     *
     * @param int|numeric-string|WC_Abstract_Order $order_or_id Order ID or loaded order.
     * @param string                               $key         Meta key.
     * @return bool True on success, false when the order cannot be resolved.
     */
    public static function delete_order_meta($order_or_id, $key) {
        $order = self::resolve_order_arg($order_or_id);
        if (!$order) {
            return false;
        }

        $order->delete_meta_data($key);
        $order->save_meta_data();

        if (class_exists('IWP_Logger')) {
            IWP_Logger::debug('Order meta deleted via active data store', 'hpos-compat', array(
                'order_id'     => $order->get_id(),
                'key'          => $key,
                'hpos_enabled' => self::is_hpos_enabled(),
            ));
        }

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