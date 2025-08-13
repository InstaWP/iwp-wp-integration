<?php
/**
 * WooCommerce Subscriptions Integration for IWP
 *
 * Handles automatic site status management based on subscription payment status
 *
 * @package IWP
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_Subscriptions_Integration class
 */
class IWP_Woo_Subscriptions_Integration {

    /**
     * Grace period in hours before marking sites as temporary
     *
     * @var int
     */
    private $grace_period_hours = 24;

    /**
     * Constructor
     */
    public function __construct() {
        // Only initialize if WooCommerce Subscriptions is active
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
        // Payment failure hooks
        add_action('woocommerce_subscription_payment_failed', array($this, 'handle_payment_failed'), 10, 2);
        add_action('woocommerce_subscription_renewal_payment_failed', array($this, 'handle_renewal_payment_failed'), 10, 2);
        
        // Payment success hooks
        add_action('woocommerce_subscription_payment_complete', array($this, 'handle_payment_complete'), 10, 2);
        add_action('woocommerce_subscription_renewal_payment_complete', array($this, 'handle_renewal_payment_complete'), 10, 2);
        
        // Subscription status change hooks
        add_action('woocommerce_subscription_status_cancelled', array($this, 'handle_subscription_cancelled'), 10, 2);
        add_action('woocommerce_subscription_status_expired', array($this, 'handle_subscription_expired'), 10, 2);
        add_action('woocommerce_subscription_status_on-hold', array($this, 'handle_subscription_on_hold'), 10, 2);
        add_action('woocommerce_subscription_status_active', array($this, 'handle_subscription_active'), 10, 2);

        // Admin interface enhancements
        add_action('add_meta_boxes', array($this, 'add_subscription_meta_box'));
        add_action('wp_ajax_iwp_force_site_status', array($this, 'ajax_force_site_status'));
    }

    /**
     * Handle subscription payment failed
     *
     * @param WC_Subscription $subscription
     * @param WC_Order $order
     */
    public function handle_payment_failed($subscription, $order = null) {
        IWP_Logger::warning('Subscription payment failed', 'subscriptions', array(
            'subscription_id' => $subscription->get_id(),
            'order_id' => $order ? $order->get_id() : 'N/A'
        ));

        $this->process_subscription_status_change($subscription, false, 'payment_failed', $order);
    }

    /**
     * Handle subscription renewal payment failed
     *
     * @param WC_Subscription $subscription
     * @param WC_Order $order
     */
    public function handle_renewal_payment_failed($subscription, $order) {
        IWP_Logger::warning('Subscription renewal payment failed', 'subscriptions', array(
            'subscription_id' => $subscription->get_id(),
            'order_id' => $order->get_id()
        ));

        $this->process_subscription_status_change($subscription, false, 'renewal_payment_failed', $order);
    }

    /**
     * Handle subscription payment complete
     *
     * @param WC_Subscription $subscription
     * @param WC_Order $order
     */
    public function handle_payment_complete($subscription, $order = null) {
        IWP_Logger::info('Subscription payment completed', 'subscriptions', array(
            'subscription_id' => $subscription->get_id(),
            'order_id' => $order ? $order->get_id() : 'N/A'
        ));

        $this->process_subscription_status_change($subscription, true, 'payment_complete', $order);
    }

    /**
     * Handle subscription renewal payment complete
     *
     * @param WC_Subscription $subscription
     * @param WC_Order $order
     */
    public function handle_renewal_payment_complete($subscription, $order) {
        IWP_Logger::info('Subscription renewal payment completed', 'subscriptions', array(
            'subscription_id' => $subscription->get_id(),
            'order_id' => $order->get_id()
        ));

        $this->process_subscription_status_change($subscription, true, 'renewal_payment_complete', $order);
    }

    /**
     * Handle subscription cancelled
     *
     * @param WC_Subscription $subscription
     * @param string $old_status Optional - may not be passed by all hooks
     */
    public function handle_subscription_cancelled($subscription, $old_status = '') {
        IWP_Logger::warning('Subscription cancelled', 'subscriptions', array(
            'subscription_id' => $subscription->get_id(),
            'old_status' => $old_status
        ));

        $this->process_subscription_status_change($subscription, false, 'cancelled');
    }

    /**
     * Handle subscription expired
     *
     * @param WC_Subscription $subscription
     * @param string $old_status Optional - may not be passed by all hooks
     */
    public function handle_subscription_expired($subscription, $old_status = '') {
        IWP_Logger::warning('Subscription expired', 'subscriptions', array(
            'subscription_id' => $subscription->get_id(),
            'old_status' => $old_status
        ));

        $this->process_subscription_status_change($subscription, false, 'expired');
    }

    /**
     * Handle subscription on hold
     *
     * @param WC_Subscription $subscription
     * @param string $old_status Optional - may not be passed by all hooks
     */
    public function handle_subscription_on_hold($subscription, $old_status = '') {
        IWP_Logger::info('Subscription on hold', 'subscriptions', array(
            'subscription_id' => $subscription->get_id(),
            'old_status' => $old_status
        ));

        $this->process_subscription_status_change($subscription, false, 'on_hold');
    }

    /**
     * Handle subscription active
     *
     * @param WC_Subscription $subscription  
     * @param string $old_status Optional - may not be passed by all hooks
     */
    public function handle_subscription_active($subscription, $old_status = '') {
        IWP_Logger::info('Subscription activated', 'subscriptions', array(
            'subscription_id' => $subscription->get_id(),
            'old_status' => $old_status
        ));

        $this->process_subscription_status_change($subscription, true, 'activated');
    }

    /**
     * Process subscription status change and update sites
     *
     * @param WC_Subscription $subscription
     * @param bool $make_permanent
     * @param string $reason
     * @param WC_Order|null $order
     */
    private function process_subscription_status_change($subscription, $make_permanent, $reason, $order = null) {
        // Find all sites associated with this subscription
        $sites = $this->find_subscription_sites($subscription);
        
        if (empty($sites)) {
            IWP_Logger::info('No sites found for subscription', 'subscriptions', array(
                'subscription_id' => $subscription->get_id(),
                'reason' => $reason
            ));
            return;
        }

        $results = array();
        $deleted_sites = array();
        
        foreach ($sites as $site_info) {
            $site_id = $site_info['site_id'];
            $order_id = $site_info['order_id'];
            
            try {
                // Update site status using the service method
                $result = IWP_Service::set_permanent($site_id, $make_permanent);
                
                if (is_wp_error($result)) {
                    // Check if site was deleted
                    if ($this->is_site_deleted_error($result)) {
                        $deleted_sites[] = array(
                            'site_id' => $site_id,
                            'order_id' => $order_id,
                            'error' => $result->get_error_message()
                        );
                    } else {
                        IWP_Logger::error('Failed to update site status', 'subscriptions', array(
                            'site_id' => $site_id,
                            'subscription_id' => $subscription->get_id(),
                            'error' => $result->get_error_message()
                        ));
                    }
                } else {
                    $results[] = array(
                        'site_id' => $site_id,
                        'order_id' => $order_id,
                        'changed' => $result['changed'],
                        'new_status' => $result['new_status']
                    );
                }
                
            } catch (Exception $e) {
                IWP_Logger::error('Exception updating site status', 'subscriptions', array(
                    'site_id' => $site_id,
                    'subscription_id' => $subscription->get_id(),
                    'exception' => $e->getMessage()
                ));
            }
        }
        
        // Add notes to subscription and related orders
        $this->add_subscription_notes($subscription, $results, $deleted_sites, $reason, $make_permanent, $order);
    }

    /**
     * Find sites associated with a subscription
     *
     * @param WC_Subscription $subscription
     * @return array Array of site info with site_id and order_id
     */
    private function find_subscription_sites($subscription) {
        $sites = array();
        
        // Method 1: Get sites from parent order
        $parent_order_id = $subscription->get_parent_id();
        if ($parent_order_id) {
            $parent_sites = $this->get_order_sites($parent_order_id);
            foreach ($parent_sites as $site) {
                $sites[] = array(
                    'site_id' => $site['site_id'] ?? $site['id'] ?? '',
                    'order_id' => $parent_order_id,
                    'source' => 'parent_order'
                );
            }
        }
        
        // Method 2: Get sites from renewal orders
        $related_orders = $subscription->get_related_orders('all', 'renewal');
        foreach ($related_orders as $order_id) {
            $order_sites = $this->get_order_sites($order_id);
            foreach ($order_sites as $site) {
                $sites[] = array(
                    'site_id' => $site['site_id'] ?? $site['id'] ?? '',
                    'order_id' => $order_id,
                    'source' => 'renewal_order'
                );
            }
        }
        
        // Method 3: Query by customer email (fallback)
        if (empty($sites)) {
            $customer_email = $subscription->get_billing_email();
            if ($customer_email) {
                $db_sites = $this->find_sites_by_customer_email($customer_email);
                foreach ($db_sites as $site) {
                    $sites[] = array(
                        'site_id' => $site['site_id'],
                        'order_id' => $site['order_id'] ?? '',
                        'source' => 'customer_email'
                    );
                }
            }
        }
        
        // Remove duplicates based on site_id
        $unique_sites = array();
        $seen_site_ids = array();
        
        foreach ($sites as $site) {
            if (!empty($site['site_id']) && !in_array($site['site_id'], $seen_site_ids)) {
                $unique_sites[] = $site;
                $seen_site_ids[] = $site['site_id'];
            }
        }
        
        IWP_Logger::info('Found sites for subscription', 'subscriptions', array(
            'subscription_id' => $subscription->get_id(),
            'sites_count' => count($unique_sites),
            'sites' => $unique_sites
        ));
        
        return $unique_sites;
    }

    /**
     * Get sites from an order
     *
     * @param int $order_id
     * @return array
     */
    private function get_order_sites($order_id) {
        $sites_created = get_post_meta($order_id, '_iwp_sites_created', true);
        
        if (empty($sites_created) || !is_array($sites_created)) {
            return array();
        }
        
        return $sites_created;
    }

    /**
     * Find sites by customer email (fallback method)
     *
     * @param string $customer_email
     * @return array
     */
    private function find_sites_by_customer_email($customer_email) {
        if (!class_exists('IWP_Sites_Model')) {
            return array();
        }
        
        try {
            // This would need to be implemented in IWP_Sites_Model
            // For now, return empty array as this is a fallback method
            return array();
        } catch (Exception $e) {
            IWP_Logger::error('Error finding sites by customer email', 'subscriptions', array(
                'email' => $customer_email,
                'error' => $e->getMessage()
            ));
            return array();
        }
    }

    /**
     * Check if error indicates site was deleted
     *
     * @param WP_Error $error
     * @return bool
     */
    private function is_site_deleted_error($error) {
        $error_message = $error->get_error_message();
        
        // Check for common deletion/not found error patterns
        $deletion_indicators = array(
            'site not found',
            '404',
            'not found',
            'deleted',
            'does not exist',
            'invalid site'
        );
        
        foreach ($deletion_indicators as $indicator) {
            if (stripos($error_message, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Add notes to subscription and related orders
     *
     * @param WC_Subscription $subscription
     * @param array $results
     * @param array $deleted_sites
     * @param string $reason
     * @param bool $make_permanent
     * @param WC_Order|null $order
     */
    private function add_subscription_notes($subscription, $results, $deleted_sites, $reason, $make_permanent, $order = null) {
        $status_text = $make_permanent ? __('permanent', 'iwp-wp-integration') : __('temporary', 'iwp-wp-integration');
        $reason_text = $this->get_reason_text($reason);
        
        // Count successful changes
        $changed_count = 0;
        $unchanged_count = 0;
        
        foreach ($results as $result) {
            if ($result['changed']) {
                $changed_count++;
            } else {
                $unchanged_count++;
            }
        }
        
        // Build subscription note
        $subscription_note = '';
        
        if ($changed_count > 0) {
            $subscription_note .= sprintf(
                __('InstaWP: %d site(s) marked as %s due to %s.', 'iwp-wp-integration'),
                $changed_count,
                $status_text,
                $reason_text
            );
        }
        
        if ($unchanged_count > 0) {
            if ($subscription_note) {
                $subscription_note .= ' ';
            }
            $subscription_note .= sprintf(
                __('%d site(s) were already %s.', 'iwp-wp-integration'),
                $unchanged_count,
                $status_text
            );
        }
        
        // Add deletion notes
        if (!empty($deleted_sites)) {
            if ($subscription_note) {
                $subscription_note .= ' ';
            }
            $subscription_note .= sprintf(
                __('⚠️ %d site(s) appear to have been deleted and could not be updated.', 'iwp-wp-integration'),
                count($deleted_sites)
            );
        }
        
        // Add note to subscription (admin only)
        if ($subscription_note) {
            $subscription->add_order_note($subscription_note, false, false);
        }
        
        // Add customer-visible notes to related orders
        $this->add_order_notes($results, $deleted_sites, $reason, $make_permanent);
    }

    /**
     * Add customer-visible notes to orders
     *
     * @param array $results
     * @param array $deleted_sites
     * @param string $reason
     * @param bool $make_permanent
     */
    private function add_order_notes($results, $deleted_sites, $reason, $make_permanent) {
        $orders_to_update = array();
        
        // Collect unique order IDs
        foreach ($results as $result) {
            if (!empty($result['order_id'])) {
                $orders_to_update[$result['order_id']] = true;
            }
        }
        
        foreach ($deleted_sites as $deleted) {
            if (!empty($deleted['order_id'])) {
                $orders_to_update[$deleted['order_id']] = true;
            }
        }
        
        // Add notes to each order
        foreach (array_keys($orders_to_update) as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }
            
            $order_results = array_filter($results, function($result) use ($order_id) {
                return $result['order_id'] == $order_id;
            });
            
            $order_deleted = array_filter($deleted_sites, function($deleted) use ($order_id) {
                return $deleted['order_id'] == $order_id;
            });
            
            $this->add_single_order_note($order, $order_results, $order_deleted, $reason, $make_permanent);
        }
    }

    /**
     * Add note to a single order
     *
     * @param WC_Order $order
     * @param array $results
     * @param array $deleted_sites
     * @param string $reason
     * @param bool $make_permanent
     */
    private function add_single_order_note($order, $results, $deleted_sites, $reason, $make_permanent) {
        $status_text = $make_permanent ? __('permanent', 'iwp-wp-integration') : __('temporary', 'iwp-wp-integration');
        $reason_text = $this->get_reason_text($reason);
        
        $note = '';
        
        if (!empty($results)) {
            $changed_results = array_filter($results, function($result) {
                return $result['changed'];
            });
            
            if (!empty($changed_results)) {
                $note .= sprintf(
                    __('🔄 Your InstaWP site(s) have been marked as %s due to subscription %s.', 'iwp-wp-integration'),
                    $status_text,
                    $reason_text
                );
            }
        }
        
        if (!empty($deleted_sites)) {
            if ($note) {
                $note .= ' ';
            }
            $note .= __('⚠️ Some of your InstaWP sites appear to have been deleted and could not be updated.', 'iwp-wp-integration');
        }
        
        if ($note) {
            // Add customer-visible note
            $order->add_order_note($note, true, false);
        }
    }

    /**
     * Get human-readable reason text
     *
     * @param string $reason
     * @return string
     */
    private function get_reason_text($reason) {
        $reason_map = array(
            'payment_failed' => __('payment failure', 'iwp-wp-integration'),
            'renewal_payment_failed' => __('renewal payment failure', 'iwp-wp-integration'),
            'payment_complete' => __('successful payment', 'iwp-wp-integration'),
            'renewal_payment_complete' => __('successful renewal payment', 'iwp-wp-integration'),
            'cancelled' => __('cancellation', 'iwp-wp-integration'),
            'expired' => __('expiration', 'iwp-wp-integration'),
            'on_hold' => __('being placed on hold', 'iwp-wp-integration'),
            'activated' => __('activation', 'iwp-wp-integration'),
        );
        
        return $reason_map[$reason] ?? $reason;
    }

    /**
     * Add meta box to subscription edit page
     */
    public function add_subscription_meta_box() {
        if (!$this->is_subscriptions_active()) {
            return;
        }
        
        add_meta_box(
            'iwp_subscription_sites',
            __('InstaWP Sites', 'iwp-wp-integration'),
            array($this, 'render_subscription_meta_box'),
            'shop_subscription',
            'normal',
            'default'
        );
    }

    /**
     * Render subscription meta box
     *
     * @param WP_Post $post
     */
    public function render_subscription_meta_box($post) {
        $subscription = wcs_get_subscription($post->ID);
        if (!$subscription) {
            return;
        }
        
        $sites = $this->find_subscription_sites($subscription);
        
        ?>
        <div class="iwp-subscription-sites">
            <?php if (empty($sites)): ?>
                <p><?php esc_html_e('No InstaWP sites found for this subscription.', 'iwp-wp-integration'); ?></p>
            <?php else: ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Site ID', 'iwp-wp-integration'); ?></th>
                            <th><?php esc_html_e('Order', 'iwp-wp-integration'); ?></th>
                            <th><?php esc_html_e('Source', 'iwp-wp-integration'); ?></th>
                            <th><?php esc_html_e('Actions', 'iwp-wp-integration'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sites as $site): ?>
                        <tr>
                            <td><?php echo esc_html($site['site_id']); ?></td>
                            <td>
                                <?php if (!empty($site['order_id'])): ?>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $site['order_id'] . '&action=edit')); ?>">
                                        #<?php echo esc_html($site['order_id']); ?>
                                    </a>
                                <?php else: ?>
                                    <?php esc_html_e('N/A', 'iwp-wp-integration'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($site['source']); ?></td>
                            <td>
                                <button type="button" class="button iwp-force-permanent" 
                                        data-site-id="<?php echo esc_attr($site['site_id']); ?>"
                                        data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>">
                                    <?php esc_html_e('Force Permanent', 'iwp-wp-integration'); ?>
                                </button>
                                <button type="button" class="button iwp-force-temporary"
                                        data-site-id="<?php echo esc_attr($site['site_id']); ?>"
                                        data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>">
                                    <?php esc_html_e('Force Temporary', 'iwp-wp-integration'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <script>
                jQuery(document).ready(function($) {
                    $('.iwp-force-permanent, .iwp-force-temporary').on('click', function(e) {
                        e.preventDefault();
                        
                        var $btn = $(this);
                        var siteId = $btn.data('site-id');
                        var subscriptionId = $btn.data('subscription-id');
                        var permanent = $btn.hasClass('iwp-force-permanent');
                        
                        $btn.prop('disabled', true).text('<?php esc_js_e('Processing...', 'iwp-wp-integration'); ?>');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'iwp_force_site_status',
                                site_id: siteId,
                                subscription_id: subscriptionId,
                                permanent: permanent ? 1 : 0,
                                nonce: '<?php echo wp_create_nonce('iwp_force_site_status'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('<?php esc_js_e('Site status updated successfully.', 'iwp-wp-integration'); ?>');
                                    location.reload();
                                } else {
                                    alert('<?php esc_js_e('Error: ', 'iwp-wp-integration'); ?>' + (response.data.message || '<?php esc_js_e('Unknown error', 'iwp-wp-integration'); ?>'));
                                }
                            },
                            error: function() {
                                alert('<?php esc_js_e('Network error occurred.', 'iwp-wp-integration'); ?>');
                            },
                            complete: function() {
                                $btn.prop('disabled', false).text(permanent ? '<?php esc_js_e('Force Permanent', 'iwp-wp-integration'); ?>' : '<?php esc_js_e('Force Temporary', 'iwp-wp-integration'); ?>');
                            }
                        });
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX handler for forcing site status
     */
    public function ajax_force_site_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iwp_force_site_status')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'iwp-wp-integration')));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'iwp-wp-integration')));
        }

        $site_id = sanitize_text_field($_POST['site_id'] ?? '');
        $subscription_id = intval($_POST['subscription_id'] ?? 0);
        $permanent = !empty($_POST['permanent']);

        if (empty($site_id) || empty($subscription_id)) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'iwp-wp-integration')));
        }

        // Update site status
        $result = IWP_Service::set_permanent($site_id, $permanent);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Add note to subscription
        $subscription = wcs_get_subscription($subscription_id);
        if ($subscription) {
            $status_text = $permanent ? __('permanent', 'iwp-wp-integration') : __('temporary', 'iwp-wp-integration');
            $note = sprintf(
                __('InstaWP: Site %s manually forced to %s status by admin.', 'iwp-wp-integration'),
                $site_id,
                $status_text
            );
            $subscription->add_order_note($note, false, false);
        }

        wp_send_json_success(array(
            'message' => __('Site status updated successfully.', 'iwp-wp-integration'),
            'result' => $result
        ));
    }
}