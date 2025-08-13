<?php
/**
 * Centralized InstaWP Service Helper Class
 *
 * @package IWP
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Service class
 * 
 * Centralized service for InstaWP API operations with caching and error handling
 */
class IWP_Service {

    /**
     * API Client instance
     *
     * @var IWP_API_Client
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
     * @return IWP_API_Client|WP_Error
     */
    private static function get_api_client() {
        if (self::$api_client === null) {
            self::$api_client = new IWP_API_Client();
            
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
            return new WP_Error('no_api_key', __('API key not configured. Please configure it in plugin settings.', 'iwp-wp-integration'));
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
            self::$plugin_options = get_option('iwp_options', array());
        }
        
        return self::$plugin_options;
    }

    /**
     * Get snapshots formatted for dropdown
     *
     * @return array
     */
    public static function get_snapshots_for_dropdown() {
        $options = array('' => __('Select a snapshot...', 'iwp-wp-integration'));
        
        $snapshots = self::get_snapshots();
        
        if (is_wp_error($snapshots)) {
            IWP_Logger::error('Error fetching snapshots for dropdown', 'service', array('error' => $snapshots->get_error_message()));
            $options[''] = __('Error loading snapshots', 'iwp-wp-integration');
            return $options;
        }
        
        // Snapshots are now returned as a direct array from the API client
        if (is_array($snapshots) && !empty($snapshots)) {
            foreach ($snapshots as $snapshot) {
                if (is_array($snapshot) && isset($snapshot['slug']) && isset($snapshot['name'])) {
                    $options[$snapshot['slug']] = sanitize_text_field($snapshot['name']);
                }
            }
            IWP_Logger::info('Loaded snapshots for dropdown', 'service', array('count' => count($snapshots)));
        } else {
            IWP_Logger::warning('No snapshots data found in API response', 'service');
            $options[''] = __('No snapshots available', 'iwp-wp-integration');
        }
        
        return $options;
    }

    /**
     * Get plans formatted for dropdown
     *
     * @return array
     */
    public static function get_plans_for_dropdown() {
        $options = array('' => __('Select a plan...', 'iwp-wp-integration'));
        
        $plans = self::get_plans();
        
        if (is_wp_error($plans)) {
            IWP_Logger::error('Error fetching plans for dropdown', 'service', array('error' => $plans->get_error_message()));
            $options[''] = __('Error loading plans', 'iwp-wp-integration');
            return $options;
        }
        
        // Plans are now returned as a direct array from the API client
        $plan_count = 0;
        if (isset($plans) && is_array($plans)) {
            foreach ($plans as $plan) {
                if (is_array($plan) && isset($plan['id']) && isset($plan['display_name'])) {
                    $plan_label = sanitize_text_field($plan['display_name']);
                    if (isset($plan['short_description']) && !empty($plan['short_description'])) {
                        $plan_label .= ' - ' . sanitize_text_field($plan['short_description']);
                    }
                    $options[$plan['id']] = $plan_label;
                    $plan_count++;
                }
            }
            IWP_Logger::info('Loaded plans for dropdown', 'service', array('count' => $plan_count));
        } else {
            IWP_Logger::warning('No plans data found in API response', 'service');
            $options[''] = __('No plans available', 'iwp-wp-integration');
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
     * Set site permanent/temporary status
     * 
     * Gets current site status first, then toggles if needed
     *
     * @param int|string $site_id Site ID
     * @param bool $permanent True for permanent, false for temporary
     * @return array|WP_Error Response data or error
     */
    public static function set_permanent($site_id, $permanent = true) {
        $api_client = self::get_api_client();
        
        if (is_wp_error($api_client)) {
            return $api_client;
        }
        
        // First, get current site details to check is_reserved status
        $site_details = $api_client->get_site_details($site_id);
        
        if (is_wp_error($site_details)) {
            return new WP_Error(
                'site_details_failed',
                sprintf(__('Failed to get site details: %s', 'iwp-wp-integration'), $site_details->get_error_message()),
                array('site_id' => $site_id)
            );
        }
        
        // Check current reservation status
        $current_is_reserved = isset($site_details['is_reserved']) ? (bool) $site_details['is_reserved'] : false;
        $desired_is_reserved = (bool) $permanent;
        
        // If already in desired state, return current details
        if ($current_is_reserved === $desired_is_reserved) {
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Site is already %s', 'iwp-wp-integration'),
                    $permanent ? __('permanent', 'iwp-wp-integration') : __('temporary', 'iwp-wp-integration')
                ),
                'site_id' => $site_id,
                'is_reserved' => $current_is_reserved,
                'changed' => false,
                'site_details' => $site_details
            );
        }
        
        // Need to toggle the reservation status
        $toggle_result = $api_client->toggle_site_reservation($site_id);
        
        if (is_wp_error($toggle_result)) {
            return new WP_Error(
                'reservation_toggle_failed',
                sprintf(__('Failed to toggle site reservation: %s', 'iwp-wp-integration'), $toggle_result->get_error_message()),
                array('site_id' => $site_id, 'desired_permanent' => $permanent)
            );
        }
        
        // If changing from temporary to permanent, disable demo helper plugin
        if ($permanent && !$current_is_reserved) {
            IWP_Logger::info('Site changed from temporary to permanent, disabling demo helper', 'service', array('site_id' => $site_id));
            
            $demo_disable_result = $api_client->disable_demo_helper($site_id);
            
            if (is_wp_error($demo_disable_result)) {
                IWP_Logger::warning('Failed to disable demo helper after status change', 'service', array(
                    'site_id' => $site_id,
                    'error' => $demo_disable_result->get_error_message()
                ));
                // Don't fail the reservation change if demo helper disable fails
            } else {
                IWP_Logger::info('Demo helper disabled after status change', 'service', array(
                    'site_id' => $site_id,
                    'result' => $demo_disable_result['message']
                ));
            }
        }
        
        // Return success with new status
        return array(
            'success' => true,
            'message' => sprintf(
                __('Site changed to %s', 'iwp-wp-integration'),
                $permanent ? __('permanent', 'iwp-wp-integration') : __('temporary', 'iwp-wp-integration')
            ),
            'site_id' => $site_id,
            'is_reserved' => isset($toggle_result['is_reserved']) ? (bool) $toggle_result['is_reserved'] : $desired_is_reserved,
            'changed' => true,
            'previous_status' => $current_is_reserved ? 'permanent' : 'temporary',
            'new_status' => $permanent ? 'permanent' : 'temporary',
            'toggle_result' => $toggle_result
        );
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
        delete_transient('iwp_snapshots');
        delete_transient('iwp_plans');
        delete_transient('iwp_templates'); // Legacy compatibility
        
        // Reset cached options
        self::$plugin_options = null;
        self::$api_client = null;
        
        IWP_Logger::info('All caches cleared via service helper', 'service');
    }

    /**
     * Refresh snapshots cache
     *
     * @return array|WP_Error
     */
    public static function refresh_snapshots() {
        delete_transient('iwp_snapshots');
        return self::get_snapshots();
    }

    /**
     * Refresh plans cache
     *
     * @return array|WP_Error
     */
    public static function refresh_plans() {
        delete_transient('iwp_plans');
        return self::get_plans();
    }

    /**
     * Get plan name by plan ID
     *
     * @param string $plan_id Plan ID to lookup
     * @return string Plan display name or plan ID if not found
     */
    public static function get_plan_name_by_id($plan_id) {
        if (empty($plan_id)) {
            return '';
        }

        $plans = self::get_plans();
        
        if (is_wp_error($plans) || !is_array($plans)) {
            // Return original plan_id if we can't fetch plans
            return $plan_id;
        }

        // Plans are in numbered keys (0, 1, 2, etc.)
        foreach ($plans as $key => $plan) {
            if (is_numeric($key) && is_array($plan) && isset($plan['id']) && $plan['id'] === $plan_id) {
                $plan_name = sanitize_text_field($plan['display_name'] ?? $plan['name'] ?? '');
                return !empty($plan_name) ? $plan_name : $plan_id;
            }
        }

        // Return original plan_id if not found in plans list
        return $plan_id;
    }

    /**
     * Get teams with caching
     *
     * @return array|WP_Error
     */
    public static function get_teams() {
        $api_client = self::get_api_client();
        
        if (is_wp_error($api_client)) {
            return $api_client;
        }
        
        return $api_client->get_teams();
    }

    /**
     * Refresh teams cache
     *
     * @return array|WP_Error
     */
    public static function refresh_teams() {
        delete_transient('iwp_teams');
        return self::get_teams();
    }

    /**
     * Get teams formatted for dropdown
     *
     * @return array
     */
    public static function get_teams_for_dropdown() {
        $options = array('' => __('User\'s Logged In Team', 'iwp-wp-integration'));
        
        $teams = self::get_teams();
        
        if (is_wp_error($teams)) {
            IWP_Logger::error('Error fetching teams for dropdown', 'service', array('error' => $teams->get_error_message()));
            $options[''] = __('Error loading teams', 'iwp-wp-integration');
            return $options;
        }
        
        if (isset($teams['data']) && is_array($teams['data'])) {
            foreach ($teams['data'] as $team) {
                if (isset($team['id']) && isset($team['name'])) {
                    $options[$team['id']] = sanitize_text_field($team['name']);
                }
            }
            IWP_Logger::info('Loaded teams for dropdown', 'service', array('count' => count($teams['data'])));
        } else {
            IWP_Logger::warning('No teams data found in API response', 'service');
            $options[''] = __('No teams available', 'iwp-wp-integration');
        }
        
        return $options;
    }

    /**
     * Set selected team ID
     *
     * @param int|null $team_id
     * @return bool
     */
    public static function set_selected_team($team_id) {
        $options = get_option('iwp_options', array());
        $old_team_id = isset($options['selected_team_id']) ? $options['selected_team_id'] : null;
        $new_team_id = $team_id ? intval($team_id) : null;
        
        // Update the option
        $options['selected_team_id'] = $new_team_id;
        
        IWP_Logger::debug('Setting selected team', 'service', array(
            'old_team_id' => $old_team_id, 
            'new_team_id' => $new_team_id,
            'options_before' => $options
        ));
        
        // Bypass WordPress settings API sanitization by temporarily removing the filter
        // This prevents the sanitize_settings callback from interfering with AJAX updates
        $sanitize_callback = null;
        if (has_filter('sanitize_option_iwp_options')) {
            global $wp_filter;
            if (isset($wp_filter['sanitize_option_iwp_options'])) {
                $sanitize_callback = $wp_filter['sanitize_option_iwp_options'];
                unset($wp_filter['sanitize_option_iwp_options']);
            }
        }
        
        $result = update_option('iwp_options', $options);
        
        // Restore the sanitization callback
        if ($sanitize_callback !== null) {
            $wp_filter['sanitize_option_iwp_options'] = $sanitize_callback;
        }
        
        IWP_Logger::debug('Update option result', 'service', array(
            'result' => $result,
            'team_id' => $new_team_id,
            'options_serialized_length' => strlen(serialize($options))
        ));
        
        // WordPress update_option returns false if the value hasn't changed
        // So we need to check if the value was actually set correctly
        $updated_options = get_option('iwp_options', array());
        $actually_set = isset($updated_options['selected_team_id']) && $updated_options['selected_team_id'] === $new_team_id;
        
        IWP_Logger::debug('Verification check', 'service', array(
            'updated_options_has_team_id' => isset($updated_options['selected_team_id']),
            'updated_team_id_value' => isset($updated_options['selected_team_id']) ? $updated_options['selected_team_id'] : 'NOT_SET',
            'new_team_id' => $new_team_id,
            'types_match' => isset($updated_options['selected_team_id']) ? (gettype($updated_options['selected_team_id']) === gettype($new_team_id)) : false,
            'strict_comparison' => isset($updated_options['selected_team_id']) ? ($updated_options['selected_team_id'] === $new_team_id) : false
        ));
        
        // Consider it successful if the value is now correct, regardless of update_option return
        $success = $actually_set || ($result !== false);
        
        if ($success) {
            IWP_Logger::info('Selected team updated successfully', 'service', array(
                'team_id' => $new_team_id,
                'update_result' => $result,
                'verified' => $actually_set
            ));
            
            // Clear team-specific caches when team changes (only if team actually changed)
            if ($old_team_id !== $new_team_id) {
                self::clear_team_caches($new_team_id);
            }
        } else {
            IWP_Logger::error('Failed to set selected team', 'service', array(
                'team_id' => $new_team_id,
                'update_result' => $result,
                'verified' => $actually_set,
                'options_after' => $updated_options
            ));
        }
        
        return $success;
    }

    /**
     * Get selected team ID
     *
     * @return int|null
     */
    public static function get_selected_team() {
        $options = get_option('iwp_options', array());
        return isset($options['selected_team_id']) ? intval($options['selected_team_id']) : null;
    }

    /**
     * Clear team-specific caches
     *
     * @param int|null $team_id
     */
    private static function clear_team_caches($team_id = null) {
        if ($team_id) {
            delete_transient('iwp_snapshots_team_' . $team_id);
            delete_transient('iwp_plans_team_' . $team_id);
        } else {
            delete_transient('iwp_snapshots');
            delete_transient('iwp_plans');
        }
        
        IWP_Logger::debug('Team-specific caches cleared', 'service', array('team_id' => $team_id));
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
            $status['error'] = __('API key not configured', 'iwp-wp-integration');
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
        $cached_snapshots = get_transient('iwp_snapshots');
        if ($cached_snapshots !== false) {
            $status['snapshots'] = true;
            if (isset($cached_snapshots['data']) && is_array($cached_snapshots['data'])) {
                $status['snapshots_count'] = count($cached_snapshots['data']);
            }
            
            // Get expiration info
            $timeout = get_option('_transient_timeout_iwp_snapshots');
            if ($timeout) {
                $status['snapshots_expires'] = $timeout;
                $status['snapshots_age'] = time() - ($timeout - (15 * MINUTE_IN_SECONDS));
            }
        }
        
        // Check plans cache
        $cached_plans = get_transient('iwp_plans');
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
            $timeout = get_option('_transient_timeout_iwp_plans');
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
        
        $cached_snapshots = get_transient('iwp_snapshots');
        if ($cached_snapshots !== false) {
            $usage += strlen(serialize($cached_snapshots));
        }
        
        $cached_plans = get_transient('iwp_plans');
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