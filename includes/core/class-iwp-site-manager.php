<?php
/**
 * Site Manager Class
 *
 * Handles site creation, progress tracking, and storage in WooCommerce orders
 *
 * @package IWP
 */

if (!defined('ABSPATH')) {
    exit;
}

class IWP_Site_Manager {

    /**
     * API Client instance
     *
     * @var IWP_API_Client
     */
    private $api_client;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new IWP_API_Client();
        
        // Hook into WordPress actions
        add_action('wp_ajax_iwp_check_site_status', array($this, 'ajax_check_site_status'));
        add_action('wp_ajax_nopriv_iwp_check_site_status', array($this, 'ajax_check_site_status'));
        add_action('iwp_check_pending_sites', array($this, 'check_pending_sites'));
        
        // Schedule periodic check for pending sites
        if (!wp_next_scheduled('iwp_check_pending_sites')) {
            wp_schedule_event(time(), 'every_minute', 'iwp_check_pending_sites');
        }
        
        // Add immediate check on admin pages for better user experience
        add_action('admin_init', array($this, 'maybe_check_pending_sites_immediately'));
        
        // Add AJAX handler for manual status refresh
        add_action('wp_ajax_iwp_refresh_site_status', array($this, 'ajax_refresh_site_status'));
    }

    /**
     * Create site from snapshot with progress tracking
     *
     * @param string $snapshot_slug
     * @param array $site_data
     * @param int $order_id
     * @param int $product_id
     * @param string $plan_id Optional plan ID
     * @return array|WP_Error
     */
    public function create_site_with_tracking($snapshot_slug, $site_data, $order_id, $product_id, $plan_id = '') {
        // Get order and user information for database tracking
        $order = wc_get_order($order_id);
        $user_id = $order ? $order->get_customer_id() : 0;

        // Create initial database record with "creating" status BEFORE API call
        $initial_site_data = array(
            'site_id' => 'pending-' . uniqid(), // Temporary ID until we get real site_id
            'status' => 'creating',
            'snapshot_slug' => $snapshot_slug,
            'plan_id' => $plan_id,
            'product_id' => $product_id,
            'order_id' => $order_id,
            'user_id' => $user_id,
            'source' => 'woocommerce',
            'source_data' => array(
                'snapshot_slug' => $snapshot_slug,
                'plan_id' => $plan_id,
                'site_data' => $site_data
            )
        );

        $db_site_id = IWP_Sites_Model::create($initial_site_data);
        IWP_Logger::info('Created initial site record in database', 'site-manager', array(
            'db_id' => $db_site_id,
            'order_id' => $order_id,
            'snapshot_slug' => $snapshot_slug
        ));

        // Create the site
        $response = $this->api_client->create_site_from_snapshot($snapshot_slug, $site_data, $plan_id);
        
        if (is_wp_error($response)) {
            // Update database record to failed status
            if ($db_site_id) {
                IWP_Sites_Model::update($initial_site_data['site_id'], array(
                    'status' => 'failed',
                    'api_response' => array('error' => $response->get_error_message())
                ));
            }
            IWP_Logger::error('Site creation failed', 'site-manager', array('error' => $response->get_error_message()));
            return $response;
        }

        $site_data_response = $response['data'] ?? array();
        
        // Debug: Log the actual API response structure
        IWP_Logger::debug('Site creation API response received', 'site-manager', $site_data_response);

        // Update database record with real site ID and response data
        $real_site_id = $site_data_response['id'] ?? $initial_site_data['site_id'];
        $update_data = array(
            'site_id' => $real_site_id,
            'site_url' => $site_data_response['wp_url'] ?? '',
            'wp_username' => $site_data_response['wp_username'] ?? '',
            'wp_password' => $site_data_response['wp_password'] ?? '',
            'wp_admin_url' => !empty($site_data_response['wp_url']) ? trailingslashit($site_data_response['wp_url']) . 'wp-admin' : '',
            's_hash' => $site_data_response['s_hash'] ?? '',
            'status' => $this->determine_site_status($site_data_response),
            'task_id' => $site_data_response['task_id'] ?? null,
            'is_pool' => isset($site_data_response['is_pool']) ? (bool)$site_data_response['is_pool'] : false,
            'is_reserved' => isset($site_data['is_reserved']) ? (bool)$site_data['is_reserved'] : true,
            'expiry_hours' => isset($site_data['expiry_hours']) ? intval($site_data['expiry_hours']) : null,
            'api_response' => $site_data_response
        );

        IWP_Sites_Model::update($initial_site_data['site_id'], $update_data);
        IWP_Logger::info('Updated site record with API response', 'site-manager', array(
            'old_site_id' => $initial_site_data['site_id'],
            'new_site_id' => $real_site_id,
            'status' => $update_data['status']
        ));
        
        // Prepare site information for storage
        $site_info = array(
            'snapshot_slug' => $snapshot_slug,
            'plan_id' => $plan_id,
            'product_id' => $product_id,
            'site_id' => $site_data_response['id'] ?? null,
            'wp_url' => $site_data_response['wp_url'] ?? '',
            'wp_username' => $site_data_response['wp_username'] ?? '',
            'wp_password' => $site_data_response['wp_password'] ?? '',
            's_hash' => $site_data_response['s_hash'] ?? '',
            'task_id' => $site_data_response['task_id'] ?? null,
            'api_status' => $site_data_response['status'] ?? null,
            'token' => $site_data_response['token'] ?? null,
            'remaining_site_minutes' => $site_data_response['remaining_site_minutes'] ?? null,
            'apply_wp_config_task_id' => $site_data_response['apply_wp_config_task_id'] ?? null,
            'is_pool' => isset($site_data_response['is_pool']) ? (bool)$site_data_response['is_pool'] : false,
            'status' => $this->determine_site_status($site_data_response),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        // Store site information in order meta
        $this->store_site_in_order($order_id, $site_info);

        // Determine if we need to track task progress based on is_pool
        $is_pool_exists = array_key_exists('is_pool', $site_data_response);
        $is_pool_value = $is_pool_exists ? $site_data_response['is_pool'] : null;
        
        // Track task if:
        // 1. is_pool is present and false, OR
        // 2. is_pool is not present at all (process ongoing)
        // AND we have a task_id
        if (!empty($site_data_response['task_id'])) {
            if ($is_pool_exists && $is_pool_value === true) {
                // is_pool is true - site is ready, no tracking needed
                IWP_Logger::info('Skipping task tracking - is_pool is true, site is ready immediately', 'site-manager');
            } else if ($is_pool_exists && $is_pool_value === false) {
                // is_pool is false - need to track task
                IWP_Logger::info('Adding site to pending tracking - is_pool is false', 'site-manager', array('task_id' => $site_data_response['task_id']));
                $this->add_pending_site($order_id, $product_id, $site_data_response['task_id'], $site_info);
            } else if (!$is_pool_exists) {
                // is_pool not present - process ongoing, need to track task
                IWP_Logger::info('Adding site to pending tracking - is_pool not present, process ongoing', 'site-manager', array('task_id' => $site_data_response['task_id']));
                $this->add_pending_site($order_id, $product_id, $site_data_response['task_id'], $site_info);
            }
        } else {
            IWP_Logger::debug('No task_id present - cannot track progress', 'site-manager');
        }

        return $site_info;
    }

    /**
     * Store site information in WooCommerce order
     *
     * @param int $order_id
     * @param array $site_info
     */
    private function store_site_in_order($order_id, $site_info) {
        // Get existing sites for this order
        $existing_sites = get_post_meta($order_id, '_iwp_created_sites', true);
        if (!is_array($existing_sites)) {
            $existing_sites = array();
        }

        // Add new site to the list
        $existing_sites[] = $site_info;

        // Update order meta
        update_post_meta($order_id, '_iwp_created_sites', $existing_sites);

        // Add order note
        $order = wc_get_order($order_id);
        if ($order) {
            if ($site_info['status'] === 'completed') {
                $note = sprintf(
                    __('Site created successfully: %s (Username: %s)', 'iwp-woo-v2'),
                    $site_info['wp_url'],
                    $site_info['wp_username']
                );
            } else {
                $note = sprintf(
                    __('Site creation started. Status: %s (Task ID: %s)', 'iwp-woo-v2'),
                    $site_info['status'],
                    $site_info['task_id']
                );
            }
            $order->add_order_note($note, 1); // 1 = customer visible
        }
    }

    /**
     * Add site to pending tracking list
     *
     * @param int $order_id
     * @param int $product_id
     * @param string $task_id
     * @param array $site_info
     */
    private function add_pending_site($order_id, $product_id, $task_id, $site_info) {
        $pending_sites = get_option('iwp_pending_sites', array());
        
        $pending_sites[$task_id] = array(
            'order_id' => $order_id,
            'product_id' => $product_id,
            'site_info' => $site_info,
            'created_at' => current_time('mysql')
        );

        update_option('iwp_pending_sites', $pending_sites);
    }

    /**
     * Check status of pending sites
     */
    public function check_pending_sites() {
        // Check database sites first (new method)
        $pending_db_sites = IWP_Sites_Model::get_pending_sites();
        
        foreach ($pending_db_sites as $db_site) {
            if (!empty($db_site->task_id)) {
                $this->check_database_site_status($db_site);
            }
        }

        // Also check legacy pending sites from options (backward compatibility)
        $pending_sites = get_option('iwp_pending_sites', array());
        
        if (!empty($pending_sites)) {
            foreach ($pending_sites as $task_id => $site_data) {
                $this->check_single_site_status($task_id, $site_data);
            }
        }
    }

    /**
     * Check status of a database site record
     *
     * @param object $db_site Database site record
     */
    private function check_database_site_status($db_site) {
        IWP_Logger::info('Checking database site status', 'site-manager', array(
            'site_id' => $db_site->site_id,
            'task_id' => $db_site->task_id,
            'order_id' => $db_site->order_id
        ));
        
        $response = $this->api_client->get_task_status($db_site->task_id);
        
        if (is_wp_error($response)) {
            IWP_Logger::error('Failed to check database site task status', 'site-manager', array(
                'site_id' => $db_site->site_id,
                'task_id' => $db_site->task_id,
                'error' => $response->get_error_message()
            ));
            return;
        }

        $task_info = $response['data'] ?? array();
        $raw_status = $task_info['status'] ?? 1;
        $status = $this->map_status_code($raw_status);

        IWP_Logger::info('Database site task status check result', 'site-manager', array(
            'site_id' => $db_site->site_id,
            'task_id' => $db_site->task_id,
            'raw_status' => $raw_status,
            'mapped_status' => $status
        ));

        // If task is still in progress, continue waiting
        if ($status === 'progress') {
            IWP_Logger::debug('Database site task still in progress', 'site-manager', array(
                'site_id' => $db_site->site_id,
                'task_id' => $db_site->task_id
            ));
            return;
        }

        // Task is completed or failed - update database record
        if ($status === 'completed' || $status === 'success') {
            error_log('IWP DEBUG: site-manager database handler - About to process completed site');
            error_log('IWP DEBUG: site-manager database handler - Site ID: ' . $db_site->site_id . ', Task ID: ' . $db_site->task_id);
            
            // Fetch complete site details now that the site is ready
            $site_details_response = $this->api_client->get_site_details($db_site->site_id);
            $update_data = array(
                'status' => 'completed',
                'api_response' => $task_info
            );
            
            if (!is_wp_error($site_details_response) && isset($site_details_response['data'])) {
                $site_details = $site_details_response['data'];
                
                // Update credentials and URLs from site details
                if (!empty($site_details['url'])) {
                    $update_data['site_url'] = $site_details['url'];
                }
                if (isset($site_details['site_meta']['wp_username'])) {
                    $update_data['wp_username'] = $site_details['site_meta']['wp_username'];
                }
                if (isset($site_details['site_meta']['wp_password'])) {
                    $update_data['wp_password'] = $site_details['site_meta']['wp_password'];
                }
                if (isset($site_details['s_hash'])) {
                    $update_data['s_hash'] = $site_details['s_hash'];
                }
                
                IWP_Logger::info('Updated database site with fresh credentials', 'site-manager', array(
                    'site_id' => $db_site->site_id,
                    'has_username' => !empty($update_data['wp_username']),
                    'has_password' => !empty($update_data['wp_password'])
                ));
            } else {
                IWP_Logger::warning('Could not fetch site details for credentials', 'site-manager', array(
                    'site_id' => $db_site->site_id,
                    'error' => is_wp_error($site_details_response) ? $site_details_response->get_error_message() : 'No data returned'
                ));
            }
            
            error_log('IWP DEBUG: site-manager database handler - About to call IWP_Sites_Model::update');
            error_log('IWP DEBUG: site-manager database handler - Update data: ' . print_r($update_data, true));
            IWP_Sites_Model::update($db_site->site_id, $update_data);
            error_log('IWP DEBUG: site-manager database handler - IWP_Sites_Model::update completed');

            // Add success note to order if available (use updated credentials)
            if (!empty($db_site->order_id)) {
                $order = wc_get_order($db_site->order_id);
                if ($order) {
                    $note = sprintf(
                        __('Site creation completed: %s (Username: %s)', 'iwp-woo-v2'),
                        $update_data['site_url'] ?? $db_site->site_url ?: 'Site URL pending',
                        $update_data['wp_username'] ?? $db_site->wp_username ?: 'Username pending'
                    );
                    $order->add_order_note($note, 1);
                }
            }

            IWP_Logger::info('Database site marked as completed', 'site-manager', array(
                'site_id' => $db_site->site_id,
                'task_id' => $db_site->task_id
            ));
            
            error_log('IWP DEBUG: site-manager database handler - Completed processing, about to exit success branch');
        } else {
            // Task failed
            IWP_Sites_Model::update($db_site->site_id, array(
                'status' => 'failed',
                'api_response' => $task_info
            ));

            // Add failure note to order if available
            if (!empty($db_site->order_id)) {
                $order = wc_get_order($db_site->order_id);
                if ($order) {
                    $note = sprintf(
                        __('Site creation failed for task: %s', 'iwp-woo-v2'),
                        $db_site->task_id
                    );
                    $order->add_order_note($note, 1);
                }
            }

            IWP_Logger::info('Database site marked as failed', 'site-manager', array(
                'site_id' => $db_site->site_id,
                'task_id' => $db_site->task_id
            ));
        }
    }

    /**
     * Check status of a single site
     *
     * @param string $task_id
     * @param array $site_data
     */
    private function check_single_site_status($task_id, $site_data) {
        IWP_Logger::info('Checking single site status', 'site-manager', array('task_id' => $task_id, 'order_id' => $site_data['order_id']));
        
        $response = $this->api_client->get_task_status($task_id);
        
        if (is_wp_error($response)) {
            IWP_Logger::error('Failed to check task status', 'site-manager', array('task_id' => $task_id, 'error' => $response->get_error_message()));
            return;
        }

        $task_info = $response['data'] ?? array();
        $raw_status = $task_info['status'] ?? 1;
        $status = $this->map_status_code($raw_status);

        IWP_Logger::info('Task status check result', 'site-manager', array(
            'task_id' => $task_id,
            'raw_status' => $raw_status,
            'mapped_status' => $status,
            'task_info' => $task_info
        ));

        // If task is still in progress, continue waiting
        if ($status === 'progress') {
            IWP_Logger::debug('Task still in progress, continuing to wait', 'site-manager', array('task_id' => $task_id));
            return;
        }

        // Task is completed or failed
        $order_id = $site_data['order_id'];
        $site_info = $site_data['site_info'];

        if ($status === 'completed' || $status === 'success') {
            // Update site status to completed
            $site_info['status'] = 'completed';
            $site_info['updated_at'] = current_time('mysql');
            
            // Fetch complete site details now that the site is ready
            if (!empty($site_info['site_id'])) {
                $site_details_response = $this->api_client->get_site_details($site_info['site_id']);
                $db_update_data = array(
                    'status' => 'completed',
                    'api_response' => $task_info
                );
                
                if (!is_wp_error($site_details_response) && isset($site_details_response['data'])) {
                    $site_details = $site_details_response['data'];
                    
                    // Update site_info with fresh credentials and URLs
                    if (!empty($site_details['url'])) {
                        $site_info['wp_url'] = $site_details['url'];
                        $db_update_data['site_url'] = $site_details['url'];
                    }
                    if (isset($site_details['site_meta']['wp_username'])) {
                        $site_info['wp_username'] = $site_details['site_meta']['wp_username'];
                        $db_update_data['wp_username'] = $site_details['site_meta']['wp_username'];
                    }
                    if (isset($site_details['site_meta']['wp_password'])) {
                        $site_info['wp_password'] = $site_details['site_meta']['wp_password'];
                        $db_update_data['wp_password'] = $site_details['site_meta']['wp_password'];
                    }
                    if (isset($site_details['s_hash'])) {
                        $site_info['s_hash'] = $site_details['s_hash'];
                        $db_update_data['s_hash'] = $site_details['s_hash'];
                    }
                    
                    IWP_Logger::info('Updated legacy site with fresh credentials', 'site-manager', array(
                        'site_id' => $site_info['site_id'],
                        'has_username' => !empty($site_info['wp_username']),
                        'has_password' => !empty($site_info['wp_password'])
                    ));
                } else {
                    IWP_Logger::warning('Could not fetch site details for legacy site credentials', 'site-manager', array(
                        'site_id' => $site_info['site_id'],
                        'error' => is_wp_error($site_details_response) ? $site_details_response->get_error_message() : 'No data returned'
                    ));
                }
                
                // Update database record
                IWP_Sites_Model::update($site_info['site_id'], $db_update_data);
                IWP_Logger::info('Updated database site record to completed', 'site-manager', array(
                    'site_id' => $site_info['site_id'],
                    'task_id' => $task_id
                ));
            }
            
            // Get fresh site data if needed
            $this->update_completed_site($order_id, $site_info);
            
            // Add success note to order (now with updated credentials)
            $order = wc_get_order($order_id);
            if ($order) {
                $note = sprintf(
                    __('Site creation completed: %s (Username: %s)', 'iwp-woo-v2'),
                    $site_info['wp_url'] ?: 'Site URL pending',
                    $site_info['wp_username'] ?: 'Username pending'
                );
                $order->add_order_note($note, 1); // 1 = customer visible
            }
        } else {
            // Task failed
            $site_info['status'] = 'failed';
            $site_info['updated_at'] = current_time('mysql');
            
            // Update database record
            if (!empty($site_info['site_id'])) {
                IWP_Sites_Model::update($site_info['site_id'], array(
                    'status' => 'failed',
                    'api_response' => $task_info
                ));
                IWP_Logger::info('Updated database site record to failed', 'site-manager', array(
                    'site_id' => $site_info['site_id'],
                    'task_id' => $task_id
                ));
            }
            
            $this->update_failed_site($order_id, $site_info);
            
            // Add failure note to order
            $order = wc_get_order($order_id);
            if ($order) {
                $note = sprintf(
                    __('Site creation failed for task: %s', 'iwp-woo-v2'),
                    $task_id
                );
                $order->add_order_note($note, 1); // 1 = customer visible
            }
        }

        // Remove from pending list
        $pending_sites = get_option('iwp_pending_sites', array());
        unset($pending_sites[$task_id]);
        update_option('iwp_pending_sites', $pending_sites);
    }

    /**
     * Update completed site information in order
     *
     * @param int $order_id
     * @param array $site_info
     */
    private function update_completed_site($order_id, $site_info) {
        $existing_sites = get_post_meta($order_id, '_iwp_created_sites', true);
        if (!is_array($existing_sites)) {
            return;
        }

        // Find and update the site
        foreach ($existing_sites as &$site) {
            if ($site['task_id'] === $site_info['task_id']) {
                $site = array_merge($site, $site_info);
                break;
            }
        }

        update_post_meta($order_id, '_iwp_created_sites', $existing_sites);
    }

    /**
     * Update failed site information in order
     *
     * @param int $order_id
     * @param array $site_info
     */
    private function update_failed_site($order_id, $site_info) {
        $this->update_completed_site($order_id, $site_info);
    }

    /**
     * Check and update pending sites for a specific order
     *
     * @param int $order_id
     * @return bool True if any sites were updated
     */
    public function check_order_pending_sites($order_id) {
        $pending_sites = get_option('iwp_pending_sites', array());
        $updated = false;
        
        foreach ($pending_sites as $task_id => $site_data) {
            if ($site_data['order_id'] == $order_id) {
                $this->check_single_site_status($task_id, $site_data);
                $updated = true;
            }
        }
        
        return $updated;
    }

    /**
     * Get sites for an order
     *
     * @param int $order_id
     * @return array
     */
    public function get_order_sites($order_id) {
        // First, check for any pending sites for this order to ensure we have the latest status
        $this->check_order_pending_sites($order_id);
        
        $all_sites = array();
        $seen_sites = array(); // For deduplication
        
        // Debug logging
        IWP_Logger::debug('Getting sites for order', 'site-manager', array('order_id' => $order_id));
        
        // Prioritize the order processor _iwp_sites_created key (includes both created and upgraded)
        $order_sites = get_post_meta($order_id, '_iwp_sites_created', true);
        IWP_Logger::debug('Found sites in _iwp_sites_created', 'site-manager', array('count' => is_array($order_sites) ? count($order_sites) : 0));
        
        if (is_array($order_sites)) {
            foreach ($order_sites as $site_data) {
                // Transform order processor format to frontend format
                $site = $this->transform_site_data_for_frontend($site_data);
                if ($site) {
                    // Create a unique key for deduplication (use site_id if available, otherwise wp_url)
                    $unique_key = '';
                    if (!empty($site['site_id'])) {
                        $unique_key = 'site_' . $site['site_id'];
                    } elseif (!empty($site['wp_url'])) {
                        $unique_key = 'url_' . md5($site['wp_url']);
                    } else {
                        $unique_key = 'index_' . count($all_sites);
                    }
                    
                    if (!isset($seen_sites[$unique_key])) {
                        $all_sites[] = $site;
                        $seen_sites[$unique_key] = true;
                    }
                }
            }
        }
        
        // Only add legacy sites if no sites were found from the order processor
        // This prevents duplicates while maintaining backward compatibility
        if (empty($all_sites)) {
            $legacy_sites = get_post_meta($order_id, '_iwp_created_sites', true);
            IWP_Logger::debug('Found sites in _iwp_created_sites (legacy)', 'site-manager', array('count' => is_array($legacy_sites) ? count($legacy_sites) : 0));
            if (is_array($legacy_sites)) {
                foreach ($legacy_sites as $legacy_site) {
                    // Create unique key for legacy sites
                    $unique_key = '';
                    if (!empty($legacy_site['site_id'])) {
                        $unique_key = 'site_' . $legacy_site['site_id'];
                    } elseif (!empty($legacy_site['wp_url'])) {
                        $unique_key = 'url_' . md5($legacy_site['wp_url']);
                    } else {
                        $unique_key = 'legacy_' . count($all_sites);
                    }
                    
                    if (!isset($seen_sites[$unique_key])) {
                        $all_sites[] = $legacy_site;
                        $seen_sites[$unique_key] = true;
                    }
                }
            }
        }
        
        IWP_Logger::debug('Returning total sites for order', 'site-manager', array('order_id' => $order_id, 'total_count' => count($all_sites)));
        return $all_sites;
    }

    /**
     * Transform site data from order processor format to frontend display format
     *
     * @param array $site_data Site data from order processor
     * @return array|null Transformed site data or null if invalid
     */
    private function transform_site_data_for_frontend($site_data) {
        if (!is_array($site_data) || !isset($site_data['site_data'])) {
            return null;
        }
        
        $action = $site_data['action'] ?? 'created';
        $product_name = $site_data['product_name'] ?? '';
        $raw_site_data = $site_data['site_data'];
        
        if ($action === 'upgraded') {
            // Handle upgraded sites - extract site details from upgrade response
            $site_details = null;
            
            // Check different possible locations for site details in the API response
            if (isset($raw_site_data['upgrade_response']['site_details']['data'])) {
                $site_details = $raw_site_data['upgrade_response']['site_details']['data'];
            } elseif (isset($raw_site_data['site_details']['data'])) {
                $site_details = $raw_site_data['site_details']['data'];
            }
            
            if ($site_details) {
                // Extract credentials from site_meta (based on API response structure)
                $site_meta = $site_details['site_meta'] ?? array();
                $wp_username = $site_meta['wp_username'] ?? '';
                $wp_password = $site_meta['wp_password'] ?? '';
                
                // Extract URLs
                $site_url = $site_details['url'] ?? '';
                $admin_url = $site_details['wp_admin_url'] ?? '';
                
                // Extract additional info
                $site_name = $site_details['name'] ?? '';
                $created_date = $site_details['created_at'] ?? current_time('mysql');
                
                return array(
                    'wp_url' => $site_url,
                    'wp_admin_url' => $admin_url,
                    'wp_username' => $wp_username,
                    'wp_password' => $wp_password,
                    's_hash' => $site_details['s_hash'] ?? '',
                    'status' => 'completed',
                    'created_at' => $created_date,
                    'snapshot_slug' => $site_details['template']['slug'] ?? '',
                    'site_id' => $raw_site_data['site_id'] ?? $site_details['id'] ?? '',
                    'site_name' => $site_name,
                    'plan_id' => $raw_site_data['plan_id'] ?? '',
                    'action' => 'upgraded',
                    'product_name' => $product_name
                );
            } else {
                // Fallback for upgraded sites without full details
                return array(
                    'wp_url' => '',
                    'wp_admin_url' => '',
                    'wp_username' => '',
                    'wp_password' => '',
                    's_hash' => '',
                    'status' => 'completed',
                    'created_at' => current_time('mysql'),
                    'snapshot_slug' => '',
                    'site_id' => $raw_site_data['site_id'] ?? '',
                    'site_name' => '',
                    'plan_id' => $raw_site_data['plan_id'] ?? '',
                    'action' => 'upgraded',
                    'product_name' => $product_name
                );
            }
        } else {
            // Handle created sites - check for both wp_url and site_url formats
            $site_url = $raw_site_data['wp_url'] ?? $raw_site_data['site_url'] ?? '';
            $admin_url = $raw_site_data['wp_admin_url'] ?? '';
            if (empty($admin_url) && !empty($site_url)) {
                $admin_url = trailingslashit($site_url) . 'wp-admin';
            }
            
            // Get the latest status from database if site_id is available
            $site_id = $raw_site_data['site_id'] ?? '';
            $current_status = $raw_site_data['status'] ?? 'completed';
            
            // Check database for updated status if we have a site_id
            if (!empty($site_id)) {
                error_log('IWP DEBUG: transform_site_data_for_frontend - About to call get_by_site_id for site_id: ' . $site_id);
                $db_site = IWP_Sites_Model::get_by_site_id($site_id);
                error_log('IWP DEBUG: transform_site_data_for_frontend - get_by_site_id returned: ' . ($db_site ? 'DATA' : 'NULL'));
                if ($db_site && !empty($db_site->status)) {
                    $current_status = $db_site->status;
                    
                    // If database has updated site details, use them
                    if ($db_site->status === 'completed') {
                        // Update site details from database fields (direct columns)
                        if (!empty($db_site->site_url) && empty($site_url)) {
                            $site_url = $db_site->site_url;
                        }
                        if (!empty($db_site->wp_username) && empty($raw_site_data['wp_username'])) {
                            $raw_site_data['wp_username'] = $db_site->wp_username;
                            error_log('IWP DEBUG: Updated wp_username from database: ' . $db_site->wp_username);
                        }
                        if (!empty($db_site->wp_password) && empty($raw_site_data['wp_password'])) {
                            $raw_site_data['wp_password'] = $db_site->wp_password;
                            error_log('IWP DEBUG: Updated wp_password from database: [REDACTED]');
                        }
                        if (!empty($db_site->s_hash) && empty($raw_site_data['s_hash'])) {
                            $raw_site_data['s_hash'] = $db_site->s_hash;
                        }
                        
                        // Also check API response for additional details if needed
                        if (!empty($db_site->api_response)) {
                            $api_response = json_decode($db_site->api_response, true);
                            if (is_array($api_response)) {
                                // Fallback to API response if database fields are still empty
                                if (!empty($api_response['wp_url']) && empty($site_url)) {
                                    $site_url = $api_response['wp_url'];
                                }
                                if (!empty($api_response['s_hash']) && empty($raw_site_data['s_hash'])) {
                                    $raw_site_data['s_hash'] = $api_response['s_hash'];
                                }
                            }
                        }
                    }
                }
            }
            
            return array(
                'wp_url' => $site_url,
                'wp_admin_url' => $admin_url,
                'wp_username' => $raw_site_data['wp_username'] ?? '',
                'wp_password' => $raw_site_data['wp_password'] ?? '',
                's_hash' => $raw_site_data['s_hash'] ?? '',
                'status' => $current_status,
                'created_at' => $raw_site_data['created_at'] ?? current_time('mysql'),
                'snapshot_slug' => $raw_site_data['snapshot_slug'] ?? '',
                'site_id' => $site_id,
                'site_name' => $raw_site_data['site_name'] ?? '',
                'is_pool' => isset($raw_site_data['is_pool']) ? (bool)$raw_site_data['is_pool'] : false,
                'action' => 'created',
                'product_name' => $product_name
            );
        }
    }

    /**
     * AJAX handler to check site status
     */
    public function ajax_check_site_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iwp_check_status')) {
            wp_die(__('Security check failed', 'iwp-woo-v2'));
        }

        // Check capabilities
        if (!current_user_can('view_woocommerce_reports')) {
            wp_die(__('Insufficient permissions', 'iwp-woo-v2'));
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error(__('Invalid order ID', 'iwp-woo-v2'));
        }

        $sites = $this->get_order_sites($order_id);
        wp_send_json_success($sites);
    }

    /**
     * Determine site status based on API response data
     *
     * @param array $site_data_response
     * @return string
     */
    private function determine_site_status($site_data_response) {
        // Check if is_pool exists in the response
        $is_pool_exists = array_key_exists('is_pool', $site_data_response);
        
        // If is_pool is present and true - site is ready immediately
        if ($is_pool_exists && $site_data_response['is_pool'] === true) {
            IWP_Logger::info('Site determined as completed - is_pool is present and true', 'site-manager');
            return 'completed';
        }
        
        // If is_pool is present and false - need to check task status
        if ($is_pool_exists && $site_data_response['is_pool'] === false) {
            IWP_Logger::info('is_pool is present and false - checking task status', 'site-manager');
            if (!empty($site_data_response['task_id'])) {
                $status = $this->map_status_code($site_data_response['status'] ?? 1);
                IWP_Logger::info('Task status mapped', 'site-manager', array('status' => $status));
                return $status;
            }
        }
        
        // If is_pool is not present at all - process is ongoing, need task checking
        if (!$is_pool_exists) {
            IWP_Logger::info('is_pool not present - process ongoing, need task checking', 'site-manager');
            if (!empty($site_data_response['task_id'])) {
                $status = $this->map_status_code($site_data_response['status'] ?? 1);
                IWP_Logger::info('Task status mapped for ongoing process', 'site-manager', array('status' => $status));
                return $status;
            }
            // If no task_id but is_pool is missing, assume still processing
            return 'progress';
        }
        
        // Fallback: If we have a wp_url and wp_username, the site is ready
        if (!empty($site_data_response['wp_url']) && !empty($site_data_response['wp_username'])) {
            IWP_Logger::info('Site determined as completed - has wp_url and wp_username', 'site-manager');
            return 'completed';
        }
        
        // Default to progress if we can't determine status
        IWP_Logger::warning('Could not determine site status, defaulting to progress', 'site-manager');
        return 'progress';
    }

    /**
     * Map status codes (numeric or string) to normalized string values
     *
     * @param int|string $status_code
     * @return string
     */
    private function map_status_code($status_code) {
        // Handle string status values first (from task API)
        if (is_string($status_code)) {
            switch (strtolower($status_code)) {
                case 'completed':
                case 'success':
                case 'done':
                    return 'completed';
                case 'progress':
                case 'in_progress':
                case 'pending':
                case 'running':
                    return 'progress';
                case 'failed':
                case 'error':
                case 'failure':
                    return 'failed';
                default:
                    // Unknown string status, treat as progress
                    IWP_Logger::warning('Unknown string status received', 'site-manager', array('status' => $status_code));
                    return 'progress';
            }
        }
        
        // Handle numeric status codes (from site creation API)
        $code = intval($status_code);
        switch ($code) {
            case 0:
                return 'completed'; // Site creation completed
            case 1:
                return 'progress';  // Site creation in progress
            case 2:
                return 'failed';    // Site creation failed
            default:
                // Handle any unknown status codes as progress
                IWP_Logger::warning('Unknown numeric status code received', 'site-manager', array('status_code' => $status_code));
                return 'progress';
        }
    }

    /**
     * Get site creation progress for display
     *
     * @param int $order_id
     * @return array
     */
    public function get_site_progress($order_id) {
        $sites = $this->get_order_sites($order_id);
        $progress = array();

        foreach ($sites as $site) {
            $progress[] = array(
                'product_id' => $site['product_id'],
                'snapshot_slug' => $site['snapshot_slug'],
                'status' => $site['status'],
                'wp_url' => $site['wp_url'] ?? '',
                'wp_username' => $site['wp_username'] ?? '',
                'created_at' => $site['created_at'],
                'updated_at' => $site['updated_at']
            );
        }

        return $progress;
    }

    /**
     * Check pending sites immediately on admin pages for better user experience
     * This runs on admin_init to provide more immediate feedback
     */
    public function maybe_check_pending_sites_immediately() {
        // Only run on specific admin pages to avoid performance issues
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('toplevel_page_instawp-integration', 'instawp_page_instawp-sites'))) {
            return;
        }

        // Check if we have any pending sites
        $pending_db_sites = IWP_Sites_Model::get_pending_sites();
        if (empty($pending_db_sites)) {
            return;
        }

        // Only check if last check was more than 30 seconds ago to avoid excessive API calls
        $last_check = get_transient('iwp_last_immediate_check');
        if ($last_check && (time() - $last_check) < 30) {
            return;
        }

        // Set transient to prevent too frequent checks
        set_transient('iwp_last_immediate_check', time(), 60);

        // Check pending sites
        $this->check_pending_sites();
    }

    /**
     * AJAX handler for manual site status refresh
     */
    public function ajax_refresh_site_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'iwp_refresh_site_status')) {
            wp_send_json_error(array('message' => __('Security check failed', 'iwp-wp-integration')));
        }

        // Check capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'iwp-wp-integration')));
        }

        // Force check pending sites
        delete_transient('iwp_last_immediate_check');
        $this->check_pending_sites();

        // Get updated pending sites count
        $pending_db_sites = IWP_Sites_Model::get_pending_sites();
        $pending_count = count($pending_db_sites);

        wp_send_json_success(array(
            'message' => sprintf(__('Status refreshed. %d sites still pending.', 'iwp-wp-integration'), $pending_count),
            'pending_count' => $pending_count
        ));
    }
}
