<?php
/**
 * InstaWP API Client for InstaWP Integration
 *
 * @package IWP
 * @since 0.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_API_Client class
 */
class IWP_API_Client {

    /**
     * API base URL
     *
     * @var string
     */
    private $api_url = 'https://app.instawp.io/api/v2/';

    /**
     * V1 API base URL (for legacy endpoints like domain mapping)
     *
     * @var string
     */
    private $api_url_v1 = 'https://app.instawp.io/api/v1/';

    /**
     * API key
     *
     * @var string
     */
    private $api_key;

    /**
     * Selected team ID
     *
     * @var int|null
     */
    private $team_id;

    /**
     * Constructor
     */
    public function __construct() {
        $options = get_option('iwp_options', array());
        $this->api_key = isset($options['api_key']) ? $options['api_key'] : '';
        $this->team_id = isset($options['selected_team_id']) ? intval($options['selected_team_id']) : null;
    }

    /**
     * Set API key
     *
     * @param string $api_key
     */
    public function set_api_key($api_key) {
        $this->api_key = sanitize_text_field($api_key);
    }

    /**
     * Get API key
     *
     * @return string
     */
    public function get_api_key() {
        return $this->api_key;
    }

    /**
     * Set team ID
     *
     * @param int|null $team_id
     */
    public function set_team_id($team_id) {
        $this->team_id = $team_id ? intval($team_id) : null;
    }

    /**
     * Get team ID
     *
     * @return int|null
     */
    public function get_team_id() {
        return $this->team_id;
    }

    /**
     * Make API request
     *
     * @param string $endpoint
     * @param array $args
     * @return array|WP_Error
     */
    private function make_request($endpoint, $args = array()) {
        if (empty($this->api_key)) {
            IWP_Logger::error('API key is empty', 'api-client');
            return new WP_Error('no_api_key', __('API key is required', 'iwp-woo-v2'));
        }

        $url = rtrim($this->api_url, '/') . '/' . ltrim($endpoint, '/');
        
        // Add team_id parameter if set
        if ($this->team_id && strpos($endpoint, 'teams') === false) {
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $url .= $separator . 'team_id=' . $this->team_id;
        }
        
        $default_args = array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
        );

        $args = wp_parse_args($args, $default_args);

        // Log the request details (without sensitive data)
        $log_args = $args;
        if (isset($log_args['headers']['Authorization'])) {
            $log_args['headers']['Authorization'] = 'Bearer ' . $this->api_key;
        }
        IWP_Logger::debug('Making API request', 'api-client', array('url' => $url));
        IWP_Logger::debug('Request args', 'api-client', $log_args);

        $response = wp_remote_request($url, $args);

        // Log the response in errorlog
        IWP_Logger::debug('Raw API response received', 'api-client');

        if (is_wp_error($response)) {
            IWP_Logger::error('API request failed with WP_Error', 'api-client', array('error' => $response->get_error_message()));
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        // Log the response details
        IWP_Logger::debug('API response code', 'api-client', array('code' => $response_code));
        error_log('IWP WooCommerce V2: API response headers: ' . wp_json_encode($response_headers->getAll()));
        IWP_Logger::debug('API response body received', 'api-client');

        if ($response_code < 200 || $response_code >= 300) {
            $error_message = sprintf(
                __('API request failed with status code %d', 'iwp-woo-v2'),
                $response_code
            );
            
            // Try to get error message from response body
            $body_data = json_decode($response_body, true);
            if (is_array($body_data) && isset($body_data['message'])) {
                $error_message .= ': ' . sanitize_text_field($body_data['message']);
            }
            
            IWP_Logger::error('API request failed', 'api-client', array('error' => $error_message));
            return new WP_Error('api_request_failed', $error_message);
        }

        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            IWP_Logger::error('JSON decode error', 'api-client', array('error' => json_last_error_msg()));
            return new WP_Error('json_decode_error', __('Invalid JSON response', 'iwp-woo-v2'));
        }

        error_log('IWP WooCommerce V2: API request successful, parsed data: ' . wp_json_encode($data));
        return $data;
    }

    /**
     * Get snapshots
     *
     * @return array|WP_Error
     */
    public function get_snapshots() {
        // Use team-specific cache key if team is selected
        $cache_key = $this->team_id ? 'iwp_snapshots_team_' . $this->team_id : 'iwp_snapshots';
        
        IWP_Logger::debug('get_snapshots() called', 'api-client', array('team_id' => $this->team_id, 'cache_key' => $cache_key));
        
        $cached_snapshots = get_transient($cache_key);
        
        if (false !== $cached_snapshots) {
            IWP_Logger::debug('Using cached snapshots data', 'api-client', array('cache_key' => $cache_key));
            return $cached_snapshots;
        }

        IWP_Logger::debug('No cached snapshots found, making API request', 'api-client', array('cache_key' => $cache_key));
        $response = $this->make_request('snapshots', array(
            'method' => 'GET',
        ));

        if (is_wp_error($response)) {
            IWP_Logger::error('get_snapshots() failed', 'api-client', array('error' => $response->get_error_message()));
            return $response;
        }

        // Handle new API response format with status, message, and data fields
        if (isset($response['data']) && is_array($response['data'])) {
            $snapshots_data = $response['data'];
            IWP_Logger::debug('Using snapshots from data field', 'api-client', array('status' => $response['status'] ?? 'unknown', 'message' => $response['message'] ?? 'no message'));
        } else {
            // Fallback for legacy response format
            $snapshots_data = $response;
            IWP_Logger::debug('Using legacy snapshots response format', 'api-client');
        }

        // Cache for configurable duration (default 15 minutes)
        $cache_duration = apply_filters('iwp_snapshots_cache_duration', 15 * MINUTE_IN_SECONDS);
        set_transient($cache_key, $snapshots_data, $cache_duration);
        IWP_Logger::info('Snapshots cached', 'api-client', array('cache_key' => $cache_key, 'duration_minutes' => $cache_duration / 60));

        return $snapshots_data;
    }

    /**
     * Clear snapshots cache
     */
    public function clear_snapshots_cache() {
        // Clear team-specific cache if team is selected
        if ($this->team_id) {
            $cache_key = 'iwp_snapshots_team_' . $this->team_id;
            delete_transient($cache_key);
            IWP_Logger::debug('Team-specific snapshots cache cleared', 'api-client', array('cache_key' => $cache_key));
        } else {
            delete_transient('iwp_snapshots');
            IWP_Logger::debug('Default snapshots cache cleared', 'api-client');
        }
    }

    /**
     * Test API connection
     *
     * @return bool|WP_Error
     */
    public function test_connection() {
        error_log('IWP WooCommerce V2: test_connection() called');
        
        $response = $this->make_request('snapshots', array(
            'method' => 'GET',
        ));

        if (is_wp_error($response)) {
            error_log('IWP WooCommerce V2: Connection test failed: ' . $response->get_error_message());
            return $response;
        }

        error_log('IWP WooCommerce V2: Connection test successful');
        return true;
    }

    /**
     * Get all available plans
     *
     * @return array|WP_Error
     */
    public function get_plans() {
        // Use team-specific cache key if team is selected
        $cache_key = $this->team_id ? 'iwp_plans_team_' . $this->team_id : 'iwp_plans';
        $cached_plans = get_transient($cache_key);
        
        if ($cached_plans !== false) {
            IWP_Logger::debug('Using cached plans data', 'api-client', array('cache_key' => $cache_key));
            return $cached_plans;
        }

        IWP_Logger::debug('Fetching plans from API', 'api-client', array('cache_key' => $cache_key));
        
        $response = $this->make_request('get-plans?product_type=sites', array(
            'method' => 'GET',
        ));

        if (is_wp_error($response)) {
            IWP_Logger::error('Failed to fetch plans', 'api-client', array('error' => $response->get_error_message()));
            return $response;
        }

        // Handle new API response format with status, message, and data fields
        if (isset($response['data']) && is_array($response['data'])) {
            $plans_data = $response['data'];
            IWP_Logger::debug('Using plans from data field', 'api-client', array('status' => $response['status'] ?? 'unknown', 'message' => $response['message'] ?? 'no message'));
        } else {
            // Fallback for legacy response format
            $plans_data = $response;
            IWP_Logger::debug('Using legacy plans response format', 'api-client');
        }

        // Cache for configurable duration (default 1 hour)
        $cache_duration = apply_filters('iwp_plans_cache_duration', HOUR_IN_SECONDS);
        set_transient($cache_key, $plans_data, $cache_duration);
        
        // Count plans from the data
        $plan_count = 0;
        if (isset($plans_data) && is_array($plans_data)) {
            $plan_count = count($plans_data);
        }
        
        IWP_Logger::info('Successfully fetched plans', 'api-client', array('count' => $plan_count, 'cache_key' => $cache_key));
        return $plans_data;
    }

    /**
     * Get a specific plan by ID
     *
     * @param string $plan_id
     * @return array|WP_Error
     */
    public function get_plan($plan_id) {
        if (empty($plan_id)) {
            return new WP_Error('invalid_plan_id', __('Plan ID is required', 'iwp-woo-v2'));
        }
        
        $response = $this->make_request('plans/' . sanitize_text_field($plan_id), array(
            'method' => 'GET',
        ));
        
        return $response;
    }

    /**
     * Get teams
     *
     * @return array|WP_Error
     */
    public function get_teams() {
        $cached_teams = get_transient('iwp_teams');
        
        if (false !== $cached_teams) {
            IWP_Logger::debug('Using cached teams data', 'api-client');
            return $cached_teams;
        }

        IWP_Logger::debug('Fetching teams from API', 'api-client');
        
        $response = $this->make_request('teams', array(
            'method' => 'GET',
        ));

        if (is_wp_error($response)) {
            IWP_Logger::error('Failed to fetch teams', 'api-client', array('error' => $response->get_error_message()));
            return $response;
        }

        // Cache for configurable duration (default 1 hour)
        $cache_duration = apply_filters('iwp_teams_cache_duration', HOUR_IN_SECONDS);
        set_transient('iwp_teams', $response, $cache_duration);
        
        $team_count = 0;
        if (isset($response['data']) && is_array($response['data'])) {
            $team_count = count($response['data']);
        }
        
        IWP_Logger::info('Successfully fetched teams', 'api-client', array('count' => $team_count));
        return $response;
    }

    /**
     * Get snapshot details
     *
     * @param string $snapshot_slug
     * @return array|WP_Error
     */
    public function get_snapshot($snapshot_slug) {
        if (empty($snapshot_slug)) {
            return new WP_Error('invalid_snapshot_slug', __('Snapshot slug is required', 'iwp-woo-v2'));
        }

        $response = $this->make_request('snapshots/' . sanitize_text_field($snapshot_slug), array(
            'method' => 'GET',
        ));

        return $response;
    }

    /**
     * Create site from snapshot
     *
     * @param string $snapshot_slug
     * @param array $site_data
     * @param string $plan_id Optional plan ID for site creation
     * @return array|WP_Error
     */
    public function create_site_from_snapshot($snapshot_slug, $site_data = array(), $plan_id = '') {
        if (empty($snapshot_slug)) {
            return new WP_Error('invalid_snapshot_slug', __('Snapshot slug is required', 'iwp-woo-v2'));
        }

        $default_data = array(
            'template_slug' => sanitize_text_field($snapshot_slug),
        );
        
        // Add plan_id if provided
        if (!empty($plan_id)) {
            $default_data['plan_id'] = sanitize_text_field($plan_id);
        }

        // Merge with provided site data
        $site_data = wp_parse_args($site_data, $default_data);

        // Handle expiry_hours and is_reserved parameters
        if (isset($site_data['expiry_hours']) && !empty($site_data['expiry_hours'])) {
            $site_data['expiry_hours'] = intval($site_data['expiry_hours']);
            // If expiry_hours is set, set is_reserved to false (unless explicitly set)
            if (!isset($site_data['is_reserved'])) {
                $site_data['is_reserved'] = false;
            }
        } else {
            // If no expiry_hours, default to reserved (unless explicitly set)
            if (!isset($site_data['is_reserved'])) {
                $site_data['is_reserved'] = true;
            }
        }

        // Ensure is_reserved is boolean
        if (isset($site_data['is_reserved'])) {
            $site_data['is_reserved'] = (bool) $site_data['is_reserved'];
        }

        // Handle is_shared parameter for sandbox sites
        if (isset($site_data['is_shared'])) {
            $site_data['is_shared'] = (bool) $site_data['is_shared'];
        }

        $response = $this->make_request('sites/template', array(
            'method' => 'POST',
            'body' => wp_json_encode($site_data),
        ));

        return $response;
    }

    /**
     * Check the status of a site creation task
     *
     * @param string $task_id
     * @return array|WP_Error
     */
    public function get_task_status($task_id) {
        if (empty($task_id)) {
            return new WP_Error('invalid_task_id', __('Task ID is required', 'iwp-woo-v2'));
        }

        $response = $this->make_request('tasks/' . sanitize_text_field($task_id) . '/status', array(
            'method' => 'GET',
        ));

        return $response;
    }

    /**
     * Upgrade site plan
     *
     * @param int $site_id
     * @param string $plan_id
     * @return array|WP_Error
     */
    public function upgrade_site_plan($site_id, $plan_id) {
        if (empty($site_id) || !is_numeric($site_id)) {
            return new WP_Error('invalid_site_id', __('Valid site ID is required', 'iwp-woo-v2'));
        }

        if (empty($plan_id)) {
            return new WP_Error('invalid_plan_id', __('Plan ID is required', 'iwp-woo-v2'));
        }

        $upgrade_data = array(
            'plan_id' => sanitize_text_field($plan_id)
        );

        error_log('IWP WooCommerce V2: Upgrading site ' . $site_id . ' to plan ' . $plan_id);

        $response = $this->make_request('sites/' . intval($site_id) . '/upgrade-plan', array(
            'method' => 'POST',
            'body' => wp_json_encode($upgrade_data),
        ));

        if (is_wp_error($response)) {
            error_log('IWP WooCommerce V2: Site plan upgrade failed: ' . $response->get_error_message());
            return $response;
        }

        error_log('IWP WooCommerce V2: Site plan upgrade successful, fetching updated site details');

        // Fetch complete site details after successful upgrade
        $site_details = $this->get_site_details($site_id);

        if (is_wp_error($site_details)) {
            error_log('IWP WooCommerce V2: Failed to fetch site details after upgrade: ' . $site_details->get_error_message());
            // Return upgrade response even if site details fetch fails
            $response['site_details_error'] = $site_details->get_error_message();
        } else {
            // Merge site details into the upgrade response
            $response['site_details'] = $site_details;
            error_log('IWP WooCommerce V2: Successfully fetched site details after upgrade');
        }

        return $response;
    }

    /**
     * Get site details by site ID
     *
     * @param int $site_id
     * @return array|WP_Error
     */
    public function get_site_details($site_id) {
        if (empty($site_id) || !is_numeric($site_id)) {
            return new WP_Error('invalid_site_id', __('Valid site ID is required', 'iwp-woo-v2'));
        }

        error_log('IWP WooCommerce V2: Fetching details for site ID: ' . $site_id);

        $response = $this->make_request('sites/' . intval($site_id), array(
            'method' => 'GET',
        ));

        if (is_wp_error($response)) {
            error_log('IWP WooCommerce V2: Failed to fetch site details: ' . $response->get_error_message());
        } else {
            error_log('IWP WooCommerce V2: Successfully fetched site details for site ID: ' . $site_id);
        }

        return $response;
    }

    /**
     * Add custom domain to a site
     *
     * @param int $site_id
     * @param string $domain_name
     * @param string $type 'primary' or 'alias'
     * @return array|WP_Error
     */
    public function add_domain_to_site($site_id, $domain_name, $type = 'primary') {
        if (empty($site_id) || !is_numeric($site_id)) {
            return new WP_Error('invalid_site_id', __('Valid site ID is required', 'iwp-woo-v2'));
        }

        if (empty($domain_name)) {
            return new WP_Error('invalid_domain', __('Domain name is required', 'iwp-woo-v2'));
        }

        if (!in_array($type, ['primary', 'alias'])) {
            $type = 'primary';
        }

        $domain_data = array(
            'name' => sanitize_text_field($domain_name),
            'type' => sanitize_text_field($type),
            'www' => false,
            'route_www' => false
        );

        error_log('IWP WooCommerce V2: Adding domain ' . $domain_name . ' to site ' . $site_id . ' as ' . $type);

        // Use v1 API for domain mapping
        $endpoint = 'site/add-domain/' . intval($site_id);
        
        // Temporarily change API URL for this request
        $original_url = $this->api_url;
        $this->api_url = $this->api_url_v1;
        
        $response = $this->make_request($endpoint, array(
            'method' => 'POST',
            'body' => wp_json_encode($domain_data),
        ));

        // Restore original API URL
        $this->api_url = $original_url;

        if (is_wp_error($response)) {
            error_log('IWP WooCommerce V2: Domain mapping failed: ' . $response->get_error_message());
            return $response;
        }

        error_log('IWP WooCommerce V2: Domain mapping successful');
        return $response;
    }

    /**
     * Disable demo helper plugin on a site
     *
     * @param int $site_id
     * @param string $site_url Optional site URL (if not provided, will try to get from site details)
     * @return array|WP_Error
     */
    public function disable_demo_helper($site_id, $site_url = '') {
        if (empty($site_id) || !is_numeric($site_id)) {
            return new WP_Error('invalid_site_id', __('Valid site ID is required', 'iwp-wp-integration'));
        }

        // If no site URL provided, try to get it from site details
        if (empty($site_url)) {
            $site_details = $this->get_site_details($site_id);
            if (is_wp_error($site_details)) {
                IWP_Logger::warning('Could not get site details for demo helper disable', 'api-client', array(
                    'site_id' => $site_id,
                    'error' => $site_details->get_error_message()
                ));
                return $site_details;
            }
            
            $site_url = $site_details['data']['url'] ?? '';
            if (empty($site_url)) {
                return new WP_Error('no_site_url', __('Could not determine site URL for demo helper disable', 'iwp-wp-integration'));
            }
        }

        // Construct the demo helper disable endpoint
        $demo_helper_url = trailingslashit($site_url) . 'wp-json/iwp-demo-helper/v1/disable';

        IWP_Logger::info('Disabling demo helper plugin', 'api-client', array(
            'site_id' => $site_id,
            'site_url' => $site_url,
            'endpoint' => $demo_helper_url
        ));

        // Make the API call to the site's demo helper endpoint
        $response = wp_remote_post($demo_helper_url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'InstaWP-Integration/' . IWP_VERSION
            ),
            'body' => wp_json_encode(array(
                'source' => 'instawp-integration',
                'site_id' => $site_id,
                'timestamp' => current_time('timestamp')
            ))
        ));

        if (is_wp_error($response)) {
            IWP_Logger::error('Failed to disable demo helper - network error', 'api-client', array(
                'site_id' => $site_id,
                'site_url' => $site_url,
                'error' => $response->get_error_message()
            ));
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code === 200) {
            IWP_Logger::info('Successfully disabled demo helper plugin', 'api-client', array(
                'site_id' => $site_id,
                'site_url' => $site_url,
                'response' => $response_body
            ));
            
            return array(
                'success' => true,
                'message' => 'Demo helper plugin disabled successfully',
                'response_body' => $response_body
            );
        } elseif ($response_code === 404) {
            // Plugin not installed or endpoint not found - this is expected and not an error
            IWP_Logger::info('Demo helper plugin not found (expected)', 'api-client', array(
                'site_id' => $site_id,
                'site_url' => $site_url,
                'response_code' => $response_code
            ));
            
            return array(
                'success' => true,
                'message' => 'Demo helper plugin not installed (silently ignored)',
                'response_body' => $response_body
            );
        } else {
            // Other HTTP error codes
            IWP_Logger::warning('Demo helper disable returned non-success code', 'api-client', array(
                'site_id' => $site_id,
                'site_url' => $site_url,
                'response_code' => $response_code,
                'response_body' => $response_body
            ));
            
            return new WP_Error('demo_helper_error', sprintf(
                __('Demo helper disable failed with code %d: %s', 'iwp-wp-integration'),
                $response_code,
                $response_body
            ));
        }
    }

    /**
     * Delete InstaWP site
     *
     * @param int|string $site_id
     * @return array|WP_Error
     */
    public function delete_site($site_id) {
        if (empty($site_id)) {
            return new WP_Error('invalid_site_id', __('Site ID is required', 'iwp-woo-v2'));
        }

        // Support both numeric and hash site IDs
        $sanitized_site_id = is_numeric($site_id) ? intval($site_id) : sanitize_text_field($site_id);

        IWP_Logger::info('Deleting site', 'api-client', array('site_id' => $sanitized_site_id));

        $endpoint = 'sites/' . $sanitized_site_id;
        
        $response = $this->make_request($endpoint, array(
            'method' => 'DELETE'
        ));

        if (is_wp_error($response)) {
            IWP_Logger::error('Site deletion failed', 'api-client', array(
                'site_id' => $sanitized_site_id,
                'error' => $response->get_error_message()
            ));
            return $response;
        }

        IWP_Logger::info('Site deletion successful', 'api-client', array('site_id' => $sanitized_site_id));
        return $response;
    }

    /**
     * Toggle site reservation status (permanent/temporary)
     *
     * @param int|string $site_id Site ID
     * @return array|WP_Error
     */
    public function toggle_site_reservation($site_id) {
        if (empty($site_id)) {
            return new WP_Error('invalid_site_id', __('Site ID is required', 'iwp-wp-integration'));
        }

        // Support both numeric and hash site IDs
        $sanitized_site_id = is_numeric($site_id) ? intval($site_id) : sanitize_text_field($site_id);

        IWP_Logger::info('Toggling site reservation status', 'api-client', array('site_id' => $sanitized_site_id));

        $endpoint = 'sites/' . $sanitized_site_id . '/reserve-toggle';
        
        $response = $this->make_request($endpoint, array(
            'method' => 'POST'
        ));

        if (is_wp_error($response)) {
            IWP_Logger::error('Site reservation toggle failed', 'api-client', array(
                'site_id' => $sanitized_site_id,
                'error' => $response->get_error_message()
            ));
            return $response;
        }

        IWP_Logger::info('Site reservation toggle successful', 'api-client', array(
            'site_id' => $sanitized_site_id,
            'new_status' => isset($response['is_reserved']) ? ($response['is_reserved'] ? 'permanent' : 'temporary') : 'unknown'
        ));
        return $response;
    }

    /**
     * Backward compatibility: Get templates (alias for get_snapshots)
     *
     * @return array|WP_Error
     * @deprecated Use get_snapshots() instead
     */
    public function get_templates() {
        return $this->get_snapshots();
    }

    /**
     * Backward compatibility: Clear templates cache (alias for clear_snapshots_cache)
     *
     * @deprecated Use clear_snapshots_cache() instead
     */
    public function clear_templates_cache() {
        return $this->clear_snapshots_cache();
    }

    /**
     * Update site configuration with post options
     *
     * @param string $site_id InstaWP site ID
     * @param array $post_options Configuration options to apply after site creation
     * @return array|WP_Error API response or error
     */
    public function update_site($site_id, $post_options = array()) {
        if (empty($site_id)) {
            return new WP_Error('missing_site_id', __('Site ID is required', 'iwp-woo-v2'));
        }

        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key not configured', 'iwp-woo-v2'));
        }

        $endpoint = 'sites/' . intval($site_id);
        
        $body = array();
        
        // Add post_options inside wordpress object structure per API spec
        if (!empty($post_options)) {
            $body['wordpress'] = array(
                'post_options' => $post_options
            );
        }

        IWP_Logger::info('Preparing site update request', 'api-client', array(
            'site_id' => $site_id,
            'endpoint' => $endpoint,
            'post_options' => $post_options
        ));

        // Use the centralized make_request method with PATCH
        $response = $this->make_request($endpoint, array(
            'method' => 'PATCH',
            'body' => wp_json_encode($body)
        ));

        if (is_wp_error($response)) {
            IWP_Logger::error('Site update request failed', 'api-client', array(
                'site_id' => $site_id,
                'error' => $response->get_error_message()
            ));
            return $response;
        }

        IWP_Logger::info('Site updated successfully', 'api-client', array(
            'site_id' => $site_id,
            'post_options' => !empty($post_options),
            'response_keys' => is_array($response) ? array_keys($response) : 'not_array'
        ));

        return $response;
    }

    /**
     * Backward compatibility: Get template (alias for get_snapshot)
     *
     * @param string $template_slug
     * @return array|WP_Error
     * @deprecated Use get_snapshot() instead
     */
    public function get_template($template_slug) {
        return $this->get_snapshot($template_slug);
    }

    /**
     * Backward compatibility: Create site from template (alias for create_site_from_snapshot)
     *
     * @param string $template_slug
     * @param array $site_data
     * @return array|WP_Error
     * @deprecated Use create_site_from_snapshot() instead
     */
    public function create_site_from_template($template_slug, $site_data = array()) {
        return $this->create_site_from_snapshot($template_slug, $site_data);
    }

    /**
     * Log API request for debugging
     *
     * @param string $endpoint
     * @param array $args
     * @param mixed $response
     */
    private function log_request($endpoint, $args, $response) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $options = get_option('iwp_options', array());
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : 'no';

        if ($debug_mode !== 'yes') {
            return;
        }

        $log_data = array(
            'endpoint' => $endpoint,
            'args' => $args,
            'response' => $response,
            'timestamp' => current_time('mysql'),
        );

        error_log('IWP WooCommerce v2 API Request: ' . wp_json_encode($log_data));
    }
}