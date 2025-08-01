<?php
/**
 * Centralized InstaWP Service Helper Class
 *
 * @package IWP_Woo_V2
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_V2_Service class
 * 
 * Centralized service for InstaWP API operations with caching and error handling
 */
class IWP_Woo_V2_Service {

    /**
     * API Client instance
     *
     * @var IWP_Woo_V2_API_Client
     */
    private static $api_client = null;

    /**
     * Cached options
     *
     * @var array
     */
    private static $plugin_options = null;

    /**
     * Get API client instance
     *
     * @return IWP_Woo_V2_API_Client|WP_Error
     */
    private static function get_api_client() {
        if (self::$api_client === null) {
            self::$api_client = new IWP_Woo_V2_API_Client();
            
            $api_key = self::get_api_key();
            if (is_wp_error($api_key)) {
                return $api_key;
            }
            
            self::$api_client->set_api_key($api_key);
        }
        
        return self::$api_client;
    }

    /**
     * Get API key from settings
     *
     * @return string|WP_Error
     */
    private static function get_api_key() {
        $options = self::get_plugin_options();
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API key not configured. Please configure it in plugin settings.', 'instawp-integration'));
        }
        
        return $api_key;
    }

    /**
     * Get cached plugin options
     *
     * @return array
     */
    private static function get_plugin_options() {
        if (self::$plugin_options === null) {
            self::$plugin_options = get_option('iwp_woo_v2_options', array());
        }
        
        return self::$plugin_options;
    }

    /**
     * Get snapshots formatted for dropdown
     *
     * @return array
     */
    public static function get_snapshots_for_dropdown() {
        $options = array('' => __('Select a snapshot...', 'instawp-integration'));
        
        $snapshots = self::get_snapshots();
        
        if (is_wp_error($snapshots)) {
            IWP_Woo_V2_Logger::error('Error fetching snapshots for dropdown', 'service', array('error' => $snapshots->get_error_message()));
            $options[''] = __('Error loading snapshots', 'instawp-integration');
            return $options;
        }
        
        if (isset($snapshots['data']) && is_array($snapshots['data'])) {
            foreach ($snapshots['data'] as $snapshot) {
                if (isset($snapshot['slug']) && isset($snapshot['name'])) {
                    $options[$snapshot['slug']] = sanitize_text_field($snapshot['name']);
                }
            }
            IWP_Woo_V2_Logger::info('Loaded snapshots for dropdown', 'service', array('count' => count($snapshots['data'])));
        } else {
            IWP_Woo_V2_Logger::warning('No snapshots data found in API response', 'service');
            $options[''] = __('No snapshots available', 'instawp-integration');
        }
        
        return $options;
    }

    /**
     * Get plans formatted for dropdown
     *
     * @return array
     */
    public static function get_plans_for_dropdown() {
        $options = array('' => __('Select a plan...', 'instawp-integration'));
        
        $plans = self::get_plans();
        
        if (is_wp_error($plans)) {
            IWP_Woo_V2_Logger::error('Error fetching plans for dropdown', 'service', array('error' => $plans->get_error_message()));
            $options[''] = __('Error loading plans', 'instawp-integration');
            return $options;
        }
        
        // Plans are in numbered keys (0, 1, 2, etc.) not in 'data' array
        $plan_count = 0;
        if (isset($plans) && is_array($plans)) {
            foreach ($plans as $key => $plan) {
                if (is_numeric($key) && is_array($plan) && isset($plan['id']) && isset($plan['display_name'])) {
                    $plan_label = sanitize_text_field($plan['display_name']);
                    if (isset($plan['short_description']) && !empty($plan['short_description'])) {
                        $plan_label .= ' - ' . sanitize_text_field($plan['short_description']);
                    }
                    $options[$plan['id']] = $plan_label;
                    $plan_count++;
                }
            }
            IWP_Woo_V2_Logger::info('Loaded plans for dropdown', 'service', array('count' => $plan_count));
        } else {
            IWP_Woo_V2_Logger::warning('No plans data found in API response', 'service');
            $options[''] = __('No plans available', 'instawp-integration');
        }
        
        return $options;
    }

    /**
     * Get all snapshots with caching
     *
     * @return array|WP_Error
     */
    public static function get_snapshots() {
        $api_client = self::get_api_client();
        
        if (is_wp_error($api_client)) {
            return $api_client;
        }
        
        return $api_client->get_snapshots();
    }

    /**
     * Get all plans with caching
     *
     * @return array|WP_Error
     */
    public static function get_plans() {
        $api_client = self::get_api_client();
        
        if (is_wp_error($api_client)) {
            return $api_client;
        }
        
        return $api_client->get_plans();
    }

    /**
     * Get specific snapshot by slug
     *
     * @param string $slug
     * @return array|WP_Error
     */
    public static function get_snapshot($slug) {
        $api_client = self::get_api_client();
        
        if (is_wp_error($api_client)) {
            return $api_client;
        }
        
        return $api_client->get_snapshot($slug);
    }

    /**
     * Create site from snapshot
     *
     * @param string $snapshot_slug
     * @param array $site_data
     * @param string $plan_id
     * @param int $expiry_hours
     * @param bool $is_shared
     * @return array|WP_Error
     */
    public static function create_site_from_snapshot($snapshot_slug, $site_data, $plan_id = '', $expiry_hours = null, $is_shared = false) {
        $api_client = self::get_api_client();
        
        if (is_wp_error($api_client)) {
            return $api_client;
        }
        
        return $api_client->create_site_from_snapshot($snapshot_slug, $site_data, $plan_id, $expiry_hours, $is_shared);
    }

    /**
     * Get task status
     *
     * @param string $task_id
     * @return array|WP_Error
     */
    public static function get_task_status($task_id) {
        $api_client = self::get_api_client();
        
        if (is_wp_error($api_client)) {
            return $api_client;
        }
        
        return $api_client->get_task_status($task_id);
    }

    /**
     * Upgrade site plan
     *
     * @param int $site_id
     * @param string $plan_id
     * @return array|WP_Error
     */
    public static function upgrade_site_plan($site_id, $plan_id) {
        $api_client = self::get_api_client();
        
        if (is_wp_error($api_client)) {
            return $api_client;
        }
        
        return $api_client->upgrade_site_plan($site_id, $plan_id);
    }

    /**
     * Add domain to site
     *
     * @param int $site_id
     * @param string $domain_name
     * @param string $domain_type
     * @return array|WP_Error
     */
    public static function add_domain_to_site($site_id, $domain_name, $domain_type = 'primary') {
        $api_client = self::get_api_client();
        
        if (is_wp_error($api_client)) {
            return $api_client;
        }
        
        return $api_client->add_domain_to_site($site_id, $domain_name, $domain_type);
    }

    /**
     * Test API connection
     *
     * @return bool|WP_Error
     */
    public static function test_connection() {
        $api_client = self::get_api_client();
        
        if (is_wp_error($api_client)) {
            return $api_client;
        }
        
        return $api_client->test_connection();
    }

    /**
     * Clear all caches
     */
    public static function clear_caches() {
        delete_transient('iwp_woo_v2_snapshots');
        delete_transient('iwp_woo_v2_plans');
        delete_transient('iwp_woo_v2_templates'); // Legacy compatibility
        
        // Reset cached options
        self::$plugin_options = null;
        self::$api_client = null;
        
        IWP_Woo_V2_Logger::info('All caches cleared via service helper', 'service');
    }

    /**
     * Refresh snapshots cache
     *
     * @return array|WP_Error
     */
    public static function refresh_snapshots() {
        delete_transient('iwp_woo_v2_snapshots');
        return self::get_snapshots();
    }

    /**
     * Refresh plans cache
     *
     * @return array|WP_Error
     */
    public static function refresh_plans() {
        delete_transient('iwp_woo_v2_plans');
        return self::get_plans();
    }

    /**
     * Check if API is configured
     *
     * @return bool
     */
    public static function is_api_configured() {
        $api_key = self::get_api_key();
        return !is_wp_error($api_key);
    }

    /**
     * Get API status information
     *
     * @return array
     */
    public static function get_api_status() {
        $status = array(
            'configured' => false,
            'connected' => false,
            'error' => null
        );
        
        if (!self::is_api_configured()) {
            $status['error'] = __('API key not configured', 'instawp-integration');
            return $status;
        }
        
        $status['configured'] = true;
        
        $connection_test = self::test_connection();
        if (is_wp_error($connection_test)) {
            $status['error'] = $connection_test->get_error_message();
        } else {
            $status['connected'] = true;
        }
        
        return $status;
    }

    /**
     * Get cache status information
     *
     * @return array
     */
    public static function get_cache_status() {
        $status = array(
            'snapshots' => false,
            'plans' => false,
            'snapshots_count' => 0,
            'plans_count' => 0,
            'snapshots_expires' => null,
            'plans_expires' => null,
            'snapshots_age' => null,
            'plans_age' => null
        );
        
        // Check snapshots cache
        $cached_snapshots = get_transient('iwp_woo_v2_snapshots');
        if ($cached_snapshots !== false) {
            $status['snapshots'] = true;
            if (isset($cached_snapshots['data']) && is_array($cached_snapshots['data'])) {
                $status['snapshots_count'] = count($cached_snapshots['data']);
            }
            
            // Get expiration info
            $timeout = get_option('_transient_timeout_iwp_woo_v2_snapshots');
            if ($timeout) {
                $status['snapshots_expires'] = $timeout;
                $status['snapshots_age'] = time() - ($timeout - (15 * MINUTE_IN_SECONDS));
            }
        }
        
        // Check plans cache
        $cached_plans = get_transient('iwp_woo_v2_plans');
        if ($cached_plans !== false) {
            $status['plans'] = true;
            if (is_array($cached_plans)) {
                foreach ($cached_plans as $key => $value) {
                    if (is_numeric($key) && is_array($value) && isset($value['id'])) {
                        $status['plans_count']++;
                    }
                }
            }
            
            // Get expiration info
            $timeout = get_option('_transient_timeout_iwp_woo_v2_plans');
            if ($timeout) {
                $status['plans_expires'] = $timeout;
                $status['plans_age'] = time() - ($timeout - HOUR_IN_SECONDS);
            }
        }
        
        return $status;
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public static function get_cache_stats() {
        $status = self::get_cache_status();
        
        $stats = array(
            'total_cached_items' => $status['snapshots_count'] + $status['plans_count'],
            'cache_hit_ratio' => self::get_cache_hit_ratio(),
            'memory_usage' => self::get_cache_memory_usage(),
            'oldest_cache' => null,
            'newest_cache' => null
        );
        
        // Determine oldest and newest cache
        $ages = array();
        if ($status['snapshots_age']) {
            $ages['snapshots'] = $status['snapshots_age'];
        }
        if ($status['plans_age']) {
            $ages['plans'] = $status['plans_age'];
        }
        
        if (!empty($ages)) {
            $stats['oldest_cache'] = max($ages);
            $stats['newest_cache'] = min($ages);
        }
        
        return $stats;
    }

    /**
     * Get cache hit ratio (estimated)
     *
     * @return float
     */
    private static function get_cache_hit_ratio() {
        // This would require tracking hits/misses over time
        // For now, return estimated based on cache status
        $status = self::get_cache_status();
        $active_caches = ($status['snapshots'] ? 1 : 0) + ($status['plans'] ? 1 : 0);
        return $active_caches / 2; // 2 total cache types
    }

    /**
     * Get estimated cache memory usage
     *
     * @return int Bytes
     */
    private static function get_cache_memory_usage() {
        $usage = 0;
        
        $cached_snapshots = get_transient('iwp_woo_v2_snapshots');
        if ($cached_snapshots !== false) {
            $usage += strlen(serialize($cached_snapshots));
        }
        
        $cached_plans = get_transient('iwp_woo_v2_plans');
        if ($cached_plans !== false) {
            $usage += strlen(serialize($cached_plans));
        }
        
        return $usage;
    }

    /**
     * Warm up caches (pre-populate)
     *
     * @return array Results
     */
    public static function warm_up_caches() {
        $results = array(
            'snapshots' => false,
            'plans' => false,
            'errors' => array()
        );
        
        // Warm up snapshots cache
        $snapshots = self::get_snapshots();
        if (!is_wp_error($snapshots)) {
            $results['snapshots'] = true;
        } else {
            $results['errors'][] = 'Snapshots: ' . $snapshots->get_error_message();
        }
        
        // Warm up plans cache
        $plans = self::get_plans();
        if (!is_wp_error($plans)) {
            $results['plans'] = true;
        } else {
            $results['errors'][] = 'Plans: ' . $plans->get_error_message();
        }
        
        return $results;
    }
}