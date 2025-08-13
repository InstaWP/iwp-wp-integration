<?php
/**
 * Subscription Site Manager for IWP
 *
 * Advanced site management for subscription-based sites
 *
 * @package IWP
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_Subscription_Site_Manager class
 */
class IWP_Woo_Subscription_Site_Manager {

    /**
     * Get all sites associated with a customer
     *
     * @param int $customer_id
     * @return array
     */
    public static function get_customer_sites($customer_id) {
        $customer = new WC_Customer($customer_id);
        if (!$customer->get_id()) {
            return array();
        }

        $sites = array();
        
        // Get customer orders
        $orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'limit' => -1,
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold')
        ));
        
        foreach ($orders as $order) {
            $order_sites = get_post_meta($order->get_id(), '_iwp_sites_created', true);
            if (!empty($order_sites) && is_array($order_sites)) {
                foreach ($order_sites as $site) {
                    $sites[] = array_merge($site, array(
                        'order_id' => $order->get_id(),
                        'order_date' => $order->get_date_created()->date('Y-m-d H:i:s')
                    ));
                }
            }
        }
        
        return $sites;
    }

    /**
     * Get all subscriptions with InstaWP sites
     *
     * @param array $args Optional query arguments
     * @return array
     */
    public static function get_subscriptions_with_sites($args = array()) {
        if (!function_exists('wcs_get_subscriptions')) {
            return array();
        }

        $default_args = array(
            'subscriptions_per_page' => -1,
            'subscription_status' => array('active', 'on-hold', 'pending-cancel')
        );
        
        $args = wp_parse_args($args, $default_args);
        $subscriptions = wcs_get_subscriptions($args);
        
        $subscriptions_with_sites = array();
        
        foreach ($subscriptions as $subscription) {
            $sites = self::get_subscription_sites($subscription);
            if (!empty($sites)) {
                $subscriptions_with_sites[] = array(
                    'subscription' => $subscription,
                    'sites' => $sites,
                    'site_count' => count($sites)
                );
            }
        }
        
        return $subscriptions_with_sites;
    }

    /**
     * Get sites for a specific subscription
     *
     * @param WC_Subscription $subscription
     * @return array
     */
    public static function get_subscription_sites($subscription) {
        $sites = array();
        
        // Get sites from parent order
        $parent_order_id = $subscription->get_parent_id();
        if ($parent_order_id) {
            $parent_sites = get_post_meta($parent_order_id, '_iwp_sites_created', true);
            if (!empty($parent_sites) && is_array($parent_sites)) {
                foreach ($parent_sites as $site) {
                    $sites[] = array_merge($site, array(
                        'order_id' => $parent_order_id,
                        'source' => 'parent_order'
                    ));
                }
            }
        }
        
        // Get sites from renewal orders
        $related_orders = $subscription->get_related_orders('all', 'renewal');
        foreach ($related_orders as $order_id) {
            $order_sites = get_post_meta($order_id, '_iwp_sites_created', true);
            if (!empty($order_sites) && is_array($order_sites)) {
                foreach ($order_sites as $site) {
                    $sites[] = array_merge($site, array(
                        'order_id' => $order_id,
                        'source' => 'renewal_order'
                    ));
                }
            }
        }
        
        // Remove duplicates based on site_id
        $unique_sites = array();
        $seen_site_ids = array();
        
        foreach ($sites as $site) {
            $site_id = $site['site_id'] ?? $site['id'] ?? '';
            if (!empty($site_id) && !in_array($site_id, $seen_site_ids)) {
                $unique_sites[] = $site;
                $seen_site_ids[] = $site_id;
            }
        }
        
        return $unique_sites;
    }

    /**
     * Update site status for all subscription sites
     *
     * @param WC_Subscription $subscription
     * @param bool $permanent
     * @param string $reason
     * @return array Results array
     */
    public static function update_subscription_sites_status($subscription, $permanent, $reason = '') {
        $sites = self::get_subscription_sites($subscription);
        $results = array(
            'success' => array(),
            'failed' => array(),
            'deleted' => array()
        );
        
        foreach ($sites as $site) {
            $site_id = $site['site_id'] ?? $site['id'] ?? '';
            if (empty($site_id)) {
                continue;
            }
            
            try {
                $result = IWP_Service::set_permanent($site_id, $permanent);
                
                if (is_wp_error($result)) {
                    if (self::is_site_deleted_error($result)) {
                        $results['deleted'][] = array(
                            'site_id' => $site_id,
                            'error' => $result->get_error_message(),
                            'site' => $site
                        );
                    } else {
                        $results['failed'][] = array(
                            'site_id' => $site_id,
                            'error' => $result->get_error_message(),
                            'site' => $site
                        );
                    }
                } else {
                    $results['success'][] = array(
                        'site_id' => $site_id,
                        'changed' => $result['changed'],
                        'new_status' => $result['new_status'],
                        'site' => $site
                    );
                }
                
            } catch (Exception $e) {
                $results['failed'][] = array(
                    'site_id' => $site_id,
                    'error' => $e->getMessage(),
                    'site' => $site
                );
            }
        }
        
        return $results;
    }

    /**
     * Get subscription payment status summary
     *
     * @param WC_Subscription $subscription
     * @return array
     */
    public static function get_subscription_payment_summary($subscription) {
        $last_payment = $subscription->get_date('last_payment');
        $next_payment = $subscription->get_date('next_payment');
        $status = $subscription->get_status();
        
        // Calculate payment health score
        $health_score = 100;
        $health_issues = array();
        
        if (in_array($status, array('on-hold', 'pending-cancel'))) {
            $health_score -= 30;
            $health_issues[] = sprintf(__('Subscription is %s', 'iwp-wp-integration'), $status);
        }
        
        if ($status === 'cancelled' || $status === 'expired') {
            $health_score = 0;
            $health_issues[] = sprintf(__('Subscription is %s', 'iwp-wp-integration'), $status);
        }
        
        // Check for failed payments
        $failed_payments = self::get_failed_payment_count($subscription);
        if ($failed_payments > 0) {
            $health_score -= ($failed_payments * 10);
            $health_issues[] = sprintf(__('%d failed payment(s)', 'iwp-wp-integration'), $failed_payments);
        }
        
        return array(
            'status' => $status,
            'last_payment' => $last_payment,
            'next_payment' => $next_payment,
            'health_score' => max(0, $health_score),
            'health_issues' => $health_issues,
            'is_active' => in_array($status, array('active', 'pending-cancel')),
            'needs_attention' => $health_score < 70
        );
    }

    /**
     * Get failed payment count for subscription
     *
     * @param WC_Subscription $subscription
     * @return int
     */
    private static function get_failed_payment_count($subscription) {
        $failed_count = 0;
        
        // Get related orders and check for failed payments
        $related_orders = $subscription->get_related_orders('all', 'renewal');
        
        foreach ($related_orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && $order->has_status('failed')) {
                $failed_count++;
            }
        }
        
        return $failed_count;
    }

    /**
     * Check if error indicates site was deleted
     *
     * @param WP_Error $error
     * @return bool
     */
    private static function is_site_deleted_error($error) {
        $error_message = strtolower($error->get_error_message());
        
        $deletion_indicators = array(
            'site not found',
            '404',
            'not found',
            'deleted',
            'does not exist',
            'invalid site'
        );
        
        foreach ($deletion_indicators as $indicator) {
            if (strpos($error_message, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Bulk update site status for multiple subscriptions
     *
     * @param array $subscription_ids
     * @param bool $permanent
     * @param string $reason
     * @return array
     */
    public static function bulk_update_subscription_sites($subscription_ids, $permanent, $reason = 'bulk_update') {
        if (!function_exists('wcs_get_subscription')) {
            return array('error' => __('WooCommerce Subscriptions not available', 'iwp-wp-integration'));
        }

        $results = array(
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'deleted' => 0,
            'errors' => array()
        );
        
        foreach ($subscription_ids as $subscription_id) {
            $subscription = wcs_get_subscription($subscription_id);
            if (!$subscription) {
                $results['errors'][] = sprintf(__('Subscription %d not found', 'iwp-wp-integration'), $subscription_id);
                continue;
            }
            
            $results['processed']++;
            
            $update_result = self::update_subscription_sites_status($subscription, $permanent, $reason);
            
            $results['success'] += count($update_result['success']);
            $results['failed'] += count($update_result['failed']);
            $results['deleted'] += count($update_result['deleted']);
            
            // Add errors
            foreach ($update_result['failed'] as $failed) {
                $results['errors'][] = sprintf(
                    __('Subscription %d, Site %s: %s', 'iwp-wp-integration'),
                    $subscription_id,
                    $failed['site_id'],
                    $failed['error']
                );
            }
        }
        
        return $results;
    }

    /**
     * Get site status statistics
     *
     * @return array
     */
    public static function get_site_status_statistics() {
        $subscriptions_with_sites = self::get_subscriptions_with_sites();
        
        $stats = array(
            'total_subscriptions_with_sites' => count($subscriptions_with_sites),
            'total_sites' => 0,
            'active_subscriptions' => 0,
            'on_hold_subscriptions' => 0,
            'cancelled_subscriptions' => 0,
            'sites_needing_attention' => 0,
            'average_sites_per_subscription' => 0
        );
        
        foreach ($subscriptions_with_sites as $sub_data) {
            $subscription = $sub_data['subscription'];
            $stats['total_sites'] += $sub_data['site_count'];
            
            switch ($subscription->get_status()) {
                case 'active':
                    $stats['active_subscriptions']++;
                    break;
                case 'on-hold':
                    $stats['on_hold_subscriptions']++;
                    break;
                case 'cancelled':
                case 'expired':
                    $stats['cancelled_subscriptions']++;
                    break;
            }
            
            $payment_summary = self::get_subscription_payment_summary($subscription);
            if ($payment_summary['needs_attention']) {
                $stats['sites_needing_attention']++;
            }
        }
        
        if ($stats['total_subscriptions_with_sites'] > 0) {
            $stats['average_sites_per_subscription'] = round($stats['total_sites'] / $stats['total_subscriptions_with_sites'], 2);
        }
        
        return $stats;
    }

    /**
     * Get subscriptions that need attention
     *
     * @return array
     */
    public static function get_subscriptions_needing_attention() {
        $subscriptions_with_sites = self::get_subscriptions_with_sites();
        $needing_attention = array();
        
        foreach ($subscriptions_with_sites as $sub_data) {
            $subscription = $sub_data['subscription'];
            $payment_summary = self::get_subscription_payment_summary($subscription);
            
            if ($payment_summary['needs_attention']) {
                $needing_attention[] = array(
                    'subscription' => $subscription,
                    'sites' => $sub_data['sites'],
                    'payment_summary' => $payment_summary,
                    'site_count' => $sub_data['site_count']
                );
            }
        }
        
        // Sort by health score (worst first)
        usort($needing_attention, function($a, $b) {
            return $a['payment_summary']['health_score'] <=> $b['payment_summary']['health_score'];
        });
        
        return $needing_attention;
    }

    /**
     * Schedule regular health checks
     */
    public static function schedule_health_checks() {
        if (!wp_next_scheduled('iwp_subscription_health_check')) {
            wp_schedule_event(time(), 'daily', 'iwp_subscription_health_check');
        }
    }

    /**
     * Unschedule health checks
     */
    public static function unschedule_health_checks() {
        wp_clear_scheduled_hook('iwp_subscription_health_check');
    }

    /**
     * Run health check for all subscriptions
     */
    public static function run_health_check() {
        $needing_attention = self::get_subscriptions_needing_attention();
        
        if (empty($needing_attention)) {
            IWP_Logger::info('Subscription health check completed - all subscriptions healthy', 'subscriptions');
            return;
        }
        
        IWP_Logger::warning('Subscription health check found issues', 'subscriptions', array(
            'subscriptions_needing_attention' => count($needing_attention)
        ));
        
        // Process subscriptions that need attention
        foreach ($needing_attention as $attention_data) {
            $subscription = $attention_data['subscription'];
            $payment_summary = $attention_data['payment_summary'];
            
            // Log details about issues
            IWP_Logger::warning('Subscription needs attention', 'subscriptions', array(
                'subscription_id' => $subscription->get_id(),
                'status' => $subscription->get_status(),
                'health_score' => $payment_summary['health_score'],
                'issues' => $payment_summary['health_issues'],
                'site_count' => $attention_data['site_count']
            ));
            
            // Add admin note if health score is very low
            if ($payment_summary['health_score'] < 30) {
                $note = sprintf(
                    __('⚠️ InstaWP Health Check: This subscription has a low health score (%d%%). Issues: %s', 'iwp-wp-integration'),
                    $payment_summary['health_score'],
                    implode(', ', $payment_summary['health_issues'])
                );
                $subscription->add_order_note($note, false, false);
            }
        }
    }
}