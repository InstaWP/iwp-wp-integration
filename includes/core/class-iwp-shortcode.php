<?php
/**
 * IWP Woo V2 Shortcode Class
 *
 * Handles the iwp_site_creator shortcode functionality
 *
 * @package IWP
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class IWP_Shortcode {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('iwp_site_creator', array($this, 'render_shortcode'));
        add_action('wp_ajax_iwp_create_site_shortcode', array($this, 'handle_ajax_site_creation'));
        add_action('wp_ajax_nopriv_iwp_create_site_shortcode', array($this, 'handle_ajax_site_creation'));
        add_action('wp_ajax_iwp_check_task_status', array($this, 'handle_ajax_check_task_status'));
        add_action('wp_ajax_nopriv_iwp_check_task_status', array($this, 'handle_ajax_check_task_status'));
        add_action('wp_ajax_iwp_apply_delayed_post_options', array($this, 'handle_ajax_apply_delayed_post_options'));
        add_action('wp_ajax_nopriv_iwp_apply_delayed_post_options', array($this, 'handle_ajax_apply_delayed_post_options'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Render the iwp_site_creator shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'snapshot_slug' => '',
            'email' => '',
            'name' => '',
            'expiry_hours' => '',
            'sandbox' => ''
        ), $atts, 'iwp_site_creator');

        // Validate required parameters
        if (empty($atts['snapshot_slug'])) {
            return '<div class="iwp-site-creator-error">Error: snapshot_slug parameter is required</div>';
        }

        // Generate unique form ID
        $form_id = 'iwp-site-creator-' . uniqid();

        // Build form HTML
        ob_start();
        ?>
        <div class="iwp-site-creator-container" id="<?php echo esc_attr($form_id); ?>">
            <form class="iwp-site-creator-form" method="post">
                <div class="iwp-site-creator-field-group">
                    <label class="iwp-site-creator-label" for="<?php echo esc_attr($form_id); ?>-name">
                        <?php esc_html_e('Name:', 'iwp-wp-integration'); ?>
                    </label>
                    <input 
                        type="text" 
                        class="iwp-site-creator-input iwp-site-creator-name" 
                        id="<?php echo esc_attr($form_id); ?>-name" 
                        name="site_name" 
                        value="<?php echo esc_attr($atts['name']); ?>" 
                        required 
                    />
                </div>

                <div class="iwp-site-creator-field-group">
                    <label class="iwp-site-creator-label" for="<?php echo esc_attr($form_id); ?>-email">
                        <?php esc_html_e('Email:', 'iwp-wp-integration'); ?>
                    </label>
                    <input 
                        type="email" 
                        class="iwp-site-creator-input iwp-site-creator-email" 
                        id="<?php echo esc_attr($form_id); ?>-email" 
                        name="site_email" 
                        value="<?php echo esc_attr($atts['email']); ?>" 
                        required 
                    />
                </div>

                <div class="iwp-site-creator-actions">
                    <button 
                        type="submit" 
                        class="iwp-site-creator-button iwp-site-creator-submit"
                    >
                        <?php esc_html_e('Create Site', 'iwp-wp-integration'); ?>
                    </button>
                </div>

                <div class="iwp-site-creator-status" style="display: none;">
                    <div class="iwp-site-creator-message"></div>
                    <div class="iwp-site-creator-progress">
                        <div class="iwp-site-creator-progress-bar"></div>
                    </div>
                </div>

                <div class="iwp-site-creator-results" style="display: none;">
                    <div class="iwp-site-creator-site-info">
                        <h3 class="iwp-site-creator-success-title"><?php esc_html_e('Site Created Successfully!', 'iwp-wp-integration'); ?></h3>
                        <div class="iwp-site-creator-site-details">
                            <div class="iwp-site-creator-detail">
                                <span class="iwp-site-creator-detail-label"><?php esc_html_e('Site URL:', 'iwp-wp-integration'); ?></span>
                                <a class="iwp-site-creator-site-url" href="#" target="_blank"></a>
                            </div>
                            <div class="iwp-site-creator-detail">
                                <span class="iwp-site-creator-detail-label"><?php esc_html_e('Admin Username:', 'iwp-wp-integration'); ?></span>
                                <span class="iwp-site-creator-username"></span>
                                <button type="button" class="iwp-site-creator-copy-btn" data-copy="username">
                                    <?php esc_html_e('Copy', 'iwp-wp-integration'); ?>
                                </button>
                            </div>
                            <div class="iwp-site-creator-detail">
                                <span class="iwp-site-creator-detail-label"><?php esc_html_e('Admin Password:', 'iwp-wp-integration'); ?></span>
                                <span class="iwp-site-creator-password" style="display: none;"></span>
                                <span class="iwp-site-creator-password-hidden">••••••••</span>
                                <button type="button" class="iwp-site-creator-toggle-password">
                                    <?php esc_html_e('Show', 'iwp-wp-integration'); ?>
                                </button>
                                <button type="button" class="iwp-site-creator-copy-btn" data-copy="password">
                                    <?php esc_html_e('Copy', 'iwp-wp-integration'); ?>
                                </button>
                            </div>
                            <div class="iwp-site-creator-detail">
                                <span class="iwp-site-creator-detail-label"><?php esc_html_e('Admin Login:', 'iwp-wp-integration'); ?></span>
                                <a class="iwp-site-creator-admin-url" href="#" target="_blank">
                                    <?php esc_html_e('Login to Admin', 'iwp-wp-integration'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hidden fields -->
                <input type="hidden" name="snapshot_slug" value="<?php echo esc_attr($atts['snapshot_slug']); ?>" />
                <input type="hidden" name="expiry_hours" value="<?php echo esc_attr($atts['expiry_hours']); ?>" />
                <input type="hidden" name="sandbox" value="<?php echo esc_attr($atts['sandbox']); ?>" />
                <input type="hidden" name="action" value="iwp_create_site_shortcode" />
                <?php wp_nonce_field('iwp_site_creator_nonce', 'iwp_site_creator_nonce'); ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX site creation request
     */
    public function handle_ajax_site_creation() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['iwp_site_creator_nonce'], 'iwp_site_creator_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'iwp-wp-integration')));
        }

        // Sanitize input data
        $snapshot_slug = sanitize_text_field($_POST['snapshot_slug']);
        $site_name = sanitize_text_field($_POST['site_name']);
        $site_email = sanitize_email($_POST['site_email']);
        $expiry_hours = !empty($_POST['expiry_hours']) ? intval($_POST['expiry_hours']) : '';
        $sandbox = !empty($_POST['sandbox']) ? sanitize_text_field($_POST['sandbox']) : '';

        // Validate required fields
        if (empty($snapshot_slug) || empty($site_name) || empty($site_email)) {
            wp_send_json_error(array('message' => __('All fields are required', 'iwp-wp-integration')));
        }

        if (!is_email($site_email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address', 'iwp-wp-integration')));
        }

        try {
            // Get configured API client
            $api_client = IWP_Service::get_api_client();
            if (is_wp_error($api_client)) {
                wp_send_json_error(array('message' => $api_client->get_error_message()));
            }
            
            // Prepare site data
            $site_data = array(
                'name' => $site_name,
                'email' => $site_email
            );

            // Add expiry and reservation parameters
            if (!empty($expiry_hours)) {
                $site_data['expiry_hours'] = $expiry_hours;
                $site_data['is_reserved'] = false;
            } else {
                $site_data['is_reserved'] = true;
            }

            // Add sandbox parameter if specified
            if (!empty($sandbox) && strtolower($sandbox) === 'true') {
                $site_data['is_shared'] = true;
            }

            // Create site from snapshot
            $result = $api_client->create_site_from_snapshot($snapshot_slug, $site_data);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            // Check if site was created successfully
            if (isset($result['data'])) {
                $site_info = $result['data'];
                
                // Extract site_id for post_options update
                $site_id = isset($site_info['site_id']) ? $site_info['site_id'] : (isset($site_info['id']) ? $site_info['id'] : null);
                
                // Prepare response data
                $response_data = array(
                    'site_url' => isset($site_info['wp_url']) ? $site_info['wp_url'] : '',
                    'admin_username' => isset($site_info['wp_username']) ? $site_info['wp_username'] : '',
                    'admin_password' => isset($site_info['wp_password']) ? $site_info['wp_password'] : '',
                    'admin_url' => isset($site_info['wp_url']) ? trailingslashit($site_info['wp_url']) . 'wp-admin' : '',
                    's_hash' => isset($site_info['s_hash']) ? $site_info['s_hash'] : '',
                    'status' => isset($site_info['status']) ? $site_info['status'] : 'pending',
                    'task_id' => isset($site_info['task_id']) ? $site_info['task_id'] : null,
                    'is_pool' => isset($site_info['is_pool']) ? $site_info['is_pool'] : false,
                    'site_id' => $site_id
                );

                // Determine site status using proper logic (same as site manager)
                $determined_status = $this->determine_site_status($site_info);
                $response_data['status'] = $determined_status;

                // Apply post_options only for completed sites
                if ($determined_status === 'completed' && $site_id) {
                    $this->apply_post_options_update($api_client, $site_id, $response_data);
                }

                // Store demo site in database for tracking and reconciliation
                $this->store_demo_site_in_database($response_data, $site_email, $site_name, $snapshot_slug, $expiry_hours);

                wp_send_json_success($response_data);
            } else {
                wp_send_json_error(array('message' => __('Site creation failed: Invalid API response', 'iwp-wp-integration')));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Store demo site in database for tracking and reconciliation
     *
     * @param array $response_data API response data
     * @param string $email Customer email
     * @param string $site_name Site name
     * @param string $snapshot_slug Snapshot slug
     * @param int $expiry_hours Expiry hours if temporary
     * @return int|false Database insert ID or false on failure
     */
    private function store_demo_site_in_database($response_data, $email, $site_name, $snapshot_slug, $expiry_hours = null) {
        try {
            // Get current user ID if logged in
            $user_id = is_user_logged_in() ? get_current_user_id() : 0;

            // Prepare site data for database
            $site_data = array(
                'site_id' => $response_data['site_id'],
                'site_url' => isset($response_data['site_url']) ? $response_data['site_url'] : '',
                'wp_username' => isset($response_data['admin_username']) ? $response_data['admin_username'] : '',
                'wp_password' => isset($response_data['admin_password']) ? $response_data['admin_password'] : '',
                'wp_admin_url' => isset($response_data['admin_url']) ? $response_data['admin_url'] : '',
                's_hash' => isset($response_data['s_hash']) ? $response_data['s_hash'] : '',
                'status' => $response_data['status'], // 'completed' or 'progress'
                'site_type' => 'demo', // NEW: Mark as demo site
                'task_id' => $response_data['task_id'] ?? null,
                'user_id' => $user_id, // 0 for guests, user_id for logged-in
                'source' => 'shortcode',
                'source_data' => array(
                    'email' => $email, // Store for reconciliation
                    'site_name' => $site_name,
                    'snapshot_slug' => $snapshot_slug,
                    'created_via' => 'shortcode'
                ),
                'is_pool' => $response_data['is_pool'] ?? false,
                'is_reserved' => !empty($expiry_hours) ? false : true,
                'expiry_hours' => !empty($expiry_hours) ? intval($expiry_hours) : null,
                'api_response' => $response_data // Store full API response
            );

            // Create database record
            IWP_Sites_Model::init();
            $db_site_id = IWP_Sites_Model::create($site_data);

            if ($db_site_id) {
                IWP_Logger::info('Demo site stored in database', 'shortcode', array(
                    'db_id' => $db_site_id,
                    'site_id' => $response_data['site_id'],
                    'email' => $email,
                    'user_id' => $user_id
                ));
            } else {
                IWP_Logger::error('Failed to store demo site in database', 'shortcode', array(
                    'site_id' => $response_data['site_id'],
                    'email' => $email
                ));
            }

            return $db_site_id;
        } catch (Exception $e) {
            // Log the error but don't fail the site creation
            IWP_Logger::error('Exception storing demo site in database', 'shortcode', array(
                'site_id' => $response_data['site_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Update demo site details in database after task completion
     *
     * @param string $site_id InstaWP site ID
     * @param array $response_data Processed site details for frontend
     * @param array $full_api_response Full API response from get_site_details
     * @return bool True on success, false on failure
     */
    private function update_demo_site_details($site_id, $response_data, $full_api_response) {
        try {
            IWP_Sites_Model::init();

            // Prepare update data
            $update_data = array(
                'site_url' => $response_data['site_url'],
                'wp_username' => $response_data['admin_username'],
                'wp_password' => $response_data['admin_password'],
                'wp_admin_url' => $response_data['admin_url'],
                's_hash' => $response_data['s_hash'],
                'status' => 'completed',
                'api_response' => $full_api_response // Store full API response
            );

            // Update the database record
            $success = IWP_Sites_Model::update($site_id, $update_data);

            if ($success) {
                IWP_Logger::info('Demo site details updated in database after task completion', 'shortcode', array(
                    'site_id' => $site_id,
                    'site_url' => $response_data['site_url']
                ));
            } else {
                IWP_Logger::error('Failed to update demo site details in database', 'shortcode', array(
                    'site_id' => $site_id
                ));
            }

            return $success;
        } catch (Exception $e) {
            IWP_Logger::error('Exception updating demo site details in database', 'shortcode', array(
                'site_id' => $site_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Determine site status based on API response (same logic as site manager)
     *
     * @param array $site_data_response API response data
     * @return string Site status (completed, progress, failed)
     */
    private function determine_site_status($site_data_response) {
        // Check if is_pool exists in the response
        $is_pool_exists = array_key_exists('is_pool', $site_data_response);
        
        // If is_pool is present and true - site is ready immediately
        if ($is_pool_exists && $site_data_response['is_pool'] === true) {
            IWP_Logger::info('Shortcode site determined as completed - is_pool is present and true', 'shortcode');
            return 'completed';
        }
        
        // If is_pool is present and false - need to check task status
        if ($is_pool_exists && $site_data_response['is_pool'] === false) {
            IWP_Logger::info('Shortcode site - is_pool is present and false - checking task status', 'shortcode');
            if (!empty($site_data_response['task_id'])) {
                $status = $this->map_status_code($site_data_response['status'] ?? 1);
                IWP_Logger::info('Shortcode site task status mapped', 'shortcode', array('status' => $status));
                return $status;
            }
        }
        
        // If is_pool is not present at all - process is ongoing, need task checking
        if (!$is_pool_exists) {
            IWP_Logger::info('Shortcode site - is_pool not present - process ongoing, need task checking', 'shortcode');
            if (!empty($site_data_response['task_id'])) {
                $status = $this->map_status_code($site_data_response['status'] ?? 1);
                IWP_Logger::info('Shortcode site task status mapped for ongoing process', 'shortcode', array('status' => $status));
                return $status;
            }
            // If no task_id but is_pool is missing, assume still processing
            return 'progress';
        }
        
        // Fallback: If we have a wp_url and wp_username, the site is ready
        if (!empty($site_data_response['wp_url']) && !empty($site_data_response['wp_username'])) {
            IWP_Logger::info('Shortcode site determined as completed - has wp_url and wp_username', 'shortcode');
            return 'completed';
        }
        
        // Default to progress if we can't determine status
        IWP_Logger::warning('Shortcode site - could not determine site status, defaulting to progress', 'shortcode');
        return 'progress';
    }

    /**
     * Map numeric/string status codes to standard status strings
     *
     * @param mixed $status_code Status code from API
     * @return string Mapped status (completed, progress, failed)
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
                    IWP_Logger::warning('Unknown string status received in shortcode', 'shortcode', array('status' => $status_code));
                    return 'progress';
            }
        }

        // Handle numeric status codes (0=completed, 1=progress, 2=failed)
        $status_code = intval($status_code);
        switch ($status_code) {
            case 0:
                return 'completed';
            case 1:
                return 'progress';
            case 2:
                return 'failed';
            default:
                IWP_Logger::warning('Unknown numeric status code in shortcode', 'shortcode', array('status_code' => $status_code));
                return 'progress';
        }
    }

    /**
     * Apply post_options configuration to newly created site
     *
     * @param IWP_API_Client $api_client
     * @param string $site_id
     * @param array $response_data
     */
    private function apply_post_options_update($api_client, $site_id, &$response_data) {
        try {
            // Define post_options to apply after site creation
            $post_options = array(
                'iwp_site_id' => $site_id,
                'iwp_created_at' => current_time('mysql')
            );

            IWP_Logger::info('Applying post_options to shortcode-created site', 'shortcode', array(
                'site_id' => $site_id,
                'post_options' => $post_options
            ));

            // Call the update site API with post_options
            $update_result = $api_client->update_site($site_id, $post_options);

            if (is_wp_error($update_result)) {
                IWP_Logger::warning('Post_options update failed for shortcode site', 'shortcode', array(
                    'site_id' => $site_id,
                    'error' => $update_result->get_error_message()
                ));
                
                // Add warning to response but don't fail the entire creation
                $response_data['post_options_warning'] = 'Site created successfully, but post-configuration update failed: ' . $update_result->get_error_message();
            } else {
                IWP_Logger::info('Post_options applied successfully to shortcode site', 'shortcode', array(
                    'site_id' => $site_id
                ));
                
                $response_data['post_options_applied'] = true;
            }

        } catch (Exception $e) {
            IWP_Logger::error('Exception during post_options update for shortcode site', 'shortcode', array(
                'site_id' => $site_id,
                'error' => $e->getMessage()
            ));
            
            // Add warning but don't fail the site creation
            $response_data['post_options_warning'] = 'Site created successfully, but post-configuration encountered an error: ' . $e->getMessage();
        }
    }

    /**
     * Handle AJAX task status check request
     */
    public function handle_ajax_check_task_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'iwp_check_task_status')) {
            wp_send_json_error(array('message' => __('Security check failed', 'iwp-wp-integration')));
        }

        // Get task ID
        $task_id = sanitize_text_field($_POST['task_id']);

        if (empty($task_id)) {
            wp_send_json_error(array('message' => __('Task ID is required', 'iwp-wp-integration')));
        }

        try {
            // Get configured API client
            $api_client = IWP_Service::get_api_client();
            if (is_wp_error($api_client)) {
                wp_send_json_error(array('message' => $api_client->get_error_message()));
            }
            
            // Check task status
            $result = $api_client->get_task_status($task_id);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            if (isset($result['data'])) {
                $status_data = $result['data'];
                
                // Map status codes to strings (0=completed, 1=progress, 2=failed)  
                $status_mapping = array(
                    0 => 'completed',
                    1 => 'progress', 
                    2 => 'failed'
                );
                
                // Handle both string and numeric status from task API
                if (isset($status_data['status'])) {
                    if (is_string($status_data['status'])) {
                        // String status from task API (progress, completed, success, failed)
                        $status_lower = strtolower($status_data['status']);
                        $string_status = ($status_lower === 'completed' || $status_lower === 'success') ? 'completed' : 
                                        ($status_lower === 'failed' ? 'failed' : 'progress');
                        $numeric_status = $string_status === 'completed' ? 0 : ($string_status === 'failed' ? 2 : 1);
                    } else {
                        // Numeric status (0=completed, 1=progress, 2=failed)
                        $numeric_status = intval($status_data['status']);
                        $string_status = isset($status_mapping[$numeric_status]) ? $status_mapping[$numeric_status] : 'progress';
                    }
                } else {
                    $numeric_status = 1;
                    $string_status = 'progress';
                }
                
                $response_data = array(
                    'status' => $string_status,
                    'raw_status' => $numeric_status,
                    'message' => isset($status_data['message']) ? $status_data['message'] : ''
                );

                // If task is completed, fetch full site details
                if ($string_status === 'completed' && isset($status_data['resource_id'])) {
                    $site_id = $status_data['resource_id'];
                    IWP_Logger::info('Task completed, fetching site details', 'shortcode', array('site_id' => $site_id));

                    $site_details = $api_client->get_site_details($site_id);
                    if (!is_wp_error($site_details) && isset($site_details['data'])) {
                        $site_info = $site_details['data'];
                        // Site details API uses different field names
                        $response_data['site_url'] = isset($site_info['url']) ? $site_info['url'] : '';
                        $response_data['admin_username'] = isset($site_info['site_meta']['wp_username']) ? $site_info['site_meta']['wp_username'] : '';
                        $response_data['admin_password'] = isset($site_info['site_meta']['wp_password']) ? $site_info['site_meta']['wp_password'] : '';
                        $response_data['s_hash'] = isset($site_info['hash']) ? $site_info['hash'] : '';
                        $response_data['site_id'] = $site_id;
                        $response_data['admin_url'] = isset($site_info['wp_admin_url']) ? $site_info['wp_admin_url'] : '';

                        IWP_Logger::info('Site details fetched successfully', 'shortcode', array('site_url' => $response_data['site_url']));

                        // Update database with full site details
                        $this->update_demo_site_details($site_id, $response_data, $site_details);
                    } else {
                        IWP_Logger::warning('Failed to fetch site details after task completion', 'shortcode', array('site_id' => $site_id));
                    }
                }

                // Don't apply post_options here - let the frontend handle delayed application

                wp_send_json_success($response_data);
            } else {
                wp_send_json_error(array('message' => __('Invalid task status response', 'iwp-wp-integration')));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle AJAX delayed post_options application
     */
    public function handle_ajax_apply_delayed_post_options() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'iwp_apply_delayed_post_options')) {
            wp_send_json_error(array('message' => __('Security check failed', 'iwp-wp-integration')));
        }

        // Get site ID
        $site_id = sanitize_text_field($_POST['site_id']);

        if (empty($site_id)) {
            wp_send_json_error(array('message' => __('Site ID is required', 'iwp-wp-integration')));
        }

        try {
            // Get configured API client
            $api_client = IWP_Service::get_api_client();
            if (is_wp_error($api_client)) {
                wp_send_json_error(array('message' => $api_client->get_error_message()));
            }
            
            // Apply post_options with delay (this runs after 5 second delay from frontend)
            $response_data = array();
            $this->apply_post_options_update($api_client, $site_id, $response_data);

            wp_send_json_success(array(
                'message' => __('Post options applied successfully', 'iwp-wp-integration'),
                'post_options_applied' => isset($response_data['post_options_applied']) ? $response_data['post_options_applied'] : false,
                'warning' => isset($response_data['post_options_warning']) ? $response_data['post_options_warning'] : null
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Generate a username from site name
     *
     * @param string $site_name Site name
     * @return string Generated username
     */
    private function generate_username($site_name) {
        // Convert to lowercase and replace spaces/special chars with underscores
        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $site_name));
        
        // Remove multiple underscores
        $username = preg_replace('/_+/', '_', $username);
        
        // Trim underscores from start and end
        $username = trim($username, '_');
        
        // Ensure it's not empty and not too long
        if (empty($username)) {
            $username = 'admin';
        }
        
        // Limit length to 20 characters
        $username = substr($username, 0, 20);
        
        return $username;
    }

    /**
     * Enqueue scripts and styles for the shortcode
     */
    public function enqueue_scripts() {
        // Always enqueue on frontend pages to ensure scripts are available
        // The shortcode detection might miss dynamic content
        if (!is_admin()) {
            // Enqueue CSS
            wp_enqueue_style(
                'instawp-integration-shortcode-css',
                IWP_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                '1.0.0'
            );
            
            // Enqueue JavaScript
            wp_enqueue_script(
                'instawp-integration-shortcode', 
                IWP_PLUGIN_URL . 'assets/js/shortcode.js', 
                array('jquery'), 
                '1.0.0', 
                true
            );

            wp_localize_script('instawp-integration-shortcode', 'iwp_shortcode_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce_check_status' => wp_create_nonce('iwp_check_task_status'),
                'nonce_apply_post_options' => wp_create_nonce('iwp_apply_delayed_post_options'),
                'messages' => array(
                    'creating' => __('Creating your site...', 'iwp-wp-integration'),
                    'checking_status' => __('Checking site status...', 'iwp-wp-integration'),
                    'finalizing' => __('Finalizing site setup...', 'iwp-wp-integration'),
                    'copy_success' => __('Copied to clipboard!', 'iwp-wp-integration'),
                    'copy_error' => __('Failed to copy', 'iwp-wp-integration'),
                    'show_password' => __('Show', 'iwp-wp-integration'),
                    'hide_password' => __('Hide', 'iwp-wp-integration')
                )
            ));
        }
    }

    /**
     * Check if the current page contains the shortcode
     *
     * @return bool
     */
    private function has_shortcode_on_page() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'iwp_site_creator')) {
            return true;
        }
        
        return false;
    }
}