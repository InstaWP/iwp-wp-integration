<?php
/**
 * Simplified Admin class for IWP Integration
 *
 * @package IWP
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Admin_Simple class - Clean, focused admin management
 */
class IWP_Admin_Simple {

    /**
     * Settings page instance
     *
     * @var IWP_Settings_Page
     */
    private $settings_page;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings_page = new IWP_Settings_Page();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_iwp_refresh_templates', array($this, 'ajax_refresh_templates'));
        add_action('wp_ajax_iwp_refresh_plans', array($this, 'ajax_refresh_plans'));
        add_action('wp_ajax_iwp_create_test_order', array($this, 'ajax_create_test_order'));
        add_action('wp_ajax_iwp_refresh_teams', array($this, 'ajax_refresh_teams'));
        add_action('wp_ajax_iwp_set_team', array($this, 'ajax_set_team'));
        
        // Domain mapping AJAX handlers (for frontend domain mapping)
        add_action('wp_ajax_iwp_add_domain', array($this, 'ajax_add_domain'));
        add_action('wp_ajax_nopriv_iwp_add_domain', array($this, 'ajax_add_domain'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Add top-level InstaWP menu
        add_menu_page(
            esc_html__('InstaWP Sites', 'iwp-wp-integration'),
            esc_html__('InstaWP', 'iwp-wp-integration'),
            'manage_woocommerce',
            'instawp-sites',
            array($this, 'sites_page'),
            $this->get_menu_icon(),
            30
        );

        // Override the default first submenu with "Sites" 
        add_submenu_page(
            'instawp-sites',
            esc_html__('InstaWP Sites', 'iwp-wp-integration'),
            esc_html__('Sites', 'iwp-wp-integration'),
            'manage_woocommerce',
            'instawp-sites',
            array($this, 'sites_page')
        );

        // Add Settings submenu
        add_submenu_page(
            'instawp-sites',
            esc_html__('InstaWP Settings', 'iwp-wp-integration'),
            esc_html__('Settings', 'iwp-wp-integration'),
            'manage_options',
            'instawp-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Get menu icon
     */
    private function get_menu_icon() {
        // InstaWP logo SVG - custom design with lightning bolt for "instant" and blue branding
        $svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.9"/>
            <path d="M13.5 7L9.5 13H11L10.5 17L14.5 11H13L13.5 7Z" fill="white"/>
            <path d="M7 16C7.5 15.5 8 15 8.5 14.5C9 15 9.5 15.5 10 16C10.5 15.5 11 15 11.5 14.5C12 15 12.5 15.5 13 16C13.5 15.5 14 15 14.5 14.5C15 15 15.5 15.5 16 16C16.5 15.5 17 15 17.5 14.5" stroke="white" stroke-width="0.5" fill="none" opacity="0.4"/>
        </svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Sites page callback
     */
    public function sites_page() {
        // Handle row actions first
        $this->handle_sites_page_actions();
        
        // Load the sites list table class
        if (!class_exists('IWP_Sites_List_Table')) {
            require_once IWP_PLUGIN_PATH . 'includes/admin/class-iwp-sites-list-table.php';
        }
        
        // Check if we have an existing sites management class
        if (class_exists('IWP_Sites_List_Table')) {
            // Use the existing sites management functionality
            $sites_table = new IWP_Sites_List_Table();
            $sites_table->prepare_items();
            
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('InstaWP Sites', 'iwp-wp-integration'); ?></h1>
                
                <form method="get">
                    <input type="hidden" name="page" value="instawp-sites" />
                    <?php $sites_table->display(); ?>
                </form>
            </div>
            <?php
        } else {
            // Fallback to a basic sites display
            $this->render_basic_sites_page();
        }
    }
    
    /**
     * Render basic sites page if advanced table not available
     */
    private function render_basic_sites_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('InstaWP Sites', 'iwp-wp-integration'); ?></h1>
            
            <div class="iwp-sites-basic">
                <p><?php esc_html_e('Here are the InstaWP sites from recent orders:', 'iwp-wp-integration'); ?></p>
                
                <?php
                // Get sites from recent completed orders
                $sites = $this->get_sites_from_orders();
                
                if (empty($sites)) {
                    echo '<p>' . esc_html__('No InstaWP sites found. Create some test orders to see sites here.', 'iwp-wp-integration') . '</p>';
                } else {
                    echo '<div class="iwp-sites-grid">';
                    foreach ($sites as $site) {
                        $this->render_site_card($site);
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        
        <style>
        .iwp-sites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .iwp-site-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .iwp-site-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #1d2327;
        }
        
        .iwp-site-info {
            margin-bottom: 15px;
        }
        
        .iwp-site-info strong {
            color: #50575e;
        }
        
        .iwp-site-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .iwp-site-actions .button {
            flex: 1;
            text-align: center;
        }
        
        .iwp-status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .iwp-status-completed { background: #46b450; }
        .iwp-status-progress { background: #ffb900; }
        .iwp-status-failed { background: #dc3232; }
        </style>
        <?php
    }
    
    /**
     * Get sites from WooCommerce orders
     */
    private function get_sites_from_orders() {
        if (!function_exists('wc_get_orders')) {
            return array();
        }
        
        $orders = wc_get_orders(array(
            'limit' => 50,
            'status' => array('completed', 'processing'),
            'meta_query' => array(
                array(
                    'key' => '_iwp_created_sites',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        $sites = array();
        foreach ($orders as $order) {
            $order_sites = $order->get_meta('_iwp_created_sites');
            if (is_array($order_sites)) {
                foreach ($order_sites as $site) {
                    $site['order_id'] = $order->get_id();
                    $site['order_date'] = $order->get_date_created()->format('Y-m-d H:i:s');
                    $sites[] = $site;
                }
            }
        }
        
        return $sites;
    }
    
    /**
     * Render individual site card
     */
    private function render_site_card($site) {
        $status = $site['status'] ?? 'unknown';
        $site_url = $site['wp_url'] ?? '#';
        $admin_url = trailingslashit($site_url) . 'wp-admin';
        $magic_login_url = !empty($site['s_hash']) ? 
            'https://app.instawp.io/wordpress-auto-login?site=' . urlencode($site['s_hash']) : 
            $admin_url;
        
        ?>
        <div class="iwp-site-card">
            <h3>
                <span class="iwp-status-indicator iwp-status-<?php echo esc_attr($status); ?>"></span>
                <?php echo esc_html($site['site_name'] ?? 'InstaWP Site'); ?>
            </h3>
            
            <div class="iwp-site-info">
                <strong><?php esc_html_e('URL:', 'iwp-wp-integration'); ?></strong>
                <a href="<?php echo esc_url($site_url); ?>" target="_blank"><?php echo esc_html($site_url); ?></a>
            </div>
            
            <div class="iwp-site-info">
                <strong><?php esc_html_e('Status:', 'iwp-wp-integration'); ?></strong>
                <?php echo esc_html(ucfirst($status)); ?>
            </div>
            
            <?php if (!empty($site['wp_username'])) : ?>
            <div class="iwp-site-info">
                <strong><?php esc_html_e('Admin Username:', 'iwp-wp-integration'); ?></strong>
                <?php echo esc_html($site['wp_username']); ?>
            </div>
            <?php endif; ?>
            
            <div class="iwp-site-info">
                <strong><?php esc_html_e('Created:', 'iwp-wp-integration'); ?></strong>
                <?php echo esc_html($site['order_date'] ?? 'Unknown'); ?>
            </div>
            
            <div class="iwp-site-info">
                <strong><?php esc_html_e('Order:', 'iwp-wp-integration'); ?></strong>
                <a href="<?php echo esc_url(admin_url('post.php?post=' . $site['order_id'] . '&action=edit')); ?>">
                    #<?php echo esc_html($site['order_id']); ?>
                </a>
            </div>
            
            <div class="iwp-site-actions">
                <a href="<?php echo esc_url($site_url); ?>" class="button button-secondary" target="_blank">
                    <?php esc_html_e('Visit Site', 'iwp-wp-integration'); ?>
                </a>
                <a href="<?php echo esc_url($magic_login_url); ?>" class="button button-primary" target="_blank">
                    <?php echo !empty($site['s_hash']) ? esc_html__('Magic Login', 'iwp-wp-integration') : esc_html__('Admin Login', 'iwp-wp-integration'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Settings page callback
     */
    public function settings_page() {
        $this->settings_page->render();
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('iwp_settings', 'iwp_options', array($this, 'sanitize_settings'));

        // API Settings Section
        add_settings_section(
            'iwp_api',
            esc_html__('InstaWP API Configuration', 'iwp-wp-integration'),
            array($this, 'api_section_callback'),
            'iwp_settings'
        );

        add_settings_field(
            'api_key',
            esc_html__('API Key', 'iwp-wp-integration'),
            array($this, 'api_key_callback'),
            'iwp_settings',
            'iwp_api'
        );

        // General Settings Section
        add_settings_section(
            'iwp_general',
            esc_html__('WooCommerce Integration Settings', 'iwp-wp-integration'),
            array($this, 'general_section_callback'),
            'iwp_settings'
        );

        add_settings_field(
            'enable_integration',
            esc_html__('Enable Integration', 'iwp-wp-integration'),
            array($this, 'checkbox_callback'),
            'iwp_settings',
            'iwp_general',
            array('field' => 'enable_integration', 'label' => 'Enable the InstaWP WooCommerce integration')
        );

        add_settings_field(
            'auto_create_sites',
            esc_html__('Auto-Create Sites', 'iwp-wp-integration'),
            array($this, 'checkbox_callback'),
            'iwp_settings',
            'iwp_general',
            array('field' => 'auto_create_sites', 'label' => 'Automatically create sites when orders are completed')
        );

        add_settings_field(
            'use_site_id_parameter',
            esc_html__('Auto-Convert Demo or Trial Sites', 'iwp-wp-integration'),
            array($this, 'checkbox_callback'),
            'iwp_settings',
            'iwp_general',
            array(
                'field' => 'use_site_id_parameter', 
                'label' => 'Automatically recognize sites for plan change using site_id parameter'
            )
        );
    }

    /**
     * API section callback
     */
    public function api_section_callback() {
        echo '<p>' . esc_html__('Configure your InstaWP API settings below.', 'iwp-wp-integration') . '</p>';
    }

    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . esc_html__('Configure general integration settings.', 'iwp-wp-integration') . '</p>';
        
        // Show subscription integration status
        if (class_exists('WC_Subscriptions') || function_exists('wcs_get_subscription')) {
            echo '<div class="iwp-integration-status" style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 10px; margin: 10px 0;">';
            echo '<strong>✅ ' . esc_html__('WooCommerce Subscriptions Integration Active', 'iwp-wp-integration') . '</strong><br>';
            echo esc_html__('Sites will automatically be marked as temporary when subscriptions are unpaid and permanent when paid.', 'iwp-wp-integration');
            echo '</div>';
        } else {
            echo '<div class="iwp-integration-status" style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; padding: 10px; margin: 10px 0;">';
            echo '<strong>ℹ️ ' . esc_html__('WooCommerce Subscriptions Not Detected', 'iwp-wp-integration') . '</strong><br>';
            echo esc_html__('Install WooCommerce Subscriptions plugin to enable automatic site status management based on payment status.', 'iwp-wp-integration');
            echo '</div>';
        }
    }

    /**
     * API key field callback
     */
    public function api_key_callback() {
        $options = get_option('iwp_options', array());
        $value = isset($options['api_key']) ? $options['api_key'] : '';
        
        printf(
            '<input type="password" id="api_key" name="iwp_options[api_key]" value="%s" class="regular-text" />',
            esc_attr($value)
        );
        
        printf(
            '<p class="description">%s <a href="https://app.instawp.io/user/api-tokens" target="_blank">%s</a></p>',
            esc_html__('Enter your InstaWP API key.', 'iwp-wp-integration'),
            esc_html__('Get your API key here', 'iwp-wp-integration')
        );
    }

    /**
     * Checkbox field callback
     */
    public function checkbox_callback($args) {
        $options = get_option('iwp_options', array());
        $value = isset($options[$args['field']]) ? $options[$args['field']] : 'no';
        $checked = $value === 'yes' ? 'checked="checked"' : '';
        
        printf(
            '<label><input type="checkbox" name="iwp_options[%s]" value="yes" %s /> %s</label>',
            esc_attr($args['field']),
            $checked,
            esc_html($args['label'])
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        // Get existing options to merge with
        $existing = get_option('iwp_options', array());
        $sanitized = $existing; // Start with existing values to preserve settings from other tabs
        
        // Determine which form was submitted based on the fields present
        $is_general_form = isset($input['_form_submitted']) && $input['_form_submitted'] === 'general_settings';
        $is_debug_form = isset($input['_form_submitted']) && $input['_form_submitted'] === 'debug_settings';
        
        // If no form identifier, try to detect based on fields
        if (!$is_general_form && !$is_debug_form) {
            // Check for specific fields to identify the form
            if (isset($input['api_key']) || isset($input['enable_integration']) || isset($input['auto_create_sites'])) {
                $is_general_form = true;
            } elseif (isset($input['debug_mode']) || isset($input['log_level'])) {
                $is_debug_form = true;
            }
        }
        
        // API key - always process if present
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        }
        
        // General form fields - only process if this is the general form
        if ($is_general_form) {
            // Enable integration - checkbox, so absence means 'no'
            $sanitized['enable_integration'] = isset($input['enable_integration']) && $input['enable_integration'] === 'yes' ? 'yes' : 'no';
            
            // Auto-create sites - checkbox, so absence means 'no'
            $sanitized['auto_create_sites'] = isset($input['auto_create_sites']) && $input['auto_create_sites'] === 'yes' ? 'yes' : 'no';
            
            // Use site_id parameter - checkbox, so absence means 'no'
            $sanitized['use_site_id_parameter'] = isset($input['use_site_id_parameter']) && $input['use_site_id_parameter'] === 'yes' ? 'yes' : 'no';
        }
        
        // Debug form fields - only process if this is the debug form
        if ($is_debug_form) {
            // Debug mode - checkbox, so absence means 'no'
            $sanitized['debug_mode'] = isset($input['debug_mode']) && $input['debug_mode'] === 'yes' ? 'yes' : 'no';
            
            // Log level
            if (isset($input['log_level'])) {
                $allowed_levels = array('debug', 'info', 'warning', 'error');
                $sanitized['log_level'] = in_array($input['log_level'], $allowed_levels) ? $input['log_level'] : 'error';
            }
        }
        
        // Always preserve team selection and other settings that aren't in forms
        // This ensures team selection and other AJAX-set values are not lost
        // Get the most current options in case they were updated after page load (e.g., via AJAX)
        $current_options = get_option('iwp_options', array());
        if (isset($current_options['selected_team_id'])) {
            $sanitized['selected_team_id'] = $current_options['selected_team_id'];
        }
        
        return $sanitized;
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'instawp') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        
        // Inline styles for clean tab interface
        wp_add_inline_style('wp-admin', '
            .iwp-admin-tabs { 
                border-bottom: 1px solid #ccd0d4; 
                margin: 20px 0 0 0; 
                padding: 0;
            }
            .iwp-admin-tabs .nav-tab { 
                margin: 0 5px -1px 0; 
                padding: 12px 16px;
                font-weight: 600;
            }
            .iwp-tab-content { 
                display: none; 
                background: #fff;
                border: 1px solid #ccd0d4;
                border-top: none;
                padding: 20px;
            }
            .iwp-tab-content.active { 
                display: block; 
            }
        ');
    }

    /**
     * AJAX: Refresh templates
     */
    public function ajax_refresh_templates() {
        check_ajax_referer('iwp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Create API client with current team selection
        $api_client = new IWP_API_Client();
        
        // Clear team-specific cache
        $api_client->clear_snapshots_cache();
        
        // Try to fetch new data
        $snapshots = $api_client->get_snapshots();
        
        if (is_wp_error($snapshots)) {
            wp_send_json_error('Failed to fetch snapshots: ' . $snapshots->get_error_message());
        }
        
        wp_send_json_success('Snapshots refreshed successfully');
    }

    /**
     * AJAX: Refresh plans
     */
    public function ajax_refresh_plans() {
        check_ajax_referer('iwp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Create API client with current team selection  
        $api_client = new IWP_API_Client();
        
        // Clear team-specific plans cache
        $team_id = $api_client->get_team_id();
        $cache_key = $team_id ? 'iwp_plans_team_' . $team_id : 'iwp_plans';
        delete_transient($cache_key);
        
        // Try to fetch new data
        $plans = $api_client->get_plans();
        
        if (is_wp_error($plans)) {
            wp_send_json_error('Failed to fetch plans: ' . $plans->get_error_message());
        }
        
        wp_send_json_success('Plans refreshed successfully');
    }

    /**
     * AJAX: Create test order
     */
    public function ajax_create_test_order() {
        check_ajax_referer('iwp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $product_id = intval($_POST['product_id'] ?? 0);
        
        if (!$product_id) {
            wp_send_json_error('Please select a product');
        }

        // Check if WooCommerce is active
        if (!class_exists('WC_Order')) {
            wp_send_json_error('WooCommerce is required for test orders');
        }

        try {
            // Get the current logged-in user
            $current_user = wp_get_current_user();
            
            // Create a new order
            $order = wc_create_order();
            
            if (is_wp_error($order)) {
                wp_send_json_error('Failed to create order: ' . $order->get_error_message());
            }

            // Add the product to the order
            $product = wc_get_product($product_id);
            if (!$product) {
                wp_send_json_error('Invalid product');
            }

            $order->add_product($product, 1);

            // Set the current user as the customer
            $order->set_customer_id($current_user->ID);
            
            // Use the current user's information for billing
            $order->set_billing_first_name($current_user->first_name ?: $current_user->display_name);
            $order->set_billing_last_name($current_user->last_name ?: 'User');
            $order->set_billing_email($current_user->user_email);
            
            // Set default address if user doesn't have billing address stored
            $billing_address_1 = get_user_meta($current_user->ID, 'billing_address_1', true);
            $billing_city = get_user_meta($current_user->ID, 'billing_city', true);
            $billing_country = get_user_meta($current_user->ID, 'billing_country', true);
            $billing_postcode = get_user_meta($current_user->ID, 'billing_postcode', true);
            
            $order->set_billing_address_1($billing_address_1 ?: '123 Test Street');
            $order->set_billing_city($billing_city ?: 'Test City');
            $order->set_billing_country($billing_country ?: 'US');
            $order->set_billing_postcode($billing_postcode ?: '12345');

            // Calculate totals
            $order->calculate_totals();

            // Add order note
            $order->add_order_note(sprintf(
                'Test order created from InstaWP Integration admin panel by %s',
                $current_user->display_name
            ));

            // Get snapshot info for the product
            $snapshot_slug = get_post_meta($product_id, '_iwp_selected_snapshot', true);
            $plan_id = get_post_meta($product_id, '_iwp_selected_plan', true);

            // Add order meta for InstaWP
            if ($snapshot_slug) {
                $order->update_meta_data('_iwp_snapshot_slug', $snapshot_slug);
            }
            if ($plan_id) {
                $order->update_meta_data('_iwp_plan_id', $plan_id);
            }

            // Save the order
            $order->save();

            // Mark as completed to trigger site creation
            $order->update_status('completed', 'Test order auto-completed for site creation');

            // Get the order edit URL
            $order_edit_url = admin_url('post.php?post=' . $order->get_id() . '&action=edit');
            
            // Get My Account orders URL for the customer
            $my_account_url = wc_get_account_endpoint_url('orders');

            wp_send_json_success(array(
                'message' => sprintf('Test order created successfully for %s!', $current_user->display_name),
                'order_id' => $order->get_id(),
                'order_edit_url' => $order_edit_url,
                'my_account_url' => $my_account_url,
                'customer_name' => $current_user->display_name,
                'customer_email' => $current_user->user_email,
                'snapshot_slug' => $snapshot_slug ?: 'No snapshot selected',
                'plan_id' => $plan_id ?: 'No plan selected'
            ));

        } catch (Exception $e) {
            wp_send_json_error('Error creating test order: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Refresh teams
     */
    public function ajax_refresh_teams() {
        check_ajax_referer('iwp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Clear cache
        delete_transient('iwp_teams');
        
        // Try to fetch new data
        require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-service.php';
        $teams = IWP_Service::refresh_teams();
        
        if (is_wp_error($teams)) {
            wp_send_json_error('Failed to fetch teams: ' . $teams->get_error_message());
        }
        
        wp_send_json_success('Teams refreshed successfully');
    }

    /**
     * AJAX: Set selected team
     */
    public function ajax_set_team() {
        check_ajax_referer('iwp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $team_id = isset($_POST['team_id']) && !empty($_POST['team_id']) ? intval($_POST['team_id']) : null;
        
        require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-service.php';
        $result = IWP_Service::set_selected_team($team_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Team selection updated successfully',
                'team_id' => $team_id
            ));
        } else {
            wp_send_json_error('Failed to update team selection');
        }
    }
    
    /**
     * AJAX: Add domain to site
     */
    public function ajax_add_domain() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iwp_add_domain_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed', 'iwp-wp-integration')
            ));
        }

        // Check if user is logged in (can be guest if they have valid session)
        $order_id = intval($_POST['order_id'] ?? 0);
        $site_id = intval($_POST['site_id'] ?? 0);
        $domain_name = sanitize_text_field($_POST['domain_name'] ?? '');
        $domain_type = sanitize_text_field($_POST['domain_type'] ?? 'primary');

        if (!$order_id || !$site_id || !$domain_name) {
            wp_send_json_error(array(
                'message' => __('Missing required information', 'iwp-wp-integration')
            ));
        }

        // Validate domain format
        if (!filter_var('http://' . $domain_name, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array(
                'message' => __('Invalid domain format', 'iwp-wp-integration')
            ));
        }

        // Check if user can view this order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array(
                'message' => __('Order not found', 'iwp-wp-integration')
            ));
        }

        // For logged-in users, check if they own the order
        if (is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            $order_customer_id = $order->get_customer_id();
            
            if ($current_user_id != $order_customer_id) {
                wp_send_json_error(array(
                    'message' => __('Access denied', 'iwp-wp-integration')
                ));
            }
        }

        // Get API client and add domain
        require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-api-client.php';
        $api_client = new IWP_API_Client();
        $options = get_option('iwp_options', array());
        $api_key = $options['api_key'] ?? '';
        
        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => __('API key not configured', 'iwp-wp-integration')
            ));
        }

        $api_client->set_api_key($api_key);
        $result = $api_client->add_domain_to_site($site_id, $domain_name, $domain_type);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }

        // Store domain mapping in order meta for future reference
        $domain_info = array(
            'site_id' => $site_id,
            'domain_name' => $domain_name,
            'domain_type' => $domain_type,
            'mapped_at' => current_time('mysql'),
            'api_response' => $result
        );

        // Use the database helper to append domain info
        require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-database.php';
        IWP_Database::append_order_meta($order_id, '_iwp_mapped_domains', $domain_info);

        // Update site URL in database with mapped domain (for primary domains)
        if ($domain_type === 'primary') {
            $this->update_site_url_with_mapped_domain($order_id, $site_id, $domain_name);
        }

        // Add order note
        $order->add_order_note(
            sprintf(
                __('Custom domain "%s" mapped to InstaWP site (ID: %s) as %s', 'iwp-wp-integration'),
                $domain_name,
                $site_id,
                $domain_type
            ),
            1 // Customer visible
        );

        wp_send_json_success(array(
            'message' => sprintf(
                __('Domain "%s" successfully mapped to your site!', 'iwp-wp-integration'),
                $domain_name
            ),
            'domain_info' => $domain_info
        ));
    }
    
    /**
     * Update site URL with mapped domain in all relevant database locations
     *
     * @param int $order_id
     * @param int $site_id
     * @param string $domain_name
     */
    private function update_site_url_with_mapped_domain($order_id, $site_id, $domain_name) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Update created sites meta
        $created_sites = $order->get_meta('_iwp_created_sites');
        if (is_array($created_sites)) {
            foreach ($created_sites as $index => $site) {
                if (isset($site['site_id']) && intval($site['site_id']) === $site_id) {
                    $created_sites[$index]['original_wp_url'] = $site['wp_url'] ?? '';
                    $created_sites[$index]['wp_url'] = 'https://' . $domain_name;
                    break;
                }
            }
            $order->update_meta_data('_iwp_created_sites', $created_sites);
            $order->save();
        }

        // Update sites database if using IWP_Site_Manager
        if (class_exists('IWP_Site_Manager')) {
            require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-site-manager.php';
            $site_manager = new IWP_Site_Manager();
            $site_manager->update_site_url($site_id, 'https://' . $domain_name);
        }
    }

    /**
     * Handle sites page actions (delete, etc.)
     */
    private function handle_sites_page_actions() {
        if (!isset($_GET['action']) || !isset($_GET['site_id'])) {
            return;
        }

        $action = sanitize_text_field($_GET['action']);
        $site_id = sanitize_text_field($_GET['site_id']);

        if ($action === 'delete') {
            // Verify nonce
            if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_site_' . $site_id)) {
                wp_die(__('Security check failed', 'iwp-wp-integration'));
            }

            // Check permissions
            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('You do not have sufficient permissions to perform this action.', 'iwp-wp-integration'));
            }

            // Delete the site
            try {
                // Get API client manually with proper configuration
                $options = get_option('iwp_options', array());
                $api_key = $options['api_key'] ?? '';
                
                if (!empty($api_key)) {
                    $api_client = new IWP_API_Client();
                    $api_client->set_api_key($api_key);
                    $api_client->delete_site($site_id);
                }

                // Remove from database
                IWP_Sites_Model::delete($site_id);

                // Log the deletion
                IWP_Logger::info('Site deleted via row action', 'admin', array(
                    'site_id' => $site_id,
                    'user_id' => get_current_user_id()
                ));

                // Show success message and redirect
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         esc_html__('Site deleted successfully.', 'iwp-wp-integration') . 
                         '</p></div>';
                });

            } catch (Exception $e) {
                // Show error message
                add_action('admin_notices', function() use ($e) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . 
                         sprintf(esc_html__('Failed to delete site: %s', 'iwp-wp-integration'), esc_html($e->getMessage())) . 
                         '</p></div>';
                });
            }
        }
    }
}