<?php
/**
 * IWP Woo V2 Shortcode Class
 *
 * Handles the iwp_site_creator shortcode functionality
 *
 * @package IWP_Woo_V2
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class IWP_Woo_V2_Shortcode {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('iwp_site_creator', array($this, 'render_shortcode'));
        add_action('wp_ajax_iwp_create_site_shortcode', array($this, 'handle_ajax_site_creation'));
        add_action('wp_ajax_nopriv_iwp_create_site_shortcode', array($this, 'handle_ajax_site_creation'));
        add_action('wp_ajax_iwp_check_task_status', array($this, 'handle_ajax_check_task_status'));
        add_action('wp_ajax_nopriv_iwp_check_task_status', array($this, 'handle_ajax_check_task_status'));
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
                        <?php esc_html_e('Name:', 'instawp-integration'); ?>
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
                        <?php esc_html_e('Email:', 'instawp-integration'); ?>
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
                        <?php esc_html_e('Create Site', 'instawp-integration'); ?>
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
                        <h3 class="iwp-site-creator-success-title"><?php esc_html_e('Site Created Successfully!', 'instawp-integration'); ?></h3>
                        <div class="iwp-site-creator-site-details">
                            <div class="iwp-site-creator-detail">
                                <span class="iwp-site-creator-detail-label"><?php esc_html_e('Site URL:', 'instawp-integration'); ?></span>
                                <a class="iwp-site-creator-site-url" href="#" target="_blank"></a>
                            </div>
                            <div class="iwp-site-creator-detail">
                                <span class="iwp-site-creator-detail-label"><?php esc_html_e('Admin Username:', 'instawp-integration'); ?></span>
                                <span class="iwp-site-creator-username"></span>
                                <button type="button" class="iwp-site-creator-copy-btn" data-copy="username">
                                    <?php esc_html_e('Copy', 'instawp-integration'); ?>
                                </button>
                            </div>
                            <div class="iwp-site-creator-detail">
                                <span class="iwp-site-creator-detail-label"><?php esc_html_e('Admin Password:', 'instawp-integration'); ?></span>
                                <span class="iwp-site-creator-password" style="display: none;"></span>
                                <span class="iwp-site-creator-password-hidden">••••••••</span>
                                <button type="button" class="iwp-site-creator-toggle-password">
                                    <?php esc_html_e('Show', 'instawp-integration'); ?>
                                </button>
                                <button type="button" class="iwp-site-creator-copy-btn" data-copy="password">
                                    <?php esc_html_e('Copy', 'instawp-integration'); ?>
                                </button>
                            </div>
                            <div class="iwp-site-creator-detail">
                                <span class="iwp-site-creator-detail-label"><?php esc_html_e('Admin Login:', 'instawp-integration'); ?></span>
                                <a class="iwp-site-creator-admin-url" href="#" target="_blank">
                                    <?php esc_html_e('Login to Admin', 'instawp-integration'); ?>
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
            wp_send_json_error(array('message' => __('Security check failed', 'instawp-integration')));
        }

        // Sanitize input data
        $snapshot_slug = sanitize_text_field($_POST['snapshot_slug']);
        $site_name = sanitize_text_field($_POST['site_name']);
        $site_email = sanitize_email($_POST['site_email']);
        $expiry_hours = !empty($_POST['expiry_hours']) ? intval($_POST['expiry_hours']) : '';
        $sandbox = !empty($_POST['sandbox']) ? sanitize_text_field($_POST['sandbox']) : '';

        // Validate required fields
        if (empty($snapshot_slug) || empty($site_name) || empty($site_email)) {
            wp_send_json_error(array('message' => __('All fields are required', 'instawp-integration')));
        }

        if (!is_email($site_email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address', 'instawp-integration')));
        }

        try {
            // Initialize API client
            $api_client = new IWP_Woo_V2_API_Client();
            
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
                
                // Prepare response data
                $response_data = array(
                    'site_url' => isset($site_info['wp_url']) ? $site_info['wp_url'] : '',
                    'admin_username' => $site_data['wp_username'],
                    'admin_password' => $site_data['wp_password'],
                    'admin_url' => isset($site_info['wp_url']) ? trailingslashit($site_info['wp_url']) . 'wp-admin' : '',
                    'status' => isset($site_info['status']) ? $site_info['status'] : 'pending',
                    'task_id' => isset($site_info['task_id']) ? $site_info['task_id'] : null,
                    'is_pool' => isset($site_info['is_pool']) ? $site_info['is_pool'] : false
                );

                // If it's a pool site or has wp_url, it's ready
                if ($response_data['is_pool'] || !empty($response_data['site_url'])) {
                    $response_data['status'] = 'completed';
                    wp_send_json_success($response_data);
                } elseif (!empty($response_data['task_id'])) {
                    // Site is being created, return task ID for status checking
                    $response_data['status'] = 'in_progress';
                    wp_send_json_success($response_data);
                } else {
                    wp_send_json_error(array('message' => __('Site creation failed: No task ID or site URL returned', 'instawp-integration')));
                }
            } else {
                wp_send_json_error(array('message' => __('Site creation failed: Invalid API response', 'instawp-integration')));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle AJAX task status check request
     */
    public function handle_ajax_check_task_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'iwp_check_task_status')) {
            wp_send_json_error(array('message' => __('Security check failed', 'instawp-integration')));
        }

        // Get task ID
        $task_id = sanitize_text_field($_POST['task_id']);

        if (empty($task_id)) {
            wp_send_json_error(array('message' => __('Task ID is required', 'instawp-integration')));
        }

        try {
            // Initialize API client
            $api_client = new IWP_Woo_V2_API_Client();
            
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
                    1 => 'in_progress', 
                    2 => 'failed'
                );
                
                $numeric_status = isset($status_data['status']) ? intval($status_data['status']) : 1;
                $string_status = isset($status_mapping[$numeric_status]) ? $status_mapping[$numeric_status] : 'in_progress';
                
                $response_data = array(
                    'status' => $string_status,
                    'raw_status' => $numeric_status,
                    'site_url' => isset($status_data['wp_url']) ? $status_data['wp_url'] : '',
                    'message' => isset($status_data['message']) ? $status_data['message'] : ''
                );

                wp_send_json_success($response_data);
            } else {
                wp_send_json_error(array('message' => __('Invalid task status response', 'instawp-integration')));
            }

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
        // Only enqueue on pages that contain the shortcode
        if ($this->has_shortcode_on_page()) {
            wp_enqueue_script(
                'instawp-integration-shortcode', 
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/shortcode.js', 
                array('jquery'), 
                '1.0.0', 
                true
            );

            wp_localize_script('instawp-integration-shortcode', 'iwp_shortcode_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce_check_status' => wp_create_nonce('iwp_check_task_status'),
                'messages' => array(
                    'creating' => __('Creating your site...', 'instawp-integration'),
                    'checking_status' => __('Checking site status...', 'instawp-integration'),
                    'copy_success' => __('Copied to clipboard!', 'instawp-integration'),
                    'copy_error' => __('Failed to copy', 'instawp-integration'),
                    'show_password' => __('Show', 'instawp-integration'),
                    'hide_password' => __('Hide', 'instawp-integration')
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