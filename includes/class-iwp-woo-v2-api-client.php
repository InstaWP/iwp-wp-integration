<?php
/**
 * InstaWP API Client for IWP WooCommerce Integration v2
 *
 * @package IWP_Woo_V2
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_V2_API_Client class
 */
class IWP_Woo_V2_API_Client {

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
     * Constructor
     */
    public function __construct() {
        $options = get_option('iwp_woo_v2_options', array());
        $this->api_key = isset($options['api_key']) ? $options['api_key'] : '';
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
     * Make API request
     *
     * @param string $endpoint
     * @param array $args
     * @return array|WP_Error
     */
    private function make_request($endpoint, $args = array()) {
        if (empty($this->api_key)) {
            IWP_Woo_V2_Logger::error('API key is empty', 'api-client');
            return new WP_Error('no_api_key', __('API key is required', 'iwp-woo-v2'));
        }

        $url = rtrim($this->api_url, '/') . '/' . ltrim($endpoint, '/');
        
        $default_args = array(
            'timeout' => 30,
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
        IWP_Woo_V2_Logger::debug('Making API request', 'api-client', array('url' => $url));
        IWP_Woo_V2_Logger::debug('Request args', 'api-client', $log_args);

        $response = wp_remote_request($url, $args);

        // Log the response in errorlog
        IWP_Woo_V2_Logger::debug('Raw API response received', 'api-client');

        if (is_wp_error($response)) {
            IWP_Woo_V2_Logger::error('API request failed with WP_Error', 'api-client', array('error' => $response->get_error_message()));
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        // Log the response details
        IWP_Woo_V2_Logger::debug('API response code', 'api-client', array('code' => $response_code));
        error_log('IWP WooCommerce V2: API response headers: ' . wp_json_encode($response_headers->getAll()));
        IWP_Woo_V2_Logger::debug('API response body received', 'api-client');

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
            
            IWP_Woo_V2_Logger::error('API request failed', 'api-client', array('error' => $error_message));
            return new WP_Error('api_request_failed', $error_message);
        }

        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            IWP_Woo_V2_Logger::error('JSON decode error', 'api-client', array('error' => json_last_error_msg()));
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
        error_log('IWP WooCommerce V2: get_snapshots() called');
        
        $cached_snapshots = get_transient('iwp_woo_v2_snapshots');
        
        if (false !== $cached_snapshots) {
            error_log('IWP WooCommerce V2: Using cached snapshots data');
            return $cached_snapshots;
        }

        error_log('IWP WooCommerce V2: No cached snapshots found, making API request');
        $response = $this->make_request('snapshots', array(
            'method' => 'GET',
        ));

        if (is_wp_error($response)) {
            error_log('IWP WooCommerce V2: get_snapshots() failed: ' . $response->get_error_message());
            return $response;
        }

        // Cache for configurable duration (default 15 minutes)
        $cache_duration = apply_filters('iwp_woo_v2_snapshots_cache_duration', 15 * MINUTE_IN_SECONDS);
        set_transient('iwp_woo_v2_snapshots', $response, $cache_duration);
        error_log('IWP WooCommerce V2: Snapshots cached for ' . ($cache_duration / 60) . ' minutes');

        return $response;
    }

    /**
     * Clear snapshots cache
     */
    public function clear_snapshots_cache() {
        delete_transient('iwp_woo_v2_snapshots');
        error_log('IWP WooCommerce V2: Snapshots cache cleared');
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
        $cache_key = 'iwp_woo_v2_plans';
        $cached_plans = get_transient($cache_key);
        
        if ($cached_plans !== false) {
            error_log('IWP WooCommerce V2: Returning cached plans data');
            return $cached_plans;
        }

        error_log('IWP WooCommerce V2: Fetching plans from API');
        
        $response = $this->make_request('get-plans?product_type=sites', array(
            'method' => 'GET',
        ));

        if (is_wp_error($response)) {
            error_log('IWP WooCommerce V2: Failed to fetch plans: ' . $response->get_error_message());
            return $response;
        }

        // Cache for configurable duration (default 1 hour)
        $cache_duration = apply_filters('iwp_woo_v2_plans_cache_duration', HOUR_IN_SECONDS);
        set_transient($cache_key, $response, $cache_duration);
        
        // Count plans from numbered keys (0, 1, 2, etc.)
        $plan_count = 0;
        if (isset($response) && is_array($response)) {
            foreach ($response as $key => $value) {
                if (is_numeric($key) && is_array($value) && isset($value['id'])) {
                    $plan_count++;
                }
            }
        }
        
        error_log('IWP WooCommerce V2: Successfully fetched ' . $plan_count . ' plans');
        return $response;
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

        IWP_Woo_V2_Logger::info('Deleting site', 'api-client', array('site_id' => $sanitized_site_id));

        $endpoint = 'sites/' . $sanitized_site_id;
        
        $response = $this->make_request($endpoint, array(
            'method' => 'DELETE'
        ));

        if (is_wp_error($response)) {
            IWP_Woo_V2_Logger::error('Site deletion failed', 'api-client', array(
                'site_id' => $sanitized_site_id,
                'error' => $response->get_error_message()
            ));
            return $response;
        }

        IWP_Woo_V2_Logger::info('Site deletion successful', 'api-client', array('site_id' => $sanitized_site_id));
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

        $options = get_option('iwp_woo_v2_options', array());
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