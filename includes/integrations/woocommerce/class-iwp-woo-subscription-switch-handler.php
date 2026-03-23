<?php
/**
 * Subscription Switch Handler for IWP
 *
 * Handles WooCommerce Subscription switching (upgrade/downgrade)
 * and syncs plan changes with the InstaWP API.
 *
 * @package IWP
 * @since 0.0.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_Subscription_Switch_Handler class
 */
class IWP_Woo_Subscription_Switch_Handler {

    /**
     * Plan hierarchy for determining upgrade vs downgrade.
     * Maps plan_id => rank (higher = better plan).
     *
     * @var array
     */
    private static $plan_order = array(
        1  => 0,  // Free
        2  => 1,  // Sandbox
        8  => 2,  // Starter
        9  => 3,  // Plus
        10 => 4,  // Pro
        11 => 5,  // Turbo
        12 => 6,  // Elite
    );

    /**
     * Maximum retry attempts for failed plan changes
     *
     * @var int
     */
    private static $max_retries = 3;

    /**
     * Constructor
     */
    public function __construct() {
        if (!$this->is_subscriptions_active()) {
            return;
        }

        $this->init_hooks();
    }

    /**
     * Check if WooCommerce Subscriptions is active
     *
     * @return bool
     */
    private function is_subscriptions_active() {
        return class_exists('WC_Subscriptions') || function_exists('wcs_get_subscription');
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enable WCS switching when plugin is active
        add_filter('option_woocommerce_subscriptions_allow_switching', array($this, 'ensure_switching_enabled'));
        add_filter('option_woocommerce_subscriptions_apportion_recurring_price', array($this, 'default_apportion_recurring_price'));

        // Core switch handler — fires after switch order is paid/completed
        add_action('woocommerce_subscriptions_switch_completed', array($this, 'handle_switch_completed'), 10, 1);

        // Per-item switch handler — fires for each switched item
        add_action('woocommerce_subscription_item_switched', array($this, 'handle_item_switched'), 10, 4);

        // Control switch eligibility
        add_filter('woocommerce_subscriptions_can_item_be_switched', array($this, 'filter_can_item_be_switched'), 10, 3);

        // Retry mechanism for failed plan changes
        add_action('iwp_retry_plan_change', array($this, 'handle_retry_plan_change'), 10, 4);
    }

    /**
     * Ensure WCS switching is enabled for both variable and grouped subscriptions
     *
     * @param string $value Current option value
     * @return string
     */
    public function ensure_switching_enabled($value) {
        if ($value === 'no' || empty($value)) {
            return 'variable_grouped';
        }
        return $value;
    }

    /**
     * Set default proration for recurring price
     *
     * @param string $value Current option value
     * @return string
     */
    public function default_apportion_recurring_price($value) {
        if ($value === 'no' || empty($value)) {
            return 'yes';
        }
        return $value;
    }

    /**
     * Handle switch completed — primary handler
     *
     * Fires after the switch order is paid/completed.
     * Processes all switched items and updates InstaWP plans.
     *
     * @param WC_Order $order The switch order
     */
    public function handle_switch_completed($order) {
        if (!is_a($order, 'WC_Abstract_Order')) {
            $order = wc_get_order($order);
        }

        if (!$order) {
            IWP_Logger::error('Invalid order passed to handle_switch_completed', 'switch-handler');
            return;
        }

        // Prevent double processing
        $already_processed = $order->get_meta('_iwp_switch_processed');
        if ($already_processed === 'yes') {
            IWP_Logger::debug('Switch already processed for order', 'switch-handler', array(
                'order_id' => $order->get_id()
            ));
            return;
        }

        IWP_Logger::info('Processing subscription switch', 'switch-handler', array(
            'order_id' => $order->get_id()
        ));

        if (!function_exists('wcs_get_subscriptions_for_switch_order')) {
            IWP_Logger::error('wcs_get_subscriptions_for_switch_order not available', 'switch-handler');
            return;
        }

        $subscriptions = wcs_get_subscriptions_for_switch_order($order);

        if (empty($subscriptions)) {
            IWP_Logger::warning('No subscriptions found for switch order', 'switch-handler', array(
                'order_id' => $order->get_id()
            ));
            return;
        }

        $results = array(
            'upgraded' => 0,
            'downgraded' => 0,
            'skipped' => 0,
            'failed' => 0,
        );

        foreach ($subscriptions as $subscription) {
            $this->process_subscription_switch($order, $subscription, $results);
        }

        // Mark as processed
        $order->update_meta_data('_iwp_switch_processed', 'yes');
        $order->save();

        IWP_Logger::info('Subscription switch processing complete', 'switch-handler', array(
            'order_id' => $order->get_id(),
            'results' => $results
        ));
    }

    /**
     * Handle individual item switched — fallback handler
     *
     * @param WC_Order $order
     * @param WC_Subscription $subscription
     * @param WC_Order_Item $new_item
     * @param WC_Order_Item $old_item
     */
    public function handle_item_switched($order, $subscription, $new_item, $old_item) {
        if (!is_a($order, 'WC_Abstract_Order')) {
            $order = wc_get_order($order);
        }

        if (!$order) {
            return;
        }

        // If already processed by handle_switch_completed, skip
        $already_processed = $order->get_meta('_iwp_switch_processed');
        if ($already_processed === 'yes') {
            return;
        }

        // Resolve items — WCS may pass item IDs (int) or WC_Order_Item objects
        if (is_numeric($new_item)) {
            $new_item = $subscription->get_item($new_item);
        }
        if (is_numeric($old_item)) {
            $old_item = $subscription->get_item($old_item);
        }

        if (!$new_item || !$old_item) {
            IWP_Logger::warning('Could not resolve switch items', 'switch-handler', array(
                'subscription_id' => $subscription->get_id(),
            ));
            return;
        }

        // Get plan IDs
        $new_product_id = $new_item->get_variation_id() ?: $new_item->get_product_id();
        $old_product_id = $old_item->get_variation_id() ?: $old_item->get_product_id();

        $new_plan_id = $this->get_plan_id_for_product($new_product_id);
        $old_plan_id = $this->get_plan_id_for_product($old_product_id);

        if (empty($new_plan_id) || $new_plan_id === $old_plan_id) {
            return;
        }

        // Find sites and process
        $sites = $this->find_subscription_sites($subscription);

        foreach ($sites as $site_info) {
            $site_id = $site_info['site_id'];
            if (!empty($site_id)) {
                $this->process_plan_change($site_id, $old_plan_id, $new_plan_id, $subscription, $order);
            }
        }
    }

    /**
     * Filter whether an item can be switched
     *
     * @param bool $can_switch
     * @param WC_Order_Item $item
     * @param WC_Subscription $subscription
     * @return bool
     */
    public function filter_can_item_be_switched($can_switch, $item, $subscription) {
        if (!$can_switch) {
            return false;
        }

        $product_id = $item->get_variation_id() ?: $item->get_product_id();
        $plan_id = $this->get_plan_id_for_product($product_id);

        // If this isn't an InstaWP product, don't interfere
        if (empty($plan_id)) {
            return $can_switch;
        }

        // Check if subscription has associated sites
        $sites = $this->find_subscription_sites($subscription);
        if (empty($sites)) {
            IWP_Logger::warning('No sites found for subscription, checking if switch should be blocked', 'switch-handler', array(
                'subscription_id' => $subscription->get_id(),
                'product_id' => $product_id,
            ));
        }

        return $can_switch;
    }

    /**
     * Process subscription switch for a single subscription
     *
     * @param WC_Order $order
     * @param WC_Subscription $subscription
     * @param array &$results
     */
    private function process_subscription_switch($order, $subscription, &$results) {
        // Get the new items from the subscription (after switch)
        foreach ($subscription->get_items() as $item) {
            $new_product_id = $item->get_variation_id() ?: $item->get_product_id();
            $new_plan_id = $this->get_plan_id_for_product($new_product_id);

            if (empty($new_plan_id)) {
                $results['skipped']++;
                continue;
            }

            // Try to find the old plan from the switch order's line items
            $old_plan_id = $this->get_old_plan_from_switch_order($order, $item);

            if (empty($old_plan_id) || $old_plan_id === $new_plan_id) {
                IWP_Logger::debug('No plan change detected or same plan', 'switch-handler', array(
                    'subscription_id' => $subscription->get_id(),
                    'old_plan_id' => $old_plan_id,
                    'new_plan_id' => $new_plan_id,
                ));
                $results['skipped']++;
                continue;
            }

            // Find sites linked to this subscription
            $sites = $this->find_subscription_sites($subscription);

            if (empty($sites)) {
                IWP_Logger::warning('No sites found for subscription during switch', 'switch-handler', array(
                    'subscription_id' => $subscription->get_id(),
                ));
                $results['skipped']++;
                continue;
            }

            // Process plan change for each site
            foreach ($sites as $site_info) {
                $site_id = $site_info['site_id'];
                if (empty($site_id)) {
                    continue;
                }

                // Check per-site idempotency
                $site_processed = $order->get_meta('_iwp_switch_processed_' . $site_id);
                if ($site_processed === 'yes') {
                    continue;
                }

                $success = $this->process_plan_change($site_id, $old_plan_id, $new_plan_id, $subscription, $order);

                if ($success) {
                    $is_upgrade = $this->is_plan_upgrade($old_plan_id, $new_plan_id);
                    $results[$is_upgrade ? 'upgraded' : 'downgraded']++;
                } else {
                    $results['failed']++;
                }
            }
        }
    }

    /**
     * Get the old plan ID from the switch order's metadata
     *
     * @param WC_Order $order
     * @param WC_Order_Item $current_item
     * @return string|null
     */
    private function get_old_plan_from_switch_order($order, $current_item) {
        // Check order line items for switch metadata
        foreach ($order->get_items() as $order_item) {
            $switched_item_id = $order_item->get_meta('_switched_subscription_item_id');

            if (empty($switched_item_id)) {
                continue;
            }

            // Find the old subscription item and its product
            $old_product_id = $order_item->get_meta('_switched_subscription_old_product_id');
            $old_variation_id = $order_item->get_meta('_switched_subscription_old_variation_id');

            $lookup_id = !empty($old_variation_id) ? $old_variation_id : $old_product_id;

            if (!empty($lookup_id)) {
                return $this->get_plan_id_for_product($lookup_id);
            }
        }

        return null;
    }

    /**
     * Process a plan change for a single site
     *
     * @param string $site_id InstaWP site ID
     * @param string $old_plan_id Previous plan ID
     * @param string $new_plan_id New plan ID
     * @param WC_Subscription $subscription
     * @param WC_Order $order
     * @return bool Success
     */
    private function process_plan_change($site_id, $old_plan_id, $new_plan_id, $subscription, $order) {
        $is_upgrade = $this->is_plan_upgrade($old_plan_id, $new_plan_id);
        $direction = $is_upgrade ? 'upgrade' : 'downgrade';

        $old_plan_name = IWP_Service::get_plan_name_by_id($old_plan_id);
        $new_plan_name = IWP_Service::get_plan_name_by_id($new_plan_id);

        IWP_Logger::info("Processing plan {$direction}", 'switch-handler', array(
            'site_id' => $site_id,
            'old_plan_id' => $old_plan_id,
            'old_plan_name' => $old_plan_name,
            'new_plan_id' => $new_plan_id,
            'new_plan_name' => $new_plan_name,
            'subscription_id' => $subscription->get_id(),
            'order_id' => $order->get_id(),
        ));

        try {
            // Call InstaWP API to change the plan
            $api_result = IWP_Service::upgrade_site_plan($site_id, $new_plan_id);

            if (is_wp_error($api_result)) {
                $error_message = $api_result->get_error_message();

                IWP_Logger::error("Plan {$direction} API call failed", 'switch-handler', array(
                    'site_id' => $site_id,
                    'new_plan_id' => $new_plan_id,
                    'error' => $error_message,
                ));

                // Add warning notes
                $note = sprintf(
                    __('InstaWP: Failed to %s site %s from %s to %s. Error: %s. Manual intervention may be required.', 'iwp-wp-integration'),
                    $direction,
                    $site_id,
                    $old_plan_name,
                    $new_plan_name,
                    $error_message
                );
                $subscription->add_order_note($note, false, false);
                $order->add_order_note($note);

                // Store failure on switch order so the thank-you page shows the error
                $fail_entry = array(
                    'product_id' => null,
                    'product_name' => $new_plan_name,
                    'action' => 'upgraded',
                    'site_data' => array(
                        'site_id' => $site_id,
                        'status' => 'failed',
                        'plan_id' => $new_plan_id,
                    ),
                );
                $existing_sites = get_post_meta($order->get_id(), '_iwp_sites_created', true);
                if (!is_array($existing_sites)) {
                    $existing_sites = array();
                }
                $existing_sites[] = $fail_entry;
                update_post_meta($order->get_id(), '_iwp_sites_created', $existing_sites);

                // Schedule retry
                $this->schedule_retry($site_id, $new_plan_id, $subscription->get_id(), $order->get_id());

                return false;
            }

            // Update local DB tracking
            $upgrade_data = array(
                'order_id' => $order->get_id(),
                'product_id' => null,
                'upgrade_response' => $api_result,
            );

            // Extract site details from API response if available
            if (isset($api_result['url'])) {
                $upgrade_data['site_url'] = $api_result['url'];
            }
            if (isset($api_result['site_meta']['wp_username'])) {
                $upgrade_data['wp_username'] = $api_result['site_meta']['wp_username'];
            }
            if (isset($api_result['site_meta']['wp_password'])) {
                $upgrade_data['wp_password'] = $api_result['site_meta']['wp_password'];
            }
            if (isset($api_result['s_hash'])) {
                $upgrade_data['s_hash'] = $api_result['s_hash'];
            }

            IWP_Sites_Model::update_plan($site_id, $new_plan_id, $upgrade_data);

            // Store site data on the switch order so the thank-you page can display it
            $db_site = IWP_Sites_Model::get_by_site_id($site_id);
            $switch_site_entry = array(
                'product_id' => null,
                'product_name' => $new_plan_name,
                'action' => 'upgraded',
                'site_data' => array(
                    'site_id' => $site_id,
                    'site_url' => $db_site ? $db_site->site_url : ($upgrade_data['site_url'] ?? ''),
                    'wp_username' => $db_site ? $db_site->wp_username : ($upgrade_data['wp_username'] ?? ''),
                    'wp_password' => $db_site ? $db_site->wp_password : ($upgrade_data['wp_password'] ?? ''),
                    's_hash' => $db_site ? $db_site->s_hash : ($upgrade_data['s_hash'] ?? ''),
                    'status' => 'completed',
                    'plan_id' => $new_plan_id,
                ),
            );
            $existing_sites = get_post_meta($order->get_id(), '_iwp_sites_created', true);
            if (!is_array($existing_sites)) {
                $existing_sites = array();
            }
            $existing_sites[] = $switch_site_entry;
            update_post_meta($order->get_id(), '_iwp_sites_created', $existing_sites);

            // Mark per-site as processed
            $order->update_meta_data('_iwp_switch_processed_' . $site_id, 'yes');
            $order->save();

            // Add success notes
            $note = sprintf(
                __('InstaWP: Site %s %sd from %s to %s.', 'iwp-wp-integration'),
                $site_id,
                $direction,
                $old_plan_name,
                $new_plan_name
            );
            $subscription->add_order_note($note, false, false);
            $order->add_order_note($note);

            IWP_Logger::info("Plan {$direction} completed successfully", 'switch-handler', array(
                'site_id' => $site_id,
                'old_plan' => $old_plan_name,
                'new_plan' => $new_plan_name,
            ));

            return true;

        } catch (Exception $e) {
            IWP_Logger::error("Exception during plan {$direction}", 'switch-handler', array(
                'site_id' => $site_id,
                'exception' => $e->getMessage(),
            ));

            $note = sprintf(
                __('InstaWP: Exception during %s for site %s: %s', 'iwp-wp-integration'),
                $direction,
                $site_id,
                $e->getMessage()
            );
            $subscription->add_order_note($note, false, false);

            $this->schedule_retry($site_id, $new_plan_id, $subscription->get_id(), $order->get_id());

            return false;
        }
    }

    /**
     * Schedule a retry for a failed plan change
     *
     * @param string $site_id
     * @param string $new_plan_id
     * @param int $subscription_id
     * @param int $order_id
     * @param int $attempt Current attempt number
     */
    private function schedule_retry($site_id, $new_plan_id, $subscription_id, $order_id, $attempt = 1) {
        if ($attempt > self::$max_retries) {
            IWP_Logger::error('Max retries exceeded for plan change', 'switch-handler', array(
                'site_id' => $site_id,
                'new_plan_id' => $new_plan_id,
                'subscription_id' => $subscription_id,
            ));

            $subscription = wcs_get_subscription($subscription_id);
            if ($subscription) {
                $subscription->add_order_note(
                    sprintf(
                        __('InstaWP: CRITICAL - Plan change for site %s failed after %d attempts. Manual intervention required.', 'iwp-wp-integration'),
                        $site_id,
                        self::$max_retries
                    ),
                    false,
                    false
                );
            }

            do_action('iwp_plan_change_failed_permanently', $site_id, $new_plan_id, $subscription_id);
            return;
        }

        // Exponential backoff: 5min, 15min, 45min
        $delay = 300 * pow(3, $attempt - 1);

        wp_schedule_single_event(
            time() + $delay,
            'iwp_retry_plan_change',
            array($site_id, $new_plan_id, $subscription_id, $attempt)
        );

        IWP_Logger::info('Scheduled plan change retry', 'switch-handler', array(
            'site_id' => $site_id,
            'new_plan_id' => $new_plan_id,
            'attempt' => $attempt,
            'delay_seconds' => $delay,
        ));
    }

    /**
     * Handle retry of a failed plan change
     *
     * @param string $site_id
     * @param string $new_plan_id
     * @param int $subscription_id
     * @param int $attempt
     */
    public function handle_retry_plan_change($site_id, $new_plan_id, $subscription_id, $attempt = 1) {
        IWP_Logger::info('Retrying plan change', 'switch-handler', array(
            'site_id' => $site_id,
            'new_plan_id' => $new_plan_id,
            'subscription_id' => $subscription_id,
            'attempt' => $attempt,
        ));

        // Check if site is already on the target plan
        $current_site = IWP_Sites_Model::get_by_site_id($site_id);
        if ($current_site && $current_site->plan_id == $new_plan_id) {
            IWP_Logger::info('Site already on target plan, skipping retry', 'switch-handler', array(
                'site_id' => $site_id,
                'plan_id' => $new_plan_id,
            ));
            return;
        }

        try {
            $api_result = IWP_Service::upgrade_site_plan($site_id, $new_plan_id);

            if (is_wp_error($api_result)) {
                IWP_Logger::warning('Plan change retry failed', 'switch-handler', array(
                    'site_id' => $site_id,
                    'attempt' => $attempt,
                    'error' => $api_result->get_error_message(),
                ));

                $this->schedule_retry($site_id, $new_plan_id, $subscription_id, 0, $attempt + 1);
                return;
            }

            // Success — update local DB
            $upgrade_data = array('upgrade_response' => $api_result);
            if (isset($api_result['url'])) {
                $upgrade_data['site_url'] = $api_result['url'];
            }

            IWP_Sites_Model::update_plan($site_id, $new_plan_id, $upgrade_data);

            $subscription = wcs_get_subscription($subscription_id);
            if ($subscription) {
                $new_plan_name = IWP_Service::get_plan_name_by_id($new_plan_id);
                $subscription->add_order_note(
                    sprintf(
                        __('InstaWP: Plan change for site %s completed on retry attempt %d. New plan: %s.', 'iwp-wp-integration'),
                        $site_id,
                        $attempt,
                        $new_plan_name
                    ),
                    false,
                    false
                );
            }

            IWP_Logger::info('Plan change retry succeeded', 'switch-handler', array(
                'site_id' => $site_id,
                'new_plan_id' => $new_plan_id,
                'attempt' => $attempt,
            ));

        } catch (Exception $e) {
            IWP_Logger::error('Exception during plan change retry', 'switch-handler', array(
                'site_id' => $site_id,
                'attempt' => $attempt,
                'exception' => $e->getMessage(),
            ));

            $this->schedule_retry($site_id, $new_plan_id, $subscription_id, 0, $attempt + 1);
        }
    }

    /**
     * Get plan ID for a product (handles both simple and variation)
     *
     * @param int $product_id Product or variation ID
     * @return string|null Plan ID or null
     */
    private function get_plan_id_for_product($product_id) {
        // Check the product/variation directly
        $plan_id = get_post_meta($product_id, '_iwp_selected_plan', true);

        if (!empty($plan_id)) {
            return $plan_id;
        }

        // For variations, fall back to parent product
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('subscription_variation')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id) {
                return get_post_meta($parent_id, '_iwp_selected_plan', true) ?: null;
            }
        }

        return null;
    }

    /**
     * Determine if a plan change is an upgrade
     *
     * @param string|int $old_plan_id
     * @param string|int $new_plan_id
     * @return bool
     */
    private function is_plan_upgrade($old_plan_id, $new_plan_id) {
        $old_rank = isset(self::$plan_order[(int) $old_plan_id]) ? self::$plan_order[(int) $old_plan_id] : -1;
        $new_rank = isset(self::$plan_order[(int) $new_plan_id]) ? self::$plan_order[(int) $new_plan_id] : -1;

        return $new_rank > $old_rank;
    }

    /**
     * Find sites associated with a subscription
     *
     * Reuses the same logic as IWP_Woo_Subscriptions_Integration::find_subscription_sites()
     *
     * @param WC_Subscription $subscription
     * @return array
     */
    private function find_subscription_sites($subscription) {
        $sites = array();

        // Method 1: Get sites from parent order
        $parent_order_id = $subscription->get_parent_id();
        if ($parent_order_id) {
            $parent_sites = get_post_meta($parent_order_id, '_iwp_sites_created', true);
            if (!empty($parent_sites) && is_array($parent_sites)) {
                foreach ($parent_sites as $site) {
                    // site_id may be at top level or nested inside site_data
                    $site_id = $site['site_id'] ?? $site['id'] ?? '';
                    if (empty($site_id) && isset($site['site_data']['site_id'])) {
                        $site_id = $site['site_data']['site_id'];
                    }
                    $sites[] = array(
                        'site_id' => $site_id,
                        'order_id' => $parent_order_id,
                        'source' => 'parent_order',
                    );
                }
            }
        }

        // Method 2: Get sites from the custom DB table
        if (class_exists('IWP_Sites_Model') && $parent_order_id) {
            $db_sites = IWP_Sites_Model::get_by_order_id($parent_order_id);
            if (!empty($db_sites)) {
                foreach ($db_sites as $db_site) {
                    $sites[] = array(
                        'site_id' => $db_site->site_id,
                        'order_id' => $parent_order_id,
                        'source' => 'database',
                    );
                }
            }
        }

        // Method 3: Get sites from renewal orders
        $related_orders = $subscription->get_related_orders('all', 'renewal');
        foreach ($related_orders as $order_id) {
            $order_sites = get_post_meta($order_id, '_iwp_sites_created', true);
            if (!empty($order_sites) && is_array($order_sites)) {
                foreach ($order_sites as $site) {
                    $site_id = $site['site_id'] ?? $site['id'] ?? '';
                    if (empty($site_id) && isset($site['site_data']['site_id'])) {
                        $site_id = $site['site_data']['site_id'];
                    }
                    $sites[] = array(
                        'site_id' => $site_id,
                        'order_id' => $order_id,
                        'source' => 'renewal_order',
                    );
                }
            }
        }

        // Deduplicate by site_id
        $unique_sites = array();
        $seen_ids = array();

        foreach ($sites as $site) {
            if (!empty($site['site_id']) && !in_array($site['site_id'], $seen_ids)) {
                $unique_sites[] = $site;
                $seen_ids[] = $site['site_id'];
            }
        }

        return $unique_sites;
    }
}
