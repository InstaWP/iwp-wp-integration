<?php
/**
 * Settings Page class for IWP Integration
 *
 * @package IWP
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Settings_Page class
 */
class IWP_Settings_Page {

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
    }

    /**
     * Render the complete settings page with tabs
     */
    public function render() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('InstaWP Integration Settings', 'iwp-wp-integration'); ?></h1>
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper iwp-admin-tabs">
                <a href="#general-settings" class="nav-tab nav-tab-active" id="general-settings-tab">
                    <?php esc_html_e('General Settings', 'iwp-wp-integration'); ?>
                </a>
                <a href="#instawp-data" class="nav-tab" id="instawp-data-tab">
                    <?php esc_html_e('InstaWP Data', 'iwp-wp-integration'); ?>
                </a>
                <a href="#testing" class="nav-tab" id="testing-tab">
                    <?php esc_html_e('Testing & Development', 'iwp-wp-integration'); ?>
                </a>
                <a href="#documentation" class="nav-tab" id="documentation-tab">
                    <?php esc_html_e('Documentation', 'iwp-wp-integration'); ?>
                </a>
            </nav>
            
            <!-- Tab Content -->
            <div class="tab-content-wrapper">
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
        </div>
        
        <?php $this->render_inline_scripts(); ?>
        <?php
    }

    /**
     * Render General Settings tab content
     */
    private function render_general_settings_tab() {
        ?>
        <form method="post" action="options.php" class="iwp-settings-form">
            <?php
            settings_fields('iwp_settings');
            ?>
            <input type="hidden" name="iwp_options[_form_submitted]" value="general_settings" />
            <?php
            do_settings_sections('iwp_settings');
            submit_button(__('Save Settings', 'iwp-wp-integration'), 'primary', 'submit', false);
            ?>
        </form>
        <?php
    }

    /**
     * Render InstaWP Data tab content
     */
    private function render_instawp_data_tab() {
        // Check if API key is configured
        $options = get_option('iwp_options', array());
        $api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
        
        if (empty($api_key)) {
            ?>
            <div class="iwp-data-section">
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('API key is not configured. Please configure your InstaWP API key in the General Settings tab to view snapshots and plans.', 'iwp-wp-integration'); ?>
                    </p>
                    <p>
                        <a href="#general-settings" class="button button-primary iwp-switch-tab" data-tab="general-settings">
                            <?php esc_html_e('Go to General Settings', 'iwp-wp-integration'); ?>
                        </a>
                    </p>
                </div>
            </div>
            <?php
            return;
        }
        
        ?>
        <div class="iwp-data-section">
            <!-- Team Selection Section -->
            <div class="iwp-team-section">
                <h3><?php esc_html_e('Team Selection', 'iwp-wp-integration'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Select a team to filter snapshots and plans. By default, your logged-in team data will be shown.', 'iwp-wp-integration'); ?>
                </p>
                
                <div class="iwp-team-controls">
                    <div class="iwp-team-dropdown-container">
                        <select id="iwp-team-select" class="iwp-team-select">
                            <?php $this->render_team_options(); ?>
                        </select>
                        <button type="button" id="iwp-refresh-teams" class="button button-secondary">
                            <?php esc_html_e('Refresh Teams', 'iwp-wp-integration'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <h3><?php esc_html_e('Snapshots & Plans', 'iwp-wp-integration'); ?></h3>
            <p class="description">
                <?php esc_html_e('View and manage your InstaWP snapshots and plans. Click refresh buttons to update cached data.', 'iwp-wp-integration'); ?>
            </p>
            
            <div class="iwp-data-grid">
                <div class="iwp-data-box">
                    <h4><?php esc_html_e('InstaWP Snapshots', 'iwp-wp-integration'); ?></h4>
                    <div id="iwp-snapshots-list">
                        <?php $this->display_snapshots(); ?>
                    </div>
                    <button type="button" id="iwp-refresh-snapshots" class="button button-secondary">
                        <?php esc_html_e('Refresh Snapshots', 'iwp-wp-integration'); ?>
                    </button>
                </div>
                
                <div class="iwp-data-box">
                    <h4><?php esc_html_e('InstaWP Plans', 'iwp-wp-integration'); ?></h4>
                    <div id="iwp-plans-list">
                        <?php $this->display_plans(); ?>
                    </div>
                    <button type="button" id="iwp-refresh-plans" class="button button-secondary">
                        <?php esc_html_e('Refresh Plans', 'iwp-wp-integration'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Testing & Development tab content
     */
    private function render_testing_tab() {
        ?>
        <div class="iwp-testing-section">
            <!-- Debug Settings Section -->
            <div class="iwp-debug-settings">
                <h3><?php esc_html_e('Debug Settings', 'iwp-wp-integration'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Configure debugging and logging options for troubleshooting.', 'iwp-wp-integration'); ?>
                </p>
                
                <form method="post" action="options.php">
                    <?php settings_fields('iwp_settings'); ?>
                    <input type="hidden" name="iwp_options[_form_submitted]" value="debug_settings" />
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Debug Mode', 'iwp-wp-integration'); ?></th>
                            <td>
                                <?php 
                                $options = get_option('iwp_options', array());
                                $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : 'no';
                                ?>
                                <label>
                                    <input type="checkbox" name="iwp_options[debug_mode]" value="yes" <?php checked($debug_mode, 'yes'); ?> />
                                    <?php esc_html_e('Enable debug mode for troubleshooting', 'iwp-wp-integration'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Log Level', 'iwp-wp-integration'); ?></th>
                            <td>
                                <?php 
                                $log_level = isset($options['log_level']) ? $options['log_level'] : 'error';
                                ?>
                                <select name="iwp_options[log_level]">
                                    <option value="debug" <?php selected($log_level, 'debug'); ?>><?php esc_html_e('Debug', 'iwp-wp-integration'); ?></option>
                                    <option value="info" <?php selected($log_level, 'info'); ?>><?php esc_html_e('Info', 'iwp-wp-integration'); ?></option>
                                    <option value="warning" <?php selected($log_level, 'warning'); ?>><?php esc_html_e('Warning', 'iwp-wp-integration'); ?></option>
                                    <option value="error" <?php selected($log_level, 'error'); ?>><?php esc_html_e('Error', 'iwp-wp-integration'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Select the logging level for plugin operations.', 'iwp-wp-integration'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Debug Settings', 'iwp-wp-integration')); ?>
                </form>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <!-- Test Order Section -->
            <div class="iwp-test-orders">
                <h3><?php esc_html_e('Test Order Creation', 'iwp-wp-integration'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Create test orders to verify your integration is working correctly.', 'iwp-wp-integration'); ?>
                </p>
                
                <?php if (class_exists('WooCommerce')) : ?>
                    <div class="iwp-test-form">
                        <?php $this->render_test_order_form(); ?>
                    </div>
                <?php else : ?>
                    <div class="notice notice-warning">
                        <p><?php esc_html_e('WooCommerce is required for test order functionality.', 'iwp-wp-integration'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Documentation tab content
     */
    private function render_documentation_tab() {
        ?>
        <div class="iwp-docs-section">
            <h3><?php esc_html_e('Plugin Documentation', 'iwp-wp-integration'); ?></h3>
            
            <div class="iwp-docs-grid">
                <div class="iwp-docs-box">
                    <h4><?php esc_html_e('Shortcode Usage', 'iwp-wp-integration'); ?></h4>
                    <p><?php esc_html_e('Use the iwp_site_creator shortcode to create standalone site creation forms:', 'iwp-wp-integration'); ?></p>
                    <code>[iwp_site_creator snapshot_slug="your-snapshot-slug"]</code>
                    
                    <h5><?php esc_html_e('Parameters:', 'iwp-wp-integration'); ?></h5>
                    <ul>
                        <li><code>snapshot_slug</code> - Required: The InstaWP snapshot to use</li>
                        <li><code>email</code> - Optional: Pre-fill email field</li>
                        <li><code>name</code> - Optional: Pre-fill site name field</li>
                        <li><code>expiry_hours</code> - Optional: Hours until site expires</li>
                    </ul>
                </div>
                
                <div class="iwp-docs-box">
                    <h4><?php esc_html_e('Useful Links', 'iwp-wp-integration'); ?></h4>
                    <ul>
                        <li><a href="https://app.instawp.io/user/api-tokens" target="_blank"><?php esc_html_e('Get Your API Key', 'iwp-wp-integration'); ?></a></li>
                        <li><a href="https://instawp.com/docs/" target="_blank"><?php esc_html_e('InstaWP Documentation', 'iwp-wp-integration'); ?></a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=instawp-sites'); ?>"><?php esc_html_e('Manage Sites', 'iwp-wp-integration'); ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Display snapshots data
     */
    private function display_snapshots() {
        // Get team-specific or general cache key
        $options = get_option('iwp_options', array());
        $team_id = isset($options['selected_team_id']) ? intval($options['selected_team_id']) : null;
        $cache_key = $team_id ? 'iwp_snapshots_team_' . $team_id : 'iwp_snapshots';
        $snapshots = get_transient($cache_key);
        
        if (empty($snapshots)) {
            echo '<p>' . esc_html__('No snapshots cached. Click refresh to load data.', 'iwp-wp-integration') . '</p>';
            return;
        }

        if (!is_array($snapshots)) {
            echo '<p>' . esc_html__('Invalid snapshot data format. Click refresh to reload.', 'iwp-wp-integration') . '</p>';
            return;
        }

        echo '<div class="iwp-snapshots-grid">';
        foreach ($snapshots as $snapshot) {
            if (!is_array($snapshot)) {
                continue;
            }
            
            $name = $snapshot['name'] ?? $snapshot['title'] ?? 'Unnamed';
            $slug = $snapshot['slug'] ?? $snapshot['id'] ?? 'No slug';
            
            printf(
                '<div class="iwp-snapshot-item"><strong>%s</strong><br><small>Slug: %s</small></div>',
                esc_html($name),
                esc_html($slug)
            );
        }
        echo '</div>';
    }

    /**
     * Display plans data
     */
    private function display_plans() {
        // Get team-specific or general cache key
        $options = get_option('iwp_options', array());
        $team_id = isset($options['selected_team_id']) ? intval($options['selected_team_id']) : null;
        $cache_key = $team_id ? 'iwp_plans_team_' . $team_id : 'iwp_plans';
        $plans = get_transient($cache_key);
        
        if (empty($plans)) {
            echo '<p>' . esc_html__('No plans cached. Click refresh to load data.', 'iwp-wp-integration') . '</p>';
            return;
        }

        if (!is_array($plans)) {
            echo '<p>' . esc_html__('Invalid plan data format. Click refresh to reload.', 'iwp-wp-integration') . '</p>';
            return;
        }

        // Check if we have any plans
        if (count($plans) === 0) {
            echo '<p>' . esc_html__('No plans found. Click refresh to reload.', 'iwp-wp-integration') . '</p>';
            return;
        }

        echo '<div class="iwp-plans-grid">';
        foreach ($plans as $plan) {
            // Process each plan in the array
            if (!is_array($plan) || !isset($plan['id'])) {
                continue;
            }
            
            // Use the same structure as the admin file
            $plan_id = isset($plan['id']) ? sanitize_text_field($plan['id']) : 'No ID';
            $plan_name = isset($plan['display_name']) ? sanitize_text_field($plan['display_name']) : 
                        (isset($plan['name']) ? sanitize_text_field($plan['name']) : 'Unnamed Plan');
            $plan_description = isset($plan['short_description']) ? sanitize_text_field($plan['short_description']) : '';
            
            echo '<div class="iwp-plan-item">';
            echo '<strong>' . esc_html($plan_name) . '</strong><br>';
            if ($plan_description) {
                echo '<small style="color: #666;">' . esc_html($plan_description) . '</small><br>';
            }
            echo '<small>ID: ' . esc_html($plan_id) . '</small>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Render team selection dropdown options
     */
    private function render_team_options() {
        require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-service.php';
        
        $teams = IWP_Service::get_teams_for_dropdown();
        $selected_team = IWP_Service::get_selected_team();
        
        foreach ($teams as $value => $label) {
            $selected = selected($selected_team, $value, false);
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
    }

    /**
     * Render test order form
     */
    private function render_test_order_form() {
        $products = get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_iwp_selected_snapshot',
                    'compare' => 'EXISTS'
                )
            )
        ));
        ?>
        
        <div class="iwp-test-form-container">
            <label for="iwp-test-product-select"><?php esc_html_e('Select Product:', 'iwp-wp-integration'); ?></label>
            <select id="iwp-test-product-select" class="regular-text">
                <option value=""><?php esc_html_e('Choose a product...', 'iwp-wp-integration'); ?></option>
                <?php foreach ($products as $product) : ?>
                    <option value="<?php echo esc_attr($product->ID); ?>">
                        <?php echo esc_html($product->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="button" id="iwp-create-test-order" class="button button-primary">
                <?php esc_html_e('Create Test Order', 'iwp-wp-integration'); ?>
            </button>
            
            <div id="iwp-test-results" class="iwp-test-results"></div>
        </div>
        <?php
    }

    /**
     * Render inline scripts for tab functionality
     */
    private function render_inline_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Simple tab switching
            $('.iwp-admin-tabs .nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var targetTab = $(this).attr('href').substring(1);
                
                // Update nav tabs
                $('.iwp-admin-tabs .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Update content
                $('.iwp-tab-content').removeClass('active');
                $('#' + targetTab).addClass('active');
                
                // Save state
                localStorage.setItem('iwp_active_tab', targetTab);
            });
            
            // Restore active tab
            var savedTab = localStorage.getItem('iwp_active_tab');
            if (savedTab && $('#' + savedTab).length) {
                $('.iwp-admin-tabs .nav-tab[href="#' + savedTab + '"]').trigger('click');
            }
            
            // Handle tab switching from buttons
            $('.iwp-switch-tab').on('click', function(e) {
                e.preventDefault();
                var targetTab = $(this).data('tab');
                $('.iwp-admin-tabs .nav-tab[href="#' + targetTab + '"]').trigger('click');
            });
            
            // Team selection handler
            $('#iwp-team-select').on('change', function() {
                var teamId = $(this).val();
                var $select = $(this);
                var originalHtml = $select.html();
                
                // Show loading state
                $select.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'iwp_set_team',
                    team_id: teamId || null,
                    nonce: '<?php echo wp_create_nonce('iwp_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Refresh snapshots and plans for the new team
                        $('#iwp-refresh-snapshots').trigger('click');
                        setTimeout(function() {
                            $('#iwp-refresh-plans').trigger('click');
                        }, 500);
                    } else {
                        alert('<?php esc_html_e('Failed to set team', 'iwp-wp-integration'); ?>');
                        // Restore original selection on error
                        $select.html(originalHtml);
                    }
                }).fail(function() {
                    alert('<?php esc_html_e('Network error occurred', 'iwp-wp-integration'); ?>');
                    $select.html(originalHtml);
                }).always(function() {
                    $select.prop('disabled', false);
                });
            });
            
            // Refresh teams handler
            $('#iwp-refresh-teams').on('click', function() {
                var $btn = $(this);
                var $select = $('#iwp-team-select');
                var originalText = $btn.text();
                var selectedValue = $select.val();
                
                $btn.text('<?php esc_html_e('Loading...', 'iwp-wp-integration'); ?>').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'iwp_refresh_teams',
                    nonce: '<?php echo wp_create_nonce('iwp_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Reload the page to refresh the dropdown
                        location.reload();
                    } else {
                        alert('<?php esc_html_e('Failed to refresh teams', 'iwp-wp-integration'); ?>');
                    }
                }).fail(function() {
                    alert('<?php esc_html_e('Network error occurred', 'iwp-wp-integration'); ?>');
                }).always(function() {
                    $btn.text(originalText).prop('disabled', false);
                });
            });
            
            // AJAX handlers
            $('#iwp-refresh-snapshots').on('click', function() {
                var $btn = $(this);
                var originalText = $btn.text();
                
                $btn.text('<?php esc_html_e('Loading...', 'iwp-wp-integration'); ?>').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'iwp_refresh_templates',
                    nonce: '<?php echo wp_create_nonce('iwp_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('<?php esc_html_e('Failed to refresh snapshots', 'iwp-wp-integration'); ?>');
                    }
                }).always(function() {
                    $btn.text(originalText).prop('disabled', false);
                });
            });
            
            $('#iwp-refresh-plans').on('click', function() {
                var $btn = $(this);
                var originalText = $btn.text();
                
                $btn.text('<?php esc_html_e('Loading...', 'iwp-wp-integration'); ?>').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'iwp_refresh_plans',
                    nonce: '<?php echo wp_create_nonce('iwp_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('<?php esc_html_e('Failed to refresh plans', 'iwp-wp-integration'); ?>');
                    }
                }).always(function() {
                    $btn.text(originalText).prop('disabled', false);
                });
            });
            
            // Test order creation handler
            $('#iwp-create-test-order').on('click', function() {
                var $btn = $(this);
                var $results = $('#iwp-test-results');
                var productId = $('#iwp-test-product-select').val();
                
                if (!productId) {
                    alert('<?php esc_html_e('Please select a product', 'iwp-wp-integration'); ?>');
                    return;
                }
                
                var originalText = $btn.text();
                $btn.text('<?php esc_html_e('Creating Order...', 'iwp-wp-integration'); ?>').prop('disabled', true);
                $results.removeClass('has-content').html('');
                
                $.post(ajaxurl, {
                    action: 'iwp_create_test_order',
                    product_id: productId,
                    nonce: '<?php echo wp_create_nonce('iwp_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        var html = '<div class="notice notice-success">';
                        html += '<p><strong>' + response.data.message + '</strong></p>';
                        html += '<p><strong>Customer:</strong> ' + response.data.customer_name + ' (' + response.data.customer_email + ')</p>';
                        html += '<p><strong>Order ID:</strong> #' + response.data.order_id + '</p>';
                        html += '<p><strong>Snapshot:</strong> ' + response.data.snapshot_slug + '</p>';
                        if (response.data.plan_id && response.data.plan_id !== 'No plan selected') {
                            html += '<p><strong>Plan:</strong> ' + response.data.plan_id + '</p>';
                        }
                        html += '<p style="margin-top: 15px;">';
                        html += '<a href="' + response.data.order_edit_url + '" class="button button-primary" target="_blank">View Order in Admin</a> ';
                        html += '<a href="' + response.data.my_account_url + '" class="button button-secondary" target="_blank">View in My Account</a>';
                        html += '</p>';
                        html += '<p class="description" style="margin-top: 10px;">The order has been created and assigned to your user account. You can view it in My Account → Orders to test the customer experience.</p>';
                        html += '</div>';
                        
                        $results.html(html).addClass('has-content');
                    } else {
                        var errorHtml = '<div class="notice notice-error">';
                        errorHtml += '<p><strong>Error:</strong> ' + (response.data || 'Unknown error') + '</p>';
                        errorHtml += '</div>';
                        
                        $results.html(errorHtml).addClass('has-content');
                    }
                }).fail(function() {
                    var errorHtml = '<div class="notice notice-error">';
                    errorHtml += '<p><strong>Error:</strong> Network error occurred</p>';
                    errorHtml += '</div>';
                    
                    $results.html(errorHtml).addClass('has-content');
                }).always(function() {
                    $btn.text(originalText).prop('disabled', false);
                });
            });
        });
        </script>
        
        <style>
        .iwp-admin-tabs {
            border-bottom: 1px solid #ccd0d4;
            margin: 0 0 0px 0;
        }
        
        .iwp-tab-content {
            display: none;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-top: none;
            padding: 20px;
            border-radius: 0 0 5px 5px;
        }
        
        .iwp-tab-content.active {
            display: block;
        }
        
        .iwp-data-grid,
        .iwp-docs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .iwp-data-box,
        .iwp-docs-box {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 20px;
        }
        
        .iwp-data-box h4,
        .iwp-docs-box h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #1d2327;
        }
        
        .iwp-snapshots-grid,
        .iwp-plans-grid {
            margin: 15px 0;
        }
        
        .iwp-snapshot-item,
        .iwp-plan-item {
            padding: 8px 12px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-bottom: 5px;
        }
        
        .iwp-test-form-container {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .iwp-test-form-container label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .iwp-test-form-container select {
            margin-bottom: 15px;
        }
        
        .iwp-test-results {
            margin-top: 20px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
            display: none;
        }
        
        .iwp-test-results.has-content {
            display: block;
        }
        
        .iwp-team-section {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .iwp-team-controls {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .iwp-team-label {
            font-weight: 600;
            color: #1d2327;
        }
        
        .iwp-team-dropdown-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .iwp-team-select {
            min-width: 250px;
            height: 32px;
        }
        
        @media (max-width: 768px) {
            .iwp-data-grid,
            .iwp-docs-grid {
                grid-template-columns: 1fr;
            }
            
            .iwp-team-dropdown-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .iwp-team-select {
                width: 100%;
                min-width: auto;
            }
        }
        </style>
        <?php
    }
}