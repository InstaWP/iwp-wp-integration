<?php
/**
 * Admin class for IWP WooCommerce Integration v2
 *
 * @package IWP
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Admin class
 */
class IWP_Admin {

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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_filter('plugin_action_links_' . IWP_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        add_action('wp_ajax_iwp_refresh_templates', array($this, 'ajax_refresh_templates'));
        add_action('wp_ajax_iwp_refresh_product_templates', array($this, 'ajax_refresh_product_templates'));
        add_action('wp_ajax_iwp_get_template_preview', array($this, 'ajax_get_template_preview'));
        add_action('wp_ajax_iwp_refresh_plans', array($this, 'ajax_refresh_plans'));
        add_action('wp_ajax_iwp_refresh_product_plans', array($this, 'ajax_refresh_product_plans'));
        add_action('wp_ajax_iwp_clear_transients', array($this, 'ajax_clear_transients'));
        add_action('wp_ajax_iwp_warm_cache', array($this, 'ajax_warm_cache'));
        add_action('wp_ajax_iwp_create_test_order', array($this, 'ajax_create_test_order'));
        add_action('wp_ajax_iwp_setup_sites', array($this, 'ajax_setup_sites'));
        
        // Frontend AJAX handlers (for customers)
        add_action('wp_ajax_iwp_add_domain', array($this, 'ajax_add_domain'));
        add_action('wp_ajax_nopriv_iwp_add_domain', array($this, 'ajax_add_domain'));
        
        // Sites management AJAX handlers
        add_action('wp_ajax_iwp_delete_site', array($this, 'ajax_delete_site'));
        
        // Testing AJAX handlers
        add_action('wp_ajax_iwp_test_create_site', array($this, 'ajax_test_create_site'));
        add_action('wp_ajax_iwp_test_check_status', array($this, 'ajax_test_check_status'));
        add_action('wp_ajax_iwp_check_pending_sites', array($this, 'ajax_force_cron_check'));
        add_action('wp_ajax_iwp_test_site_update', array($this, 'ajax_test_site_update'));
    }

    /**
     * Get InstaWP menu icon
     *
     * @return string Base64 encoded SVG data URI or URL
     */
    private function get_instawp_menu_icon() {
        // InstaWP logo SVG - custom design with lightning bolt for "instant" and blue branding
        $svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.9"/>
            <path d="M13.5 7L9.5 13H11L10.5 17L14.5 11H13L13.5 7Z" fill="white"/>
            <path d="M7 16C7.5 15.5 8 15 8.5 14.5C9 15 9.5 15.5 10 16C10.5 15.5 11 15 11.5 14.5C12 15 12.5 15.5 13 16C13.5 15.5 14 15 14.5 14.5C15 15 15.5 15.5 16 16C16.5 15.5 17 15 17.5 14.5" stroke="white" stroke-width="0.5" fill="none" opacity="0.4"/>
        </svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
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
            $this->get_instawp_menu_icon(),
            30
        );

        // Rename the first submenu item to "Sites" instead of "InstaWP"
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
            array($this, 'admin_page')
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            'iwp_settings',
            'iwp_options',
            array($this, 'validate_settings')
        );

        add_settings_section(
            'iwp_api',
            esc_html__('InstaWP API Settings', 'iwp-wp-integration'),
            array($this, 'api_settings_callback'),
            'iwp_settings'
        );

        add_settings_field(
            'api_key',
            esc_html__('API Key', 'iwp-wp-integration'),
            array($this, 'text_field_callback'),
            'iwp_settings',
            'iwp_api',
            array(
                'label_for' => 'api_key',
                'type' => 'password',
                'description' => sprintf(
                    esc_html__('Enter your API key for authentication. %sGet your API key here%s', 'iwp-wp-integration'),
                    '<a href="https://app.instawp.io/user/api-tokens" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                )
            )
        );

        add_settings_section(
            'iwp_general',
            esc_html__('General Settings', 'iwp-wp-integration'),
            array($this, 'general_settings_callback'),
            'iwp_settings'
        );

        add_settings_field(
            'enabled',
            esc_html__('Enable Integration', 'iwp-wp-integration'),
            array($this, 'checkbox_field_callback'),
            'iwp_settings',
            'iwp_general',
            array(
                'label_for' => 'enabled',
                'description' => esc_html__('Enable the InstaWP WooCommerce integration.', 'iwp-wp-integration')
            )
        );

        add_settings_field(
            'debug_mode',
            esc_html__('Debug Mode', 'iwp-wp-integration'),
            array($this, 'checkbox_field_callback'),
            'iwp_settings',
            'iwp_general',
            array(
                'label_for' => 'debug_mode',
                'description' => esc_html__('Enable debug mode for troubleshooting.', 'iwp-wp-integration')
            )
        );

        add_settings_field(
            'log_level',
            esc_html__('Log Level', 'iwp-wp-integration'),
            array($this, 'select_field_callback'),
            'iwp_settings',
            'iwp_general',
            array(
                'label_for' => 'log_level',
                'options' => array(
                    'debug' => esc_html__('Debug', 'iwp-wp-integration'),
                    'info' => esc_html__('Info', 'iwp-wp-integration'),
                    'warning' => esc_html__('Warning', 'iwp-wp-integration'),
                    'error' => esc_html__('Error', 'iwp-wp-integration'),
                ),
                'description' => esc_html__('Select the log level for the plugin.', 'iwp-wp-integration')
            )
        );

        add_settings_field(
            'auto_create_sites_on_purchase',
            esc_html__('Auto-Create Sites on Purchase', 'iwp-wp-integration'),
            array($this, 'checkbox_field_callback'),
            'iwp_settings',
            'iwp_general',
            array(
                'label_for' => 'auto_create_sites_on_purchase',
                'description' => esc_html__('Automatically create InstaWP sites when orders are completed. When disabled, admins will see a "Setup Site" button in order details to manually create sites.', 'iwp-wp-integration')
            )
        );

        add_settings_field(
            'use_site_id_parameter',
            esc_html__('Use site_id Parameter', 'iwp-wp-integration'),
            array($this, 'checkbox_field_callback'),
            'iwp_settings',
            'iwp_general',
            array(
                'label_for' => 'use_site_id_parameter',
                'description' => esc_html__('Use site_id when provided to change plan instead of creating a new site. When enabled, customers can visit shop with ?site_id=123 to upgrade an existing site instead of creating new one.', 'iwp-wp-integration')
            )
        );

        add_settings_section(
            'iwp_snapshots',
            esc_html__('InstaWP Snapshots', 'iwp-wp-integration'),
            array($this, 'snapshots_section_callback'),
            'iwp_settings'
        );

        add_settings_section(
            'iwp_plans',
            esc_html__('InstaWP Plans', 'iwp-wp-integration'),
            array($this, 'plans_section_callback'),
            'iwp_settings'
        );

        add_settings_section(
            'iwp_cache',
            esc_html__('Cache Management', 'iwp-wp-integration'),
            array($this, 'cache_section_callback'),
            'iwp_settings'
        );

        add_settings_section(
            'iwp_shortcode',
            esc_html__('Shortcode Documentation', 'iwp-wp-integration'),
            array($this, 'shortcode_section_callback'),
            'iwp_settings'
        );
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook The current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        // Check for the correct admin page hooks
        $allowed_hooks = array(
            'toplevel_page_instawp-sites',
            'instawp_page_instawp-settings', 
            'tools_page_instawp-integration', // Legacy fallback
        );
        
        // Debug: log the current hook to help troubleshoot
        error_log('IWP Admin Scripts - Current hook: ' . $hook);
        
        // Allow scripts on any InstaWP page or if it contains 'instawp'
        if (!in_array($hook, $allowed_hooks) && strpos($hook, 'instawp') === false) {
            return;
        }

        wp_enqueue_script(
            'instawp-integration-admin',
            IWP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            IWP_VERSION . '.' . time(), // Cache busting with timestamp
            true
        );

        wp_enqueue_style(
            'instawp-integration-admin',
            IWP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            IWP_VERSION . '.' . time() // Cache busting with timestamp
        );

        wp_localize_script(
            'instawp-integration-admin',
            'iwp_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('iwp_admin_nonce'),
                'orders_url' => admin_url('edit.php?post_type=shop_order'),
                'site_url' => home_url(),
                'strings' => array(
                    'confirm_reset' => esc_html__('Are you sure you want to reset all settings?', 'iwp-wp-integration'),
                    'settings_saved' => esc_html__('Settings saved successfully.', 'iwp-wp-integration'),
                    'error_occurred' => esc_html__('An error occurred. Please try again.', 'iwp-wp-integration'),
                ),
            )
        );
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        $screen = get_current_screen();
        
        if ($screen->id !== 'instawp_page_instawp-settings' && $screen->id !== 'toplevel_page_instawp-sites') {
            return;
        }

        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Settings saved successfully.', 'iwp-wp-integration'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing action links
     * @return array
     */
    public function plugin_action_links($links) {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=instawp-settings') . '">' . esc_html__('Settings', 'iwp-wp-integration') . '</a>',
        );

        return array_merge($action_links, $links);
    }

    /**
     * Add plugin row meta
     *
     * @param array $links Existing meta links
     * @param string $file Plugin file
     * @return array
     */
    public function plugin_row_meta($links, $file) {
        if (IWP_PLUGIN_BASENAME === $file) {
            $meta_links = array(
                'docs' => '<a href="https://instawp.com/docs" target="_blank">' . esc_html__('Documentation', 'iwp-wp-integration') . '</a>',
                'support' => '<a href="https://instawp.com/support" target="_blank">' . esc_html__('Support', 'iwp-wp-integration') . '</a>',
            );

            $links = array_merge($links, $meta_links);
        }

        return $links;
    }

    /**
     * Admin page callback
     */
    public function admin_page() {
        ?>
        <style>
        /* Emergency CSS fallback */
        .iwp-admin-tabs { 
            border-bottom: 1px solid #ccd0d4; 
            margin-bottom: 0;
        }
        .iwp-admin-tabs .nav-tab { 
            display: inline-block; 
            padding: 12px 16px; 
            margin-right: 5px;
            text-decoration: none;
            border: 1px solid #ccd0d4;
            background: #f1f1f1;
            color: #50575e;
        }
        .iwp-admin-tabs .nav-tab.nav-tab-active,
        .iwp-admin-tabs .nav-tab:first-child { 
            background: #fff; 
            border-bottom-color: #fff;
            color: #000;
        }
        .iwp-tab-content { 
            display: none; 
            border: 1px solid #ccd0d4; 
            border-top: none; 
            padding: 20px; 
            background: #fff;
        }
        .iwp-tab-content.active,
        .iwp-tab-content:first-of-type { 
            display: block; 
        }
        </style>
        
        <div class="wrap">
            <h1><?php esc_html_e('InstaWP Integration Settings', 'iwp-wp-integration'); ?></h1>
            
            <!-- Tab Navigation -->
            <div class="iwp-admin-tabs">
                <a href="#general-settings" class="nav-tab nav-tab-active"><?php esc_html_e('General Settings', 'iwp-wp-integration'); ?></a>
                <a href="#instawp-data" class="nav-tab"><?php esc_html_e('InstaWP Data', 'iwp-wp-integration'); ?></a>
                <a href="#testing" class="nav-tab"><?php esc_html_e('Testing & Development', 'iwp-wp-integration'); ?></a>
                <a href="#documentation" class="nav-tab"><?php esc_html_e('Documentation', 'iwp-wp-integration'); ?></a>
            </div>
            
            <!-- Tab 1: General Settings -->
            <div id="general-settings" class="iwp-tab-content active">
                <?php $this->render_general_settings_tab(); ?>
            </div>
            
            <!-- Tab 2: InstaWP Data -->
            <div id="instawp-data" class="iwp-tab-content">
                <?php $this->render_instawp_data_tab(); ?>
            </div>
            
            <!-- Tab 3: Testing & Development -->
            <div id="testing" class="iwp-tab-content">
                <?php $this->render_testing_tab(); ?>
            </div>
            
            <!-- Tab 4: Documentation -->
            <div id="documentation" class="iwp-tab-content">
                <?php $this->render_documentation_tab(); ?>
            </div>
            
        </div>
        
        <script>
        // Emergency JavaScript fallback
        jQuery(document).ready(function($) {
            $('.iwp-admin-tabs .nav-tab').on('click', function(e) {
                e.preventDefault();
                var tabId = $(this).attr('href').substring(1);
                
                // Update active tab
                $('.iwp-admin-tabs .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Update active content
                $('.iwp-tab-content').removeClass('active').hide();
                $('#' + tabId).addClass('active').show();
                
                // Save to localStorage
                localStorage.setItem('iwp_active_tab', tabId);
            });
            
            // Restore saved tab
            var savedTab = localStorage.getItem('iwp_active_tab');
            if (savedTab && $('#' + savedTab).length) {
                $('.iwp-admin-tabs .nav-tab[href="#' + savedTab + '"]').click();
            }
        });
        </script>
        <?php
    }

    /**
     * Render General Settings tab content
     */
    private function render_general_settings_tab() {
        ?>
        <form method="post" action="options.php" id="iwp-settings-form">
            <?php
            settings_fields('iwp_settings');
            
            // API Settings Section
            echo '<div class="tab-section">';
            echo '<h3>' . esc_html__('InstaWP API Configuration', 'iwp-wp-integration') . '</h3>';
            $this->render_settings_section('iwp_settings', 'iwp_api');
            echo '</div>';
            
            // General Settings Section  
            echo '<div class="tab-section">';
            echo '<h3>' . esc_html__('Integration Settings', 'iwp-wp-integration') . '</h3>';
            $this->render_settings_section('iwp_settings', 'iwp_general');
            echo '</div>';
            
            submit_button(__('Save Settings', 'iwp-wp-integration'), 'primary', 'submit', false);
            ?>
        </form>
        <?php
    }

    /**
     * Render InstaWP Data tab content
     */
    private function render_instawp_data_tab() {
        ?>
        <div class="tab-section">
            <h3><?php esc_html_e('Cached Data Management', 'iwp-wp-integration'); ?></h3>
            <p class="description"><?php esc_html_e('The sections below display cached data from InstaWP API. Use the refresh buttons to update the data.', 'iwp-wp-integration'); ?></p>
            
            <div class="form-section">
                <h4><?php esc_html_e('InstaWP Snapshots', 'iwp-wp-integration'); ?></h4>
                <div id="iwp-snapshots-list">
                    <?php $this->render_settings_section('iwp_settings', 'iwp_snapshots'); ?>
                </div>
            </div>
            
            <div class="form-section">
                <h4><?php esc_html_e('InstaWP Plans', 'iwp-wp-integration'); ?></h4>
                <div id="iwp-plans-list">
                    <?php $this->render_settings_section('iwp_settings', 'iwp_plans'); ?>
                </div>
            </div>
            
            <div class="form-section">
                <h4><?php esc_html_e('Cache Management', 'iwp-wp-integration'); ?></h4>
                <?php $this->render_settings_section('iwp_settings', 'iwp_cache'); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Testing & Development tab content
     */
    private function render_testing_tab() {
        ?>
        <div class="tab-section">
            <h3><?php esc_html_e('Development Tools', 'iwp-wp-integration'); ?></h3>
            <p class="description"><?php esc_html_e('Tools for testing and debugging InstaWP integration functionality.', 'iwp-wp-integration'); ?></p>
            
            <?php if (class_exists('WooCommerce')) : ?>
                <div class="form-section">
                    <h4><?php esc_html_e('Test Order Creation', 'iwp-wp-integration'); ?></h4>
                    <p class="description"><?php esc_html_e('Create test orders to verify InstaWP site creation functionality without manual checkout.', 'iwp-wp-integration'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Select Product', 'iwp-wp-integration'); ?></th>
                            <td>
                                <select id="iwp-test-product-select" style="min-width: 300px;">
                                    <option value=""><?php esc_html_e('Choose a product...', 'iwp-wp-integration'); ?></option>
                                    <?php
                                    // Get all products with InstaWP snapshots configured
                                    $products = get_posts(array(
                                        'post_type' => 'product',
                                        'post_status' => 'publish',
                                        'numberposts' => -1,
                                        'meta_query' => array(
                                            array(
                                                'key' => '_iwp_selected_snapshot',
                                                'compare' => 'EXISTS'
                                            )
                                        )
                                    ));
                                    
                                    foreach ($products as $product_post) {
                                        $product = wc_get_product($product_post->ID);
                                        if ($product) {
                                            $snapshot_slug = get_post_meta($product_post->ID, '_iwp_selected_snapshot', true);
                                            if (!empty($snapshot_slug)) {
                                                echo '<option value="' . esc_attr($product_post->ID) . '" data-snapshot="' . esc_attr($snapshot_slug) . '">';
                                                echo esc_html($product->get_name()) . ' (Snapshot: ' . esc_html($snapshot_slug) . ')';
                                                echo '</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e('Only products with InstaWP snapshots configured are shown.', 'iwp-wp-integration'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Customer', 'iwp-wp-integration'); ?></th>
                            <td>
                                <div style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="radio" name="iwp-customer-type" value="existing" checked style="margin-right: 5px;" />
                                        <?php esc_html_e('Use Existing User', 'iwp-wp-integration'); ?>
                                    </label>
                                    <select id="iwp-test-customer-select" style="min-width: 300px;">
                                        <option value=""><?php esc_html_e('Choose a user...', 'iwp-wp-integration'); ?></option>
                                        <?php
                                        // Get all users
                                        $users = get_users(array(
                                            'number' => 50, // Limit for performance
                                            'orderby' => 'display_name',
                                            'order' => 'ASC'
                                        ));
                                        
                                        foreach ($users as $user) {
                                            $user_info = sprintf(
                                                '%s (%s) - %s',
                                                $user->display_name,
                                                $user->user_login,
                                                $user->user_email
                                            );
                                            echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user_info) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="radio" name="iwp-customer-type" value="guest" style="margin-right: 5px;" />
                                        <?php esc_html_e('Guest Checkout', 'iwp-wp-integration'); ?>
                                    </label>
                                    <div id="iwp-guest-details" style="margin-left: 20px; display: none;">
                                        <input type="text" id="iwp-test-customer-first-name" placeholder="<?php esc_attr_e('First Name', 'iwp-wp-integration'); ?>" value="Test" style="width: 150px; margin-right: 10px;" />
                                        <input type="text" id="iwp-test-customer-last-name" placeholder="<?php esc_attr_e('Last Name', 'iwp-wp-integration'); ?>" value="Customer" style="width: 150px; margin-right: 10px;" />
                                        <input type="email" id="iwp-test-customer-email" placeholder="<?php esc_attr_e('Email', 'iwp-wp-integration'); ?>" value="test@example.com" style="width: 200px;" />
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="radio" name="iwp-customer-type" value="new" style="margin-right: 5px;" />
                                        <?php esc_html_e('Create New User', 'iwp-wp-integration'); ?>
                                    </label>
                                    <div id="iwp-new-user-details" style="margin-left: 20px; display: none;">
                                        <input type="text" id="iwp-new-user-username" placeholder="<?php esc_attr_e('Username', 'iwp-wp-integration'); ?>" value="" style="width: 150px; margin-right: 10px;" />
                                        <input type="text" id="iwp-new-user-first-name" placeholder="<?php esc_attr_e('First Name', 'iwp-wp-integration'); ?>" value="Test" style="width: 150px; margin-right: 10px;" />
                                        <input type="text" id="iwp-new-user-last-name" placeholder="<?php esc_attr_e('Last Name', 'iwp-wp-integration'); ?>" value="User" style="width: 150px; margin-right: 10px;" />
                                        <input type="email" id="iwp-new-user-email" placeholder="<?php esc_attr_e('Email', 'iwp-wp-integration'); ?>" value="" style="width: 200px;" />
                                        <p class="description"><?php esc_html_e('A random password will be generated and emailed to the user.', 'iwp-wp-integration'); ?></p>
                                    </div>
                                </div>
                                
                                <p class="description"><?php esc_html_e('Choose how to handle the customer for this test order.', 'iwp-wp-integration'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"></th>
                            <td>
                                <button type="button" id="iwp-create-test-order" class="button button-primary">
                                    <?php esc_html_e('Create Test Order', 'iwp-wp-integration'); ?>
                                </button>
                                <span id="iwp-test-order-status" style="margin-left: 10px;"></span>
                            </td>
                        </tr>
                    </table>
                    
                    <div id="iwp-test-order-results" style="margin-top: 15px;"></div>
                </div>
            <?php else : ?>
                <div class="form-section">
                    <h4><?php esc_html_e('Test Order Creation', 'iwp-wp-integration'); ?></h4>
                    <p class="description" style="color: #d63638;"><?php esc_html_e('WooCommerce is required to use this feature. Please install and activate WooCommerce to create test orders.', 'iwp-wp-integration'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="form-section">
                <h4><?php esc_html_e('Site Creation Testing', 'iwp-wp-integration'); ?></h4>
                <p class="description"><?php esc_html_e('Test site creation and status checking functionality directly from the admin panel.', 'iwp-wp-integration'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Quick Site Creation Test', 'iwp-wp-integration'); ?></th>
                        <td>
                            <div style="margin-bottom: 10px;">
                                <select id="iwp-test-snapshot-select" style="min-width: 300px;">
                                    <option value=""><?php esc_html_e('Select a snapshot...', 'iwp-wp-integration'); ?></option>
                                    <?php
                                    // Get available snapshots for testing
                                    $snapshots = IWP_Service::get_snapshots();
                                    if (!is_wp_error($snapshots) && isset($snapshots['data']) && is_array($snapshots['data'])) {
                                        foreach ($snapshots['data'] as $snapshot) {
                                            $slug = esc_attr($snapshot['slug'] ?? '');
                                            $name = esc_html($snapshot['name'] ?? 'Untitled');
                                            if (!empty($slug)) {
                                                echo '<option value="' . $slug . '">' . $name . ' (' . $slug . ')</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <input type="text" id="iwp-test-site-name" placeholder="<?php esc_attr_e('Test Site Name', 'iwp-wp-integration'); ?>" value="Test Site <?php echo date('H:i:s'); ?>" style="width: 300px;" />
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label>
                                    <input type="checkbox" id="iwp-add-to-sites-table" checked />
                                    <?php esc_html_e('Add test site to Sites management table', 'iwp-wp-integration'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('When checked, the test site will appear in the Sites management interface. Uncheck to create test sites without cluttering the Sites list.', 'iwp-wp-integration'); ?></p>
                            </div>
                            <button type="button" id="iwp-test-create-site" class="button button-primary">
                                <?php esc_html_e('Create Test Site', 'iwp-wp-integration'); ?>
                            </button>
                            <span id="iwp-test-create-status" style="margin-left: 10px;"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Status Management', 'iwp-wp-integration'); ?></th>
                        <td>
                            <button type="button" id="iwp-check-pending-sites" class="button button-secondary">
                                <?php esc_html_e('Check Pending Sites', 'iwp-wp-integration'); ?>
                            </button>
                            <button type="button" id="iwp-refresh-site-status" class="button button-primary" style="margin-left: 10px;">
                                <?php esc_html_e('Refresh Site Status', 'iwp-wp-integration'); ?>
                            </button>
                            <div id="iwp-status-check-results" style="margin-top: 15px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Database Status', 'iwp-wp-integration'); ?></th>
                        <td>
                            <div id="iwp-db-status">
                                <?php
                                $total_sites = IWP_Sites_Model::get_total_count();
                                $pending_sites = IWP_Sites_Model::get_total_count(array('status' => 'creating'));
                                $progress_sites = IWP_Sites_Model::get_total_count(array('status' => 'progress'));
                                $completed_sites = IWP_Sites_Model::get_total_count(array('status' => 'completed'));
                                $failed_sites = IWP_Sites_Model::get_total_count(array('status' => 'failed'));
                                
                                echo '<p><strong>Total Sites:</strong> ' . $total_sites . '</p>';
                                echo '<p><strong>Creating:</strong> ' . $pending_sites . ' | ';
                                echo '<strong>In Progress:</strong> ' . $progress_sites . ' | ';
                                echo '<strong>Completed:</strong> ' . $completed_sites . ' | ';
                                echo '<strong>Failed:</strong> ' . $failed_sites . '</p>';
                                ?>
                            </div>
                            <button type="button" id="iwp-refresh-db-status" class="button button-secondary">
                                <?php esc_html_e('Refresh Status', 'iwp-wp-integration'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
                
                <div id="iwp-testing-results" style="margin-top: 15px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 3px; display: none;">
                    <h4><?php esc_html_e('Test Results', 'iwp-wp-integration'); ?></h4>
                    <div id="iwp-testing-log"></div>
                </div>
            </div>

            <div class="form-section">
                <h4><?php esc_html_e('Plugin Information', 'iwp-wp-integration'); ?></h4>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Plugin Version', 'iwp-wp-integration'); ?></th>
                        <td><?php echo esc_html(IWP_VERSION); ?></td>
                    </tr>
                    <?php if (class_exists('WooCommerce')) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('WooCommerce Version', 'iwp-wp-integration'); ?></th>
                        <td><?php echo esc_html(WC()->version); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('HPOS Status', 'iwp-wp-integration'); ?></th>
                        <td>
                            <span style="color: <?php echo class_exists('IWP_HPOS') && IWP_HPOS::is_hpos_enabled() ? 'green' : 'orange'; ?>">
                                <?php echo class_exists('IWP_HPOS') && IWP_HPOS::is_hpos_enabled() ? esc_html__('Enabled', 'iwp-wp-integration') : esc_html__('Disabled', 'iwp-wp-integration'); ?>
                            </span>
                            <?php if (class_exists('IWP_HPOS') && IWP_HPOS::is_hpos_enabled()) : ?>
                                <small><?php esc_html_e('(High Performance Order Storage is active)', 'iwp-wp-integration'); ?></small>
                            <?php else : ?>
                                <small><?php esc_html_e('(Using legacy post-based order storage)', 'iwp-wp-integration'); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Actions', 'iwp-wp-integration'); ?></th>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=instawp-settings&action=reset_settings')); ?>" 
                               class="button button-secondary" 
                               onclick="return confirm('<?php esc_attr_e('Are you sure you want to reset all settings?', 'iwp-wp-integration'); ?>')">
                                <?php esc_html_e('Reset Settings', 'iwp-wp-integration'); ?>
                            </a>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render Documentation tab content
     */
    private function render_documentation_tab() {
        ?>
        <div class="tab-section">
            <h3><?php esc_html_e('Shortcode Documentation', 'iwp-wp-integration'); ?></h3>
            <?php $this->render_settings_section('iwp_settings', 'iwp_shortcode'); ?>
        </div>
        
        <div class="tab-section">
            <h3><?php esc_html_e('API References', 'iwp-wp-integration'); ?></h3>
            <div class="form-section">
                <h4><?php esc_html_e('Useful Links', 'iwp-wp-integration'); ?></h4>
                <ul>
                    <li><a href="https://app.instawp.io/user/api-tokens" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get API Key', 'iwp-wp-integration'); ?></a></li>
                    <li><a href="https://instawp.com/docs" target="_blank" rel="noopener noreferrer"><?php esc_html_e('InstaWP Documentation', 'iwp-wp-integration'); ?></a></li>
                    <li><a href="https://instawp.com/support" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Support', 'iwp-wp-integration'); ?></a></li>
                </ul>
            </div>
        </div>
        </div>
        <?php
    }

    /**
     * General settings section callback
     */
    public function general_settings_callback() {
        echo '<p>' . esc_html__('Configure the general settings for the InstaWP WooCommerce integration.', 'iwp-wp-integration') . '</p>';
    }

    /**
     * API settings section callback
     */
    public function api_settings_callback() {
        echo '<p>' . esc_html__('Configure the API settings for InstaWP', 'iwp-wp-integration') . '</p>';
    }

    /**
     * Snapshots section callback
     */
    public function snapshots_section_callback() {
        IWP_Logger::debug('snapshots_section_callback() called from admin interface', 'admin');
        
        echo '<p>' . esc_html__('Available InstaWP snapshots from your account.', 'iwp-wp-integration') . '</p>';
        
        $options = get_option('iwp_options', array());
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        if (empty($api_key)) {
            IWP_Logger::warning('No API key found in options', 'admin');
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Please enter your API key above to view snapshots.', 'iwp-wp-integration') . '</p></div>';
            return;
        }

        IWP_Logger::info('Fetching snapshots via centralized service', 'admin');
        $snapshots = IWP_Service::get_snapshots();
        
        if (is_wp_error($snapshots)) {
            IWP_Logger::error('Snapshots fetch failed in admin', 'admin', array('error' => $snapshots->get_error_message()));
            echo '<div class="notice notice-error inline"><p>' . esc_html__('Error fetching snapshots: ', 'iwp-wp-integration') . esc_html($snapshots->get_error_message()) . '</p></div>';
            return;
        }

        IWP_Logger::info('Snapshots fetched successfully in admin interface', 'admin');
        error_log('IWP WooCommerce V2: Snapshots data structure: ' . wp_json_encode(array_keys($snapshots['data'])));
        if (isset($snapshots['data']) && is_array($snapshots['data'])) {
            error_log('IWP WooCommerce V2: Number of snapshots found: ' . count($snapshots['data']));
        }

        echo '<div class="iwp-snapshots-container">';
        echo '<div class="iwp-snapshots-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">';
        echo '<h4>' . esc_html__('Snapshots', 'iwp-wp-integration') . '</h4>';
        echo '<button type="button" class="button button-secondary" id="iwp-refresh-snapshots">' . esc_html__('Refresh Snapshots', 'iwp-wp-integration') . '</button>';
        echo '</div>';

        if (!empty($snapshots) && isset($snapshots['data']) && is_array($snapshots['data'])) {
            echo '<div class="iwp-snapshots-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">';
            
            foreach ($snapshots['data'] as $snapshot) {
                $snapshot_slug = isset($snapshot['slug']) ? sanitize_text_field($snapshot['slug']) : '';
                $snapshot_name = isset($snapshot['name']) ? sanitize_text_field($snapshot['name']) : __('Untitled Snapshot', 'iwp-wp-integration');
                $snapshot_description = isset($snapshot['description']) ? sanitize_text_field($snapshot['description']) : '';
                $snapshot_image = isset($snapshot['image']) ? esc_url($snapshot['image']) : '';
                $created_at = isset($snapshot['created_at']) ? sanitize_text_field($snapshot['created_at']) : '';
                
                echo '<div class="iwp-snapshot-card" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #fff;">';
                
                if ($snapshot_image) {
                    echo '<img src="' . esc_url($snapshot_image) . '" alt="' . esc_attr($snapshot_name) . '" style="width: 100%; height: 150px; object-fit: cover; border-radius: 4px; margin-bottom: 10px;">';
                }
                
                echo '<h5 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600;">' . esc_html($snapshot_name) . '</h5>';
                
                if ($snapshot_description) {
                    echo '<p style="margin: 0 0 8px 0; font-size: 12px; color: #666; line-height: 1.4;">' . esc_html($snapshot_description) . '</p>';
                }
                
                echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">';
                echo '<small style="color: #999;">Slug: ' . esc_html($snapshot_slug) . '</small>';
                
                if ($created_at) {
                    $formatted_date = date_i18n(get_option('date_format'), strtotime($created_at));
                    echo '<small style="color: #999;">' . esc_html($formatted_date) . '</small>';
                }
                echo '</div>';
                
                echo '</div>';
            }
            
            echo '</div>';
        } else {
            echo '<p>' . esc_html__('No snapshots found.', 'iwp-wp-integration') . '</p>';
        }
        
        echo '</div>';
    }

    /**
     * Plans section callback
     */
    public function plans_section_callback() {
        IWP_Logger::debug('plans_section_callback() called from admin interface', 'admin');
        
        echo '<p>' . esc_html__('Available InstaWP plans from your account.', 'iwp-wp-integration') . '</p>';
        
        $options = get_option('iwp_options', array());
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        if (empty($api_key)) {
            IWP_Logger::warning('No API key found in options', 'admin');
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Please enter your API key above to view plans.', 'iwp-wp-integration') . '</p></div>';
            return;
        }

        error_log('IWP WooCommerce V2: Fetching plans via centralized service');
        $plans = IWP_Service::get_plans();
        
        if (is_wp_error($plans)) {
            error_log('IWP WooCommerce V2: Plans fetch failed in admin: ' . $plans->get_error_message());
            echo '<div class="notice notice-error inline"><p>' . esc_html__('Error fetching plans: ', 'iwp-wp-integration') . esc_html($plans->get_error_message()) . '</p></div>';
            return;
        }

        error_log('IWP WooCommerce V2: Plans fetched successfully in admin interface');
        
        // Count plans (now they are a direct array)
        $plan_count = 0;
        if (isset($plans) && is_array($plans)) {
            $plan_count = count($plans);
        }
        error_log('IWP WooCommerce V2: Number of plans found: ' . $plan_count);

        echo '<div class="iwp-plans-container">';
        echo '<div class="iwp-plans-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">';
        echo '<h4>' . esc_html__('Plans', 'iwp-wp-integration') . '</h4>';
        echo '<button type="button" class="button button-secondary" id="iwp-refresh-plans">' . esc_html__('Refresh Plans', 'iwp-wp-integration') . '</button>';
        echo '</div>';

        if ($plan_count > 0) {
            echo '<div class="iwp-plans-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">';
            
            foreach ($plans as $plan) {
                if (!is_array($plan) || !isset($plan['id'])) {
                    continue;
                }
                $plan_id = isset($plan['id']) ? sanitize_text_field($plan['id']) : '';
                $plan_name = isset($plan['display_name']) ? sanitize_text_field($plan['display_name']) : (isset($plan['name']) ? sanitize_text_field($plan['name']) : __('Untitled Plan', 'iwp-wp-integration'));
                $plan_description = isset($plan['short_description']) ? sanitize_text_field($plan['short_description']) : '';
                // Note: The API response doesn't include price/currency, so we'll skip those for now
                $plan_internal_name = isset($plan['name']) ? sanitize_text_field($plan['name']) : '';
                
                echo '<div class="iwp-plan-card" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #fff;">';
                
                echo '<h5 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600;">' . esc_html($plan_name) . '</h5>';
                
                if ($plan_description) {
                    echo '<p style="margin: 0 0 8px 0; font-size: 12px; color: #666; line-height: 1.4;">' . esc_html($plan_description) . '</p>';
                }
                
                echo '<div style="margin-top: 10px;">';
                if ($plan_internal_name) {
                    echo '<small style="color: #999; display: block;">Name: ' . esc_html($plan_internal_name) . '</small>';
                }
                echo '<small style="color: #999;">ID: ' . esc_html($plan_id) . '</small>';
                echo '</div>';
                
                echo '</div>';
            }
            
            echo '</div>';
        } else {
            echo '<p>' . esc_html__('No plans found.', 'iwp-wp-integration') . '</p>';
        }
        
        echo '</div>';
    }

    /**
     * Backward compatibility: Templates section callback (alias for snapshots_section_callback)
     *
     * @deprecated Use snapshots_section_callback() instead
     */
    public function templates_section_callback() {
        return $this->snapshots_section_callback();
    }

    /**
     * Cache management section callback
     */
    public function cache_section_callback() {
        echo '<p>' . esc_html__('Manage cached data for InstaWP integration.', 'iwp-wp-integration') . '</p>';
        
        echo '<div class="iwp-cache-container">';
        echo '<div class="iwp-cache-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">';
        echo '<h4>' . esc_html__('Cache Management', 'iwp-wp-integration') . '</h4>';
        echo '<div class="iwp-cache-actions">';
        echo '<button type="button" class="button button-secondary" id="iwp-warm-cache" style="margin-right: 5px;">' . esc_html__('Warm Up Cache', 'iwp-wp-integration') . '</button>';
        echo '<button type="button" class="button button-secondary" id="iwp-clear-transients">' . esc_html__('Clear All Cache', 'iwp-wp-integration') . '</button>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="iwp-cache-info" style="background: #f9f9f9; padding: 15px; border-radius: 4px; border-left: 4px solid #0073aa;">';
        echo '<p><strong>' . esc_html__('Cached Data:', 'iwp-wp-integration') . '</strong></p>';
        
        // Get detailed cache status from centralized service
        $cache_status = IWP_Service::get_cache_status();
        $cache_stats = IWP_Service::get_cache_stats();
        
        echo '<div class="iwp-cache-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">';
        
        // Snapshots cache info
        echo '<div class="iwp-cache-item">';
        echo '<h4 style="margin: 0 0 8px 0;">' . esc_html__('Snapshots Cache', 'iwp-wp-integration') . '</h4>';
        if ($cache_status['snapshots']) {
            echo '<div style="color: #46b450;">✓ ' . sprintf(esc_html__('%d items cached', 'iwp-wp-integration'), $cache_status['snapshots_count']) . '</div>';
            if ($cache_status['snapshots_expires']) {
                $expires_in = $cache_status['snapshots_expires'] - time();
                echo '<div style="font-size: 12px; color: #666;">' . sprintf(esc_html__('Expires in %s', 'iwp-wp-integration'), human_time_diff(time(), $cache_status['snapshots_expires'])) . '</div>';
            }
        } else {
            echo '<div style="color: #dc3232;">✗ ' . esc_html__('No cache', 'iwp-wp-integration') . '</div>';
        }
        echo '</div>';
        
        // Plans cache info
        echo '<div class="iwp-cache-item">';
        echo '<h4 style="margin: 0 0 8px 0;">' . esc_html__('Plans Cache', 'iwp-wp-integration') . '</h4>';
        if ($cache_status['plans']) {
            echo '<div style="color: #46b450;">✓ ' . sprintf(esc_html__('%d items cached', 'iwp-wp-integration'), $cache_status['plans_count']) . '</div>';
            if ($cache_status['plans_expires']) {
                $expires_in = $cache_status['plans_expires'] - time();
                echo '<div style="font-size: 12px; color: #666;">' . sprintf(esc_html__('Expires in %s', 'iwp-wp-integration'), human_time_diff(time(), $cache_status['plans_expires'])) . '</div>';
            }
        } else {
            echo '<div style="color: #dc3232;">✗ ' . esc_html__('No cache', 'iwp-wp-integration') . '</div>';
        }
        echo '</div>';
        
        echo '</div>';
        
        // Cache statistics
        echo '<div class="iwp-cache-stats" style="background: #fff; padding: 10px; border-radius: 3px; margin-bottom: 10px;">';
        echo '<strong>' . esc_html__('Cache Statistics:', 'iwp-wp-integration') . '</strong><br>';
        echo sprintf(esc_html__('Total Items: %d', 'iwp-wp-integration'), $cache_stats['total_cached_items']) . ' | ';
        echo sprintf(esc_html__('Memory Usage: %s', 'iwp-wp-integration'), size_format($cache_stats['memory_usage'])) . ' | ';
        echo sprintf(esc_html__('Hit Ratio: %d%%', 'iwp-wp-integration'), round($cache_stats['cache_hit_ratio'] * 100));
        echo '</div>';
        
        echo '<p style="font-size: 12px; color: #666; margin-top: 10px;">';
        echo esc_html__('Snapshots cache for 15 minutes, Plans cache for 1 hour. Use refresh buttons to update manually.', 'iwp-wp-integration');
        echo '</p>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Shortcode documentation section callback
     */
    public function shortcode_section_callback() {
        echo '<p>' . esc_html__('The iwp_site_creator shortcode allows you to create an InstaWP site creation form anywhere on your site.', 'iwp-wp-integration') . '</p>';
        
        echo '<div class="iwp-shortcode-docs" style="background: #f9f9f9; padding: 20px; border-radius: 4px; border-left: 4px solid #0073aa; margin-top: 15px;">';
        
        // Basic Usage
        echo '<h4>' . esc_html__('Basic Usage', 'iwp-wp-integration') . '</h4>';
        echo '<code style="background: #fff; padding: 8px; border: 1px solid #ddd; border-radius: 3px; display: block; margin-bottom: 15px;">';
        echo '[iwp_site_creator snapshot_slug="your-snapshot-slug"]';
        echo '</code>';
        
        // Parameters
        echo '<h4>' . esc_html__('Parameters', 'iwp-wp-integration') . '</h4>';
        echo '<table class="form-table" style="background: #fff; margin-bottom: 15px;">';
        
        echo '<tr>';
        echo '<th scope="row" style="padding: 10px;"><strong>snapshot_slug</strong></th>';
        echo '<td style="padding: 10px;">' . esc_html__('(Required) The slug of the InstaWP snapshot to use for site creation.', 'iwp-wp-integration') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row" style="padding: 10px;"><strong>email</strong></th>';
        echo '<td style="padding: 10px;">' . esc_html__('(Optional) Pre-fill the email field with this value.', 'iwp-wp-integration') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row" style="padding: 10px;"><strong>name</strong></th>';
        echo '<td style="padding: 10px;">' . esc_html__('(Optional) Pre-fill the site name field with this value.', 'iwp-wp-integration') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row" style="padding: 10px;"><strong>expiry_hours</strong></th>';
        echo '<td style="padding: 10px;">' . esc_html__('(Optional) Number of hours until the site expires. If set, the site will be temporary (is_reserved=false). If not set, the site will be permanent (is_reserved=true).', 'iwp-wp-integration') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row" style="padding: 10px;"><strong>sandbox</strong></th>';
        echo '<td style="padding: 10px;">' . esc_html__('(Optional) Set to "true" to create a sandbox/shared site. This sends is_shared:true to the API.', 'iwp-wp-integration') . '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        // Examples
        echo '<h4>' . esc_html__('Examples', 'iwp-wp-integration') . '</h4>';
        
        echo '<h5>' . esc_html__('Basic form:', 'iwp-wp-integration') . '</h5>';
        echo '<code style="background: #fff; padding: 8px; border: 1px solid #ddd; border-radius: 3px; display: block; margin-bottom: 10px;">';
        echo '[iwp_site_creator snapshot_slug="wordpress-blog"]';
        echo '</code>';
        
        echo '<h5>' . esc_html__('Pre-filled form:', 'iwp-wp-integration') . '</h5>';
        echo '<code style="background: #fff; padding: 8px; border: 1px solid #ddd; border-radius: 3px; display: block; margin-bottom: 10px;">';
        echo '[iwp_site_creator snapshot_slug="ecommerce-store" email="customer@example.com" name="My Store"]';
        echo '</code>';
        
        echo '<h5>' . esc_html__('Temporary site (24 hours):', 'iwp-wp-integration') . '</h5>';
        echo '<code style="background: #fff; padding: 8px; border: 1px solid #ddd; border-radius: 3px; display: block; margin-bottom: 10px;">';
        echo '[iwp_site_creator snapshot_slug="demo-site" expiry_hours="24"]';
        echo '</code>';
        
        echo '<h5>' . esc_html__('Sandbox site:', 'iwp-wp-integration') . '</h5>';
        echo '<code style="background: #fff; padding: 8px; border: 1px solid #ddd; border-radius: 3px; display: block; margin-bottom: 15px;">';
        echo '[iwp_site_creator snapshot_slug="sandbox-demo" sandbox="true"]';
        echo '</code>';
        
        // Styling
        echo '<h4>' . esc_html__('Styling', 'iwp-wp-integration') . '</h4>';
        echo '<p>' . esc_html__('The shortcode generates an unstyled form with CSS classes for theme customization:', 'iwp-wp-integration') . '</p>';
        
        echo '<ul style="margin-left: 20px;">';
        echo '<li><code>.iwp-site-creator-container</code> - Main container</li>';
        echo '<li><code>.iwp-site-creator-form</code> - Form element</li>';
        echo '<li><code>.iwp-site-creator-field-group</code> - Field wrapper</li>';
        echo '<li><code>.iwp-site-creator-input</code> - Input fields</li>';
        echo '<li><code>.iwp-site-creator-button</code> - Submit button</li>';
        echo '<li><code>.iwp-site-creator-results</code> - Success results display</li>';
        echo '</ul>';
        
        // Features
        echo '<h4>' . esc_html__('Features', 'iwp-wp-integration') . '</h4>';
        echo '<ul style="margin-left: 20px;">';
        echo '<li>' . esc_html__('Real-time site creation status tracking', 'iwp-wp-integration') . '</li>';
        echo '<li>' . esc_html__('Copy-to-clipboard functionality for credentials', 'iwp-wp-integration') . '</li>';
        echo '<li>' . esc_html__('Password show/hide toggle', 'iwp-wp-integration') . '</li>';
        echo '<li>' . esc_html__('Mobile-responsive design', 'iwp-wp-integration') . '</li>';
        echo '<li>' . esc_html__('Form validation and error handling', 'iwp-wp-integration') . '</li>';
        echo '<li>' . esc_html__('Support for both pool sites and task-based creation', 'iwp-wp-integration') . '</li>';
        echo '</ul>';
        
        echo '</div>';
    }

    /**
     * Checkbox field callback
     *
     * @param array $args Field arguments
     */
    public function checkbox_field_callback($args) {
        $options = get_option('iwp_options', array());
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : 'no';
        ?>
        <input type="checkbox" 
               id="<?php echo esc_attr($args['label_for']); ?>" 
               name="iwp_options[<?php echo esc_attr($args['label_for']); ?>]" 
               value="yes" 
               <?php checked('yes', $value); ?> />
        <?php if (isset($args['description'])) : ?>
            <p class="description"><?php echo wp_kses($args['description'], array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Text field callback
     *
     * @param array $args Field arguments
     */
    public function text_field_callback($args) {
        $options = get_option('iwp_options', array());
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        $type = isset($args['type']) ? $args['type'] : 'text';
        ?>
        <input type="<?php echo esc_attr($type); ?>" 
               id="<?php echo esc_attr($args['label_for']); ?>" 
               name="iwp_options[<?php echo esc_attr($args['label_for']); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <?php if (isset($args['description'])) : ?>
            <p class="description"><?php echo wp_kses($args['description'], array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Select field callback
     *
     * @param array $args Field arguments
     */
    public function select_field_callback($args) {
        $options = get_option('iwp_options', array());
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : '';
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>" 
                name="iwp_options[<?php echo esc_attr($args['label_for']); ?>]">
            <?php foreach ($args['options'] as $option_value => $option_label) : ?>
                <option value="<?php echo esc_attr($option_value); ?>" <?php selected($option_value, $value); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($args['description'])) : ?>
            <p class="description"><?php echo wp_kses($args['description'], array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Handle sites page row actions
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
                // Get API client
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

    /**
     * Sites page callback
     */
    public function sites_page() {
        // Handle row actions
        $this->handle_sites_page_actions();
        
        // Include the sites list table class
        if (!class_exists('IWP_Sites_List_Table')) {
            require_once IWP_PLUGIN_PATH . 'includes/admin/class-iwp-sites-list-table.php';
        }
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('InstaWP Sites', 'iwp-wp-integration'); ?>
                <a href="#" class="page-title-action" id="iwp-refresh-sites"><?php esc_html_e('Refresh', 'iwp-wp-integration'); ?></a>
            </h1>
            
            <p class="description">
                <?php esc_html_e('Manage all InstaWP sites created through WooCommerce orders, shortcodes, or other integrations.', 'iwp-wp-integration'); ?>
            </p>

            <?php
            // Display sites table
            $sites_table = new IWP_Sites_List_Table();
            $sites_table->prepare_items();
            ?>
            
            <form method="post">
                <?php
                $sites_table->display();
                ?>
            </form>
        </div>
        
        <style>
        .iwp-status {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .iwp-status-completed {
            background: #c6e1c6;
            color: #0d5016;
        }
        .iwp-status-progress {
            background: #ffeaa7;
            color: #8b6f00;
        }
        .iwp-status-failed {
            background: #fab1a0;
            color: #8b2635;
        }
        .iwp-status-unknown {
            background: #ddd;
            color: #555;
        }
        .iwp-password-hidden {
            font-family: monospace;
            letter-spacing: 2px;
        }
        .iwp-show-password {
            font-size: 11px;
            margin-left: 5px;
        }
        
        /* Row actions styling */
        .row-actions {
            font-size: 13px;
            color: #ddd;
            margin: 6px 0 0;
        }
        .row-actions a {
            color: #0073aa;
            text-decoration: none;
        }
        .row-actions a:hover {
            color: #00a0d2;
        }
        .row-actions .delete a {
            color: #a00;
        }
        .row-actions .delete a:hover {
            color: #dc3232;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle password show/hide
            $('.iwp-show-password').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var password = $btn.data('password');
                var $hidden = $btn.prev('.iwp-password-hidden');
                
                if ($btn.text() === '<?php echo esc_js(__('Show', 'iwp-wp-integration')); ?>') {
                    $hidden.text(password);
                    $btn.text('<?php echo esc_js(__('Hide', 'iwp-wp-integration')); ?>');
                } else {
                    $hidden.text('••••••••');
                    $btn.text('<?php echo esc_js(__('Show', 'iwp-wp-integration')); ?>');
                }
            });
            
            // Handle refresh button
            $('#iwp-refresh-sites').on('click', function(e) {
                e.preventDefault();
                location.reload();
            });
            
            // Handle delete site buttons
            $('.iwp-delete-site').on('click', function(e) {
                e.preventDefault();
                
                var $btn = $(this);
                var siteId = $btn.data('site-id');
                var siteUrl = $btn.data('site-url');
                
                if (!siteId) {
                    alert('<?php echo esc_js(__('Site ID is required', 'iwp-wp-integration')); ?>');
                    return;
                }
                
                var confirmMessage = siteUrl ? 
                    '<?php echo esc_js(__('Are you sure you want to delete the site:', 'iwp-wp-integration')); ?> ' + siteUrl + '?' :
                    '<?php echo esc_js(__('Are you sure you want to delete this site?', 'iwp-wp-integration')); ?>';
                
                if (!confirm(confirmMessage)) {
                    return;
                }
                
                // Show loading state
                $btn.prop('disabled', true).text('<?php echo esc_js(__('Deleting...', 'iwp-wp-integration')); ?>');
                
                // Make AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'iwp_delete_site',
                        site_id: siteId,
                        site_url: siteUrl,
                        nonce: '<?php echo wp_create_nonce('iwp_delete_site_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            // Remove the row from the table
                            $btn.closest('tr').fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert('<?php echo esc_js(__('Error:', 'iwp-wp-integration')); ?> ' + (response.data.message || '<?php echo esc_js(__('Unknown error occurred', 'iwp-wp-integration')); ?>'));
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Delete', 'iwp-wp-integration')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Network error occurred', 'iwp-wp-integration')); ?>');
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Delete', 'iwp-wp-integration')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Validate settings
     *
     * @param array $input Input data
     * @return array
     */
    public function validate_settings($input) {
        $output = array();

        if (isset($input['enabled'])) {
            $output['enabled'] = sanitize_text_field($input['enabled']);
        }

        if (isset($input['debug_mode'])) {
            $output['debug_mode'] = sanitize_text_field($input['debug_mode']);
        }

        if (isset($input['log_level'])) {
            $valid_levels = array('debug', 'info', 'warning', 'error');
            if (in_array($input['log_level'], $valid_levels)) {
                $output['log_level'] = sanitize_text_field($input['log_level']);
            }
        }

        if (isset($input['api_key'])) {
            $output['api_key'] = sanitize_text_field($input['api_key']);
        }

        if (isset($input['auto_create_sites_on_purchase'])) {
            $output['auto_create_sites_on_purchase'] = sanitize_text_field($input['auto_create_sites_on_purchase']);
        }

        if (isset($input['use_site_id_parameter'])) {
            $output['use_site_id_parameter'] = sanitize_text_field($input['use_site_id_parameter']);
        }

        return $output;
    }

    /**
     * Helper method to validate AJAX request security
     *
     * @param string $nonce_action The nonce action to verify
     * @param string $capability The required user capability
     * @return bool True if valid, exits with error if not
     */
    private function validate_ajax_request($nonce_action, $capability) {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], $nonce_action)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'iwp-wp-integration')
            ));
        }

        // Check user capabilities
        if (!current_user_can($capability)) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'iwp-wp-integration')
            ));
        }

        return true;
    }

    /**
     * Helper method to get and validate API key
     *
     * @return string The API key
     */
    private function get_validated_api_key() {
        $options = get_option('iwp_options', array());
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => __('API key is required.', 'iwp-wp-integration')
            ));
        }

        return $api_key;
    }

    /**
     * Helper method to setup API client with key
     *
     * @param string $api_key The API key to use
     */
    private function setup_api_client($api_key) {
        $this->api_client->set_api_key($api_key);
    }

    /**
     * Helper method to refresh snapshots
     *
     * @return array|WP_Error The snapshots or error
     */
    private function refresh_snapshots() {
        $this->api_client->clear_snapshots_cache();
        return $this->api_client->get_snapshots();
    }

    /**
     * AJAX handler for refreshing templates
     */
    public function ajax_refresh_templates() {
        IWP_Security::validate_ajax_request('iwp_admin_nonce', 'manage_woocommerce', 'nonce');

        $api_key = $this->get_validated_api_key();
        $this->setup_api_client($api_key);
        
        $snapshots = IWP_Service::refresh_snapshots();
        
        if (is_wp_error($snapshots)) {
            wp_send_json_error(array(
                'message' => $snapshots->get_error_message()
            ));
        }

        wp_send_json_success(array(
            'message' => __('Snapshots refreshed successfully.', 'iwp-wp-integration'),
            'snapshots' => $snapshots
        ));
    }

    /**
     * AJAX handler for refreshing product snapshots
     */
    public function ajax_refresh_product_snapshots() {
        $this->validate_ajax_request('iwp_product_admin_nonce', 'edit_products');
        
        $api_key = $this->get_validated_api_key();
        $this->setup_api_client($api_key);
        
        $snapshots = IWP_Service::refresh_snapshots();
        
        if (is_wp_error($snapshots)) {
            wp_send_json_error(array(
                'message' => $snapshots->get_error_message()
            ));
        }

        wp_send_json_success(array(
            'message' => __('Snapshots refreshed successfully.', 'iwp-wp-integration'),
            'snapshots' => $snapshots
        ));
    }

    /**
     * AJAX handler for getting snapshot preview
     */
    public function ajax_get_snapshot_preview() {
        $this->validate_ajax_request('iwp_product_admin_nonce', 'edit_products');

        $snapshot_slug = isset($_POST['snapshot_slug']) ? sanitize_text_field($_POST['snapshot_slug']) : '';
        
        if (empty($snapshot_slug)) {
            wp_send_json_error(array(
                'message' => __('Snapshot slug is required.', 'iwp-wp-integration')
            ));
        }

        $api_key = $this->get_validated_api_key();
        $this->setup_api_client($api_key);
        
        $snapshot = IWP_Service::get_snapshot($snapshot_slug);
        
        if (is_wp_error($snapshot)) {
            wp_send_json_error(array(
                'message' => $snapshot->get_error_message()
            ));
        }

        $preview_html = $this->generate_snapshot_preview_html($snapshot);
        wp_send_json_success($preview_html);
    }

    /**
     * Helper method to generate snapshot preview HTML
     *
     * @param array $snapshot The snapshot data
     * @return string The preview HTML
     */
    private function generate_snapshot_preview_html($snapshot) {
        ob_start();
        
        if (isset($snapshot['data'])) {
            $snapshot_data = $snapshot['data'];
            echo '<div class="iwp-snapshot-preview" style="border: 1px solid #ddd; padding: 15px; border-radius: 4px; background: #f9f9f9;">';
            
            if (isset($snapshot_data['name'])) {
                echo '<h5>' . esc_html($snapshot_data['name']) . '</h5>';
            }
            
            if (isset($snapshot_data['description'])) {
                echo '<p>' . esc_html($snapshot_data['description']) . '</p>';
            }
            
            if (isset($snapshot_data['screenshot_url'])) {
                echo '<img src="' . esc_url($snapshot_data['screenshot_url']) . '" alt="Snapshot Screenshot" style="max-width: 100%; height: auto; border-radius: 4px;">';
            }
            
            echo '</div>';
        } else {
            echo '<p>' . __('No snapshot data available.', 'iwp-wp-integration') . '</p>';
        }
        
        return ob_get_clean();
    }

    /**
     * AJAX handler for refreshing product plans
     */
    public function ajax_refresh_product_plans() {
        $this->validate_ajax_request('iwp_product_admin_nonce', 'edit_products');
        
        $api_key = $this->get_validated_api_key();
        $this->setup_api_client($api_key);
        
        $plans = IWP_Service::refresh_plans();
        
        if (is_wp_error($plans)) {
            wp_send_json_error(array(
                'message' => $plans->get_error_message()
            ));
        }

        wp_send_json_success(array(
            'message' => __('Plans refreshed successfully.', 'iwp-wp-integration'),
            'plans' => $plans
        ));
    }

    /**
     * AJAX handler for refreshing plans in settings
     */
    public function ajax_refresh_plans() {
        IWP_Security::validate_ajax_request('iwp_admin_nonce', 'manage_woocommerce', 'nonce');
        
        $api_key = $this->get_validated_api_key();
        $this->setup_api_client($api_key);
        
        $plans = IWP_Service::refresh_plans();
        
        if (is_wp_error($plans)) {
            wp_send_json_error(array(
                'message' => $plans->get_error_message()
            ));
        }

        wp_send_json_success(array(
            'message' => __('Plans refreshed successfully.', 'iwp-wp-integration'),
            'plans' => $plans
        ));
    }

    /**
     * Refresh plans from API
     *
     * @return array|WP_Error
     */
    private function refresh_plans() {
        // Clear cache first
        delete_transient('iwp_plans');
        
        // Fetch fresh data
        $plans = $this->api_client->get_plans();
        
        if (is_wp_error($plans)) {
            error_log('IWP WooCommerce V2: Failed to refresh plans: ' . $plans->get_error_message());
            return $plans;
        }
        
        error_log('IWP WooCommerce V2: Plans refreshed successfully');
        return $plans;
    }

    /**
     * Backward compatibility: AJAX handler for refreshing product templates
     * @deprecated Use ajax_refresh_product_snapshots() instead
     */
    public function ajax_refresh_product_templates() {
        return $this->ajax_refresh_product_snapshots();
    }

    /**
     * Backward compatibility: AJAX handler for getting template preview
     * @deprecated Use ajax_get_snapshot_preview() instead
     */
    public function ajax_get_template_preview() {
        return $this->ajax_get_snapshot_preview();
    }

    /**
     * Backward compatibility: Generate template preview HTML
     * @deprecated Use generate_snapshot_preview_html() instead
     */
    private function generate_template_preview_html($template) {
        return $this->generate_snapshot_preview_html($template);
    }

    /**
     * AJAX handler for clearing all transients
     */
    public function ajax_clear_transients() {
        IWP_Security::validate_ajax_request('iwp_admin_nonce', 'manage_woocommerce', 'nonce');
        
        error_log('IWP WooCommerce V2: Clearing all transients via centralized service');
        IWP_Service::clear_caches();
        
        // Get cache status to determine what was cleared
        $transients_cleared = array('snapshots', 'plans', 'templates (legacy)');
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('Successfully cleared %d cache type(s): %s', 'iwp-wp-integration'),
                count($transients_cleared),
                implode(', ', $transients_cleared)
            ),
            'cleared' => $transients_cleared
        ));
    }

    /**
     * AJAX handler for warming up caches
     */
    public function ajax_warm_cache() {
        IWP_Security::validate_ajax_request('iwp_admin_nonce', 'manage_woocommerce', 'nonce');
        
        error_log('IWP WooCommerce V2: Warming up caches via AJAX');
        
        // Warm up caches using centralized service
        $results = IWP_Service::warm_up_caches();
        
        $successful = array();
        if ($results['snapshots']) {
            $successful[] = 'snapshots';
        }
        if ($results['plans']) {
            $successful[] = 'plans';
        }
        
        if (empty($successful) && !empty($results['errors'])) {
            wp_send_json_error(array(
                'message' => __('Failed to warm up caches: ', 'iwp-wp-integration') . implode(', ', $results['errors']),
                'errors' => $results['errors']
            ));
        } else {
            $message = sprintf(
                __('Successfully warmed up %d cache type(s): %s', 'iwp-wp-integration'),
                count($successful),
                implode(', ', $successful)
            );
            
            if (!empty($results['errors'])) {
                $message .= '. ' . __('Some errors occurred: ', 'iwp-wp-integration') . implode(', ', $results['errors']);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'warmed_up' => $successful,
                'errors' => $results['errors']
            ));
        }
    }

    /**
     * Render a specific settings section
     *
     * @param string $page The settings page slug
     * @param string $section_id The section ID to render
     */
    private function render_settings_section($page, $section_id) {
        global $wp_settings_sections, $wp_settings_fields;
        
        if (!isset($wp_settings_sections[$page][$section_id])) {
            return;
        }
        
        $section = $wp_settings_sections[$page][$section_id];
        
        echo '<div class="iwp-settings-section" id="' . esc_attr($section_id) . '">';
        
        if ($section['title']) {
            echo '<h2>' . esc_html($section['title']) . '</h2>';
        }
        
        if ($section['callback']) {
            call_user_func($section['callback'], $section);
        }
        
        if (isset($wp_settings_fields[$page][$section_id])) {
            echo '<table class="form-table" role="presentation">';
            do_settings_fields($page, $section_id);
            echo '</table>';
        }
        
        echo '</div>';
    }

    /**
     * AJAX handler for creating test orders
     */
    public function ajax_create_test_order() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iwp_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'iwp-wp-integration'));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions', 'iwp-wp-integration'));
        }

        $product_id = intval($_POST['product_id'] ?? 0);
        $customer_type = sanitize_text_field($_POST['customer_type'] ?? 'existing');
        
        if (!$product_id) {
            wp_send_json_error(__('Product ID is required', 'iwp-wp-integration'));
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(__('Product not found', 'iwp-wp-integration'));
        }

        // Check if product has InstaWP snapshot configured
        $snapshot_slug = get_post_meta($product_id, '_iwp_selected_snapshot', true);
        if (empty($snapshot_slug)) {
            wp_send_json_error(__('Product does not have an InstaWP snapshot configured', 'iwp-wp-integration'));
        }

        // Handle different customer types
        $customer_id = 0;
        $customer_email = '';
        $customer_first_name = '';
        $customer_last_name = '';
        
        switch ($customer_type) {
            case 'existing':
                $customer_id = intval($_POST['customer_id'] ?? 0);
                if (!$customer_id) {
                    wp_send_json_error(__('Please select a user', 'iwp-wp-integration'));
                }
                
                $customer = get_user_by('ID', $customer_id);
                if (!$customer) {
                    wp_send_json_error(__('Selected user not found', 'iwp-wp-integration'));
                }
                
                $customer_email = $customer->user_email;
                $customer_first_name = $customer->first_name ?: $customer->display_name;
                $customer_last_name = $customer->last_name ?: '';
                break;
                
            case 'new':
                $new_username = sanitize_user($_POST['new_user_username'] ?? '');
                $new_email = sanitize_email($_POST['new_user_email'] ?? '');
                $new_first_name = sanitize_text_field($_POST['new_user_first_name'] ?? 'Test');
                $new_last_name = sanitize_text_field($_POST['new_user_last_name'] ?? 'User');
                
                if (empty($new_username) || empty($new_email)) {
                    wp_send_json_error(__('Username and email are required for new users', 'iwp-wp-integration'));
                }
                
                if (username_exists($new_username)) {
                    wp_send_json_error(__('Username already exists', 'iwp-wp-integration'));
                }
                
                if (email_exists($new_email)) {
                    wp_send_json_error(__('Email already exists', 'iwp-wp-integration'));
                }
                
                // Create new user
                $new_password = wp_generate_password(12, false);
                $customer_id = wp_create_user($new_username, $new_password, $new_email);
                
                if (is_wp_error($customer_id)) {
                    wp_send_json_error(__('Failed to create user: ', 'iwp-wp-integration') . $customer_id->get_error_message());
                }
                
                // Update user meta
                wp_update_user(array(
                    'ID' => $customer_id,
                    'first_name' => $new_first_name,
                    'last_name' => $new_last_name,
                    'display_name' => $new_first_name . ' ' . $new_last_name
                ));
                
                // Send new user notification
                wp_new_user_notification($customer_id, null, 'user');
                
                $customer_email = $new_email;
                $customer_first_name = $new_first_name;
                $customer_last_name = $new_last_name;
                break;
                
            case 'guest':
            default:
                $customer_email = sanitize_email($_POST['customer_email'] ?? 'test@example.com');
                $customer_first_name = sanitize_text_field($_POST['customer_first_name'] ?? 'Test');
                $customer_last_name = sanitize_text_field($_POST['customer_last_name'] ?? 'Customer');
                
                if (empty($customer_email)) {
                    wp_send_json_error(__('Customer email is required', 'iwp-wp-integration'));
                }
                break;
        }

        try {
            // Create test order
            $order = wc_create_order();
            
            // Add product to order
            $order->add_product($product, 1);
            
            // Set customer information
            if ($customer_id) {
                $order->set_customer_id($customer_id);
            }
            
            // Set billing information
            $order->set_billing_first_name($customer_first_name);
            $order->set_billing_last_name($customer_last_name);
            $order->set_billing_email($customer_email);
            
            // Calculate totals
            $order->calculate_totals();
            
            // Check if auto-create is enabled globally
            $options = get_option('iwp_options', array());
            $auto_create_enabled = isset($options['auto_create_sites_on_purchase']) ? $options['auto_create_sites_on_purchase'] : 'yes';
            
            if ($auto_create_enabled === 'yes') {
                // Set order status to completed to trigger automatic site creation
                $order->set_status('completed');
            } else {
                // Set to processing status to avoid automatic site creation
                $order->set_status('processing');
            }
            
            // Save order
            $order->save();
            
            // Add order note indicating this is a test order
            $test_note = __('Test order created via InstaWP admin panel for testing site creation functionality.', 'iwp-wp-integration');
            if ($customer_type === 'new') {
                $test_note .= ' ' . sprintf(__('New user created: %s', 'iwp-wp-integration'), $customer_email);
            }
            
            if ($auto_create_enabled === 'yes') {
                $test_note .= ' ' . __('Sites will be created automatically.', 'iwp-wp-integration');
            } else {
                $test_note .= ' ' . __('Auto-create is disabled - use "Setup Site" button to create sites manually.', 'iwp-wp-integration');
            }
            
            $order->add_order_note($test_note);
            
            $success_message = sprintf(
                __('Test order #%s created successfully for product "%s"', 'iwp-wp-integration'), 
                $order->get_order_number(), 
                $product->get_name()
            );
            
            if ($auto_create_enabled !== 'yes') {
                $success_message .= ' ' . __('(Auto-create disabled - sites not created automatically)', 'iwp-wp-integration');
            }
            
            if ($customer_type === 'existing') {
                $success_message .= sprintf(__(' for user %s', 'iwp-wp-integration'), $customer_email);
            } elseif ($customer_type === 'new') {
                $success_message .= sprintf(__(' with new user %s (login details sent via email)', 'iwp-wp-integration'), $customer_email);
            } else {
                $success_message .= sprintf(__(' with guest customer %s', 'iwp-wp-integration'), $customer_email);
            }
            
            wp_send_json_success(array(
                'message' => $success_message,
                'order_id' => $order->get_id(),
                'order_edit_url' => admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
                'snapshot_slug' => $snapshot_slug,
                'customer_type' => $customer_type,
                'customer_id' => $customer_id,
                'customer_email' => $customer_email
            ));

        } catch (Exception $e) {
            error_log('IWP WooCommerce V2: Test order creation failed - ' . $e->getMessage());
            wp_send_json_error(__('Failed to create test order: ', 'iwp-wp-integration') . $e->getMessage());
        }
    }

    /**
     * AJAX handler for manual site setup
     */
    public function ajax_setup_sites() {
        // Verify nonce
        $order_id = intval($_POST['order_id'] ?? 0);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        
        if (!wp_verify_nonce($nonce, 'iwp_setup_sites_' . $order_id)) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'iwp-wp-integration')
            ));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'iwp-wp-integration')
            ));
        }

        if (!$order_id) {
            wp_send_json_error(array(
                'message' => __('Order ID is required.', 'iwp-wp-integration')
            ));
        }

        // Get the order processor and call manual creation
        $order_processor = new IWP_Order_Processor();
        $result = $order_processor->manually_create_sites($order_id);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'sites_created' => $result['sites_created'] ?? array(),
                'errors' => $result['errors'] ?? array()
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
                'errors' => $result['errors'] ?? array()
            ));
        }
    }

    /**
     * AJAX handler for adding custom domain to InstaWP site
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
        $new_url = 'https://' . $domain_name;
        
        error_log('IWP WooCommerce V2: Updating site URL to mapped domain: ' . $new_url);

        // Update in _iwp_sites_created (order processor format)
        $sites_created = get_post_meta($order_id, '_iwp_sites_created', true);
        if (is_array($sites_created)) {
            foreach ($sites_created as &$site_data) {
                if (isset($site_data['site_data']['site_id']) && $site_data['site_data']['site_id'] == $site_id) {
                    $site_data['site_data']['site_url'] = $new_url;
                    $site_data['site_data']['wp_url'] = $new_url;
                    error_log('IWP WooCommerce V2: Updated site URL in _iwp_sites_created');
                    break;
                }
            }
            update_post_meta($order_id, '_iwp_sites_created', $sites_created);
        }

        // Update in _iwp_created_sites (site manager format)
        $created_sites = get_post_meta($order_id, '_iwp_created_sites', true);
        if (is_array($created_sites)) {
            foreach ($created_sites as &$site_info) {
                if (isset($site_info['site_id']) && $site_info['site_id'] == $site_id) {
                    $site_info['wp_url'] = $new_url;
                    error_log('IWP WooCommerce V2: Updated site URL in _iwp_created_sites');
                    break;
                }
            }
            update_post_meta($order_id, '_iwp_created_sites', $created_sites);
        }

        error_log('IWP WooCommerce V2: Site URL update completed for domain: ' . $domain_name);
    }

    /**
     * AJAX handler for deleting InstaWP sites
     */
    public function ajax_delete_site() {
        IWP_Security::validate_ajax_request('iwp_delete_site_nonce', 'manage_woocommerce', 'nonce');

        $site_id = sanitize_text_field($_POST['site_id'] ?? '');
        $site_url = sanitize_text_field($_POST['site_url'] ?? '');

        if (empty($site_id)) {
            wp_send_json_error(array(
                'message' => __('Site ID is required', 'iwp-wp-integration')
            ));
        }

        // Get API client and delete site
        $options = get_option('iwp_options', array());
        $api_key = $options['api_key'] ?? '';
        
        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => __('API key not configured', 'iwp-wp-integration')
            ));
        }

        $this->api_client->set_api_key($api_key);
        $result = $this->api_client->delete_site($site_id);

        if (is_wp_error($result)) {
            IWP_Logger::error('Site deletion failed', 'admin', array(
                'site_id' => $site_id,
                'error' => $result->get_error_message()
            ));
            
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Failed to delete site: %s', 'iwp-wp-integration'),
                    $result->get_error_message()
                )
            ));
        }

        // Also remove from database table if it exists
        $db_site = IWP_Sites_Model::get_by_site_id($site_id);
        if ($db_site) {
            IWP_Sites_Model::delete($site_id);
            IWP_Logger::info('Removed site from database table', 'admin', array('site_id' => $site_id));
        }

        IWP_Logger::info('Site deleted successfully', 'admin', array(
            'site_id' => $site_id,
            'site_url' => $site_url
        ));

        wp_send_json_success(array(
            'message' => sprintf(
                __('Site "%s" has been deleted successfully.', 'iwp-wp-integration'),
                $site_url ?: $site_id
            )
        ));
    }

    /**
     * AJAX handler for testing site creation
     */
    public function ajax_test_create_site() {
        IWP_Security::validate_ajax_request('iwp_admin_nonce', 'manage_options', 'nonce');

        $snapshot_slug = sanitize_text_field($_POST['snapshot_slug'] ?? '');
        $site_name = sanitize_text_field($_POST['site_name'] ?? '');
        $add_to_sites_table = filter_var($_POST['add_to_sites_table'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (empty($snapshot_slug)) {
            wp_send_json_error(array(
                'message' => __('Snapshot slug is required', 'iwp-wp-integration')
            ));
        }

        if (empty($site_name)) {
            wp_send_json_error(array(
                'message' => __('Site name is required', 'iwp-wp-integration')
            ));
        }

        // Get API key
        $options = get_option('iwp_options', array());
        $api_key = $options['api_key'] ?? '';
        
        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => __('API key not configured', 'iwp-wp-integration')
            ));
        }

        // Create site data
        $site_data = array(
            'name' => $site_name,
            'title' => $site_name,
            'admin_email' => get_option('admin_email'),
            'admin_username' => 'admin',
            'admin_password' => wp_generate_password(12, false)
        );

        // Use the site manager to create site with tracking
        $site_manager = new IWP_Site_Manager();
        
        // Create a fake order for testing purposes
        $test_order_id = 0; // No real order
        $test_product_id = 0; // No real product
        
        try {
            // Get current user ID
            $user_id = get_current_user_id();

            // Conditionally create initial database record
            $db_site_id = null;
            $initial_site_data = null;
            
            if ($add_to_sites_table) {
                $initial_site_data = array(
                    'site_id' => 'test-' . uniqid(),
                    'status' => 'creating',
                    'snapshot_slug' => $snapshot_slug,
                    'product_id' => null,
                    'order_id' => null,
                    'user_id' => $user_id,
                    'source' => 'admin_test',
                    'source_data' => array(
                        'snapshot_slug' => $snapshot_slug,
                        'site_data' => $site_data,
                        'test_mode' => true
                    )
                );

                $db_site_id = IWP_Sites_Model::create($initial_site_data);
                IWP_Logger::info('Created test site record in database', 'admin-test', array(
                    'db_id' => $db_site_id,
                    'snapshot_slug' => $snapshot_slug
                ));
            } else {
                IWP_Logger::info('Skipping database record creation per user request', 'admin-test', array(
                    'snapshot_slug' => $snapshot_slug
                ));
            }

            // Create the site via API
            $api_client = new IWP_API_Client();
            $api_client->set_api_key($api_key);
            $response = $api_client->create_site_from_snapshot($snapshot_slug, $site_data);

            if (is_wp_error($response)) {
                // Update database record to failed status
                if ($db_site_id) {
                    IWP_Sites_Model::update($initial_site_data['site_id'], array(
                        'status' => 'failed',
                        'api_response' => array('error' => $response->get_error_message())
                    ));
                }
                
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Site creation failed: %s', 'iwp-wp-integration'),
                        $response->get_error_message()
                    )
                ));
            }

            $site_data_response = $response['data'] ?? array();

            // Get site ID from API response or fallback
            $real_site_id = $site_data_response['id'] ?? ('test-' . uniqid());
            $status = isset($site_data_response['is_pool']) && $site_data_response['is_pool'] === true ? 'completed' : 'progress';
            
            // Update database record if we created one
            if ($add_to_sites_table && $initial_site_data) {
                $update_data = array(
                    'site_id' => $real_site_id,
                    'site_url' => $site_data_response['wp_url'] ?? '',
                    'wp_username' => $site_data_response['wp_username'] ?? '',
                    'wp_password' => $site_data_response['wp_password'] ?? '',
                    'wp_admin_url' => !empty($site_data_response['wp_url']) ? trailingslashit($site_data_response['wp_url']) . 'wp-admin' : '',
                    's_hash' => $site_data_response['s_hash'] ?? '',
                    'status' => $status,
                    'task_id' => $site_data_response['task_id'] ?? null,
                    'is_pool' => isset($site_data_response['is_pool']) ? (bool)$site_data_response['is_pool'] : false,
                    'api_response' => $site_data_response
                );

                IWP_Sites_Model::update($initial_site_data['site_id'], $update_data);
                
                IWP_Logger::info('Test site created and database updated', 'admin-test', array(
                    'old_site_id' => $initial_site_data['site_id'],
                    'new_site_id' => $real_site_id,
                    'status' => $status
                ));
            } else {
                IWP_Logger::info('Test site created (no database record)', 'admin-test', array(
                    'site_id' => $real_site_id,
                    'status' => $status
                ));
            }

            $message = sprintf(
                __('Test site created successfully! Site ID: %s', 'iwp-wp-integration'),
                $real_site_id
            );

            if ($status === 'completed') {
                $message .= __(' (Ready immediately - pool site)', 'iwp-wp-integration');
            } else {
                $message .= __(' (Creating in background - task-based)', 'iwp-wp-integration');
            }
            
            if ($add_to_sites_table) {
                $message .= __(' - Added to Sites table', 'iwp-wp-integration');
            } else {
                $message .= __(' - Not added to Sites table', 'iwp-wp-integration');
            }

            wp_send_json_success(array(
                'message' => $message,
                'site_id' => $real_site_id,
                'task_id' => $site_data_response['task_id'] ?? null,
                'site_url' => $site_data_response['wp_url'] ?? null,
                'status' => $status,
                'is_pool' => isset($site_data_response['is_pool']) ? (bool)$site_data_response['is_pool'] : false,
                'added_to_sites_table' => $add_to_sites_table
            ));

        } catch (Exception $e) {
            IWP_Logger::error('Test site creation failed with exception', 'admin-test', array(
                'error' => $e->getMessage()
            ));
            
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Site creation failed: %s', 'iwp-wp-integration'),
                    $e->getMessage()
                )
            ));
        }
    }

    /**
     * AJAX handler for testing status checking
     */
    public function ajax_test_check_status() {
        IWP_Security::validate_ajax_request('iwp_admin_nonce', 'manage_options', 'nonce');

        try {
            // Get all pending sites
            $pending_sites = IWP_Sites_Model::get_pending_sites();
            $checked_count = 0;
            $updated_count = 0;
            $results = array();

            IWP_Logger::info('Manual status check initiated', 'admin-test', array(
                'pending_sites_count' => count($pending_sites)
            ));

            // Check each pending site
            foreach ($pending_sites as $db_site) {
                if (empty($db_site->task_id)) {
                    continue;
                }

                $checked_count++;
                $old_status = $db_site->status;

                // Get API client
                $options = get_option('iwp_options', array());
                $api_key = $options['api_key'] ?? '';
                
                if (empty($api_key)) {
                    continue;
                }

                $api_client = new IWP_API_Client();
                $api_client->set_api_key($api_key);

                // Check task status
                $response = $api_client->get_task_status($db_site->task_id);
                
                if (is_wp_error($response)) {
                    IWP_Logger::error('Failed to check task status during manual check', 'admin-test', array(
                        'site_id' => $db_site->site_id,
                        'task_id' => $db_site->task_id,
                        'error' => $response->get_error_message()
                    ));
                    continue;
                }

                $task_info = $response['data'] ?? array();
                $raw_status = $task_info['status'] ?? 1;
                
                // Map status code (same logic as site manager)
                $new_status = 'progress';
                switch (intval($raw_status)) {
                    case 0:
                        $new_status = 'completed';
                        break;
                    case 1:
                        $new_status = 'progress';
                        break;
                    case 2:
                        $new_status = 'failed';
                        break;
                }

                // Update if status changed
                if ($new_status !== $old_status && $new_status !== 'progress') {
                    IWP_Sites_Model::update($db_site->site_id, array(
                        'status' => $new_status,
                        'api_response' => $task_info
                    ));
                    
                    $updated_count++;
                    $results[] = array(
                        'site_id' => $db_site->site_id,
                        'old_status' => $old_status,
                        'new_status' => $new_status,
                        'task_id' => $db_site->task_id
                    );

                    IWP_Logger::info('Updated site status during manual check', 'admin-test', array(
                        'site_id' => $db_site->site_id,
                        'old_status' => $old_status,
                        'new_status' => $new_status
                    ));
                }
            }

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Status check completed. Checked %d sites, updated %d sites.', 'iwp-wp-integration'),
                    $checked_count,
                    $updated_count
                ),
                'checked_count' => $checked_count,
                'updated_count' => $updated_count,
                'results' => $results
            ));

        } catch (Exception $e) {
            IWP_Logger::error('Manual status check failed with exception', 'admin-test', array(
                'error' => $e->getMessage()
            ));
            
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Status check failed: %s', 'iwp-wp-integration'),
                    $e->getMessage()
                )
            ));
        }
    }

    /**
     * AJAX handler for forcing cron check
     */
    public function ajax_force_cron_check() {
        IWP_Security::validate_ajax_request('iwp_admin_nonce', 'manage_options', 'nonce');

        try {
            // Get the site manager and trigger the cron check manually
            $site_manager = new IWP_Site_Manager();
            $site_manager->check_pending_sites();

            IWP_Logger::info('Manual cron check executed successfully', 'admin-test');

            wp_send_json_success(array(
                'message' => __('Cron check executed successfully. Check the "Check All Pending Sites" button to see results.', 'iwp-wp-integration')
            ));

        } catch (Exception $e) {
            IWP_Logger::error('Manual cron check failed with exception', 'admin-test', array(
                'error' => $e->getMessage()
            ));
            
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Cron check failed: %s', 'iwp-wp-integration'),
                    $e->getMessage()
                )
            ));
        }
    }

    /**
     * AJAX handler for testing site update API
     */
    public function ajax_test_site_update() {
        IWP_Security::validate_ajax_request('iwp_admin_nonce', 'manage_options', 'nonce');

        $site_id = sanitize_text_field($_POST['site_id'] ?? '');
        
        if (empty($site_id)) {
            wp_send_json_error(array(
                'message' => __('Site ID is required', 'iwp-wp-integration')
            ));
        }

        try {
            // Test post_options configuration (will be nested in wordpress object)
            $post_options = array(
                'option_name3' => false,
                'option_name4' => 'test_value_' . time()
            );

            IWP_Logger::info('Testing site update API', 'admin-test', array(
                'site_id' => $site_id,
                'post_options' => $post_options
            ));

            $result = $this->api_client->update_site($site_id, $post_options);

            if (is_wp_error($result)) {
                IWP_Logger::error('Site update test failed', 'admin-test', array(
                    'site_id' => $site_id,
                    'error' => $result->get_error_message()
                ));

                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Site update failed: %s', 'iwp-wp-integration'),
                        $result->get_error_message()
                    )
                ));
            }

            IWP_Logger::info('Site update test successful', 'admin-test', array(
                'site_id' => $site_id,
                'result' => $result
            ));

            wp_send_json_success(array(
                'message' => __('Site updated successfully with post_options!', 'iwp-wp-integration'),
                'site_id' => $site_id,
                'post_options' => $post_options,
                'api_response' => $result
            ));

        } catch (Exception $e) {
            IWP_Logger::error('Site update test failed with exception', 'admin-test', array(
                'site_id' => $site_id,
                'error' => $e->getMessage()
            ));
            
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Site update failed: %s', 'iwp-wp-integration'),
                    $e->getMessage()
                )
            ));
        }
    }
}