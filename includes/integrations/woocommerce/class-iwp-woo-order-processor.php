<?php
/**
 * Order Processing for IWP WooCommerce Integration v2
 *
 * @package IWP
 * @since 0.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_Order_Processor class
 */
class IWP_Woo_Order_Processor {

    /**
     * API Client instance
     *
     * @var IWP_API_Client
     */
    private $api_client;

    /**
     * Product Integration instance
     *
     * @var IWP_Product_Integration
     */
    private $product_integration;

    /**
     * Constructor
     */
    public function __construct() {
        // Load required dependencies
        if (!class_exists('IWP_API_Client')) {
            require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-api-client.php';
        }
        if (!class_exists('IWP_Logger')) {
            require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-logger.php';
        }
        
        $this->api_client = new IWP_API_Client();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Process order when it's completed
        add_action('woocommerce_order_status_completed', array($this, 'process_completed_order'));
        
        // Process order when it's processing (for digital products)
        add_action('woocommerce_order_status_processing', array($this, 'process_processing_order'));
        
        // Add order meta box to display sites
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        
        // Add custom columns to orders list
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_columns'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'populate_order_columns'), 10, 2);
    }

    /**
     * Set product integration instance
     *
     * @param IWP_Product_Integration $product_integration
     */
    public function set_product_integration($product_integration) {
        $this->product_integration = $product_integration;
    }

    /**
     * Process completed order
     *
     * @param int $order_id
     */
    public function process_completed_order($order_id) {
        error_log('IWP WooCommerce V2: Processing completed order: ' . $order_id);
        $this->process_order($order_id, 'completed');
    }

    /**
     * Process processing order
     *
     * @param int $order_id
     */
    public function process_processing_order($order_id) {
        error_log('IWP WooCommerce V2: Processing order in processing status: ' . $order_id);
        $this->process_order($order_id, 'processing');
    }

    /**
     * Process order for site creation
     *
     * @param int $order_id
     * @param string $status
     */
    private function process_order($order_id, $status) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            error_log('IWP WooCommerce V2: Order not found: ' . $order_id);
            return;
        }

        // Check if we've already processed this order
        $processed = get_post_meta($order_id, '_iwp_processed', true);
        if ($processed) {
            error_log('IWP WooCommerce V2: Order already processed: ' . $order_id);
            return;
        }

        // Check if auto-create is enabled globally
        $options = get_option('iwp_options', array());
        $auto_create_enabled = isset($options['auto_create_sites_on_purchase']) ? $options['auto_create_sites_on_purchase'] : 'yes';

        if ($auto_create_enabled !== 'yes') {
            error_log('IWP WooCommerce V2: Auto-create disabled globally, skipping automatic site creation for order: ' . $order_id);
            return;
        }

        $sites_created = array();
        $errors = array();

        // Check if site_id upgrade mode is active
        if (!class_exists('IWP_Frontend')) {
            require_once IWP_PLUGIN_PATH . 'includes/frontend/class-iwp-frontend.php';
        }
        $frontend = new IWP_Frontend();
        $upgrade_site_id = $frontend->get_stored_site_id();

        if ($upgrade_site_id) {
            error_log('IWP WooCommerce V2: Site upgrade mode detected for site ID: ' . $upgrade_site_id);
        }

        // Check for demo sites to reconcile before processing order
        $reconciled_sites = $this->reconcile_demo_sites_to_order($order, $upgrade_site_id);
        if (!empty($reconciled_sites)) {
            error_log('IWP WooCommerce V2: Reconciled ' . count($reconciled_sites) . ' demo site(s) to order');
        }

        // Process each item in the order
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if (!$product) {
                continue;
            }

            // Check if this product has a snapshot or plan
            $snapshot_slug = get_post_meta($product->get_id(), '_iwp_selected_snapshot', true);
            $plan_id = get_post_meta($product->get_id(), '_iwp_selected_plan', true);
            
            // If we have a site_id for upgrade and a plan_id, upgrade the site instead of creating new one
            if ($upgrade_site_id && !empty($plan_id)) {
                error_log('IWP WooCommerce V2: Upgrading site ' . $upgrade_site_id . ' to plan ' . $plan_id);
                
                $upgrade_result = $this->upgrade_site_plan($order, $product, $upgrade_site_id, $plan_id, $item);
                
                if (is_wp_error($upgrade_result)) {
                    $errors[] = array(
                        'product_id' => $product_id,
                        'product_name' => $product->get_name(),
                        'error' => $upgrade_result->get_error_message()
                    );
                    error_log('IWP WooCommerce V2: Site upgrade failed for product ' . $product_id . ': ' . $upgrade_result->get_error_message());
                } else {
                    $sites_created[] = array(
                        'product_id' => $product_id,
                        'product_name' => $product->get_name(),
                        'site_data' => $upgrade_result,
                        'action' => 'upgraded'
                    );
                    error_log('IWP WooCommerce V2: Site upgraded successfully for product ' . $product_id);
                }
                
                continue;
            }
            
            // Regular site creation flow
            if (empty($snapshot_slug)) {
                error_log('IWP WooCommerce V2: No snapshot selected for product ID: ' . $product->get_id());
                continue;
            }
            
            error_log('IWP WooCommerce V2: Processing product with snapshot slug: ' . $snapshot_slug . ', plan ID: ' . $plan_id);

            // Create site for this product
            $site_result = $this->create_site_for_product($order, $product, $snapshot_slug, $item, $plan_id);
            
            if (is_wp_error($site_result)) {
                $errors[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'error' => $site_result->get_error_message()
                );
                error_log('IWP WooCommerce V2: Site creation failed for product ' . $product_id . ': ' . $site_result->get_error_message());
            } else {
                $sites_created[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'site_data' => $site_result,
                    'action' => 'created'
                );
                error_log('IWP WooCommerce V2: Site created successfully for product ' . $product_id);
            }
        }

        // Store results in order meta
        if (!empty($sites_created)) {
            update_post_meta($order_id, '_iwp_sites_created', $sites_created);
        }
        
        if (!empty($errors)) {
            update_post_meta($order_id, '_iwp_creation_errors', $errors);
        }

        // Mark order as processed
        update_post_meta($order_id, '_iwp_processed', true);
        update_post_meta($order_id, '_iwp_processed_date', current_time('mysql'));

        // Add order note
        $note = $this->generate_order_note($sites_created, $errors);
        $order->add_order_note($note, 1); // 1 = customer visible

        // Always clear stored site_id after processing order (whether upgrade happened or not)
        if (!class_exists('IWP_Frontend')) {
            require_once IWP_PLUGIN_PATH . 'includes/frontend/class-iwp-frontend.php';
        }
        $frontend = new IWP_Frontend();
        $frontend->clear_stored_site_id();
        error_log('IWP WooCommerce V2: Cleared stored site_id after order processing');

        error_log('IWP WooCommerce V2: Order processing completed for order: ' . $order_id);
    }

    /**
     * Create site for a specific product
     *
     * @param WC_Order $order
     * @param WC_Product $product
     * @param string $snapshot_slug
     * @param WC_Order_Item_Product $item
     * @param string $plan_id Optional plan ID
     * @return array|WP_Error
     */
    private function create_site_for_product($order, $product, $snapshot_slug, $item, $plan_id = '') {
        // Get API key from settings
        $options = get_option('iwp_options', array());
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API key not configured', 'iwp-woo-v2'));
        }

        $this->api_client->set_api_key($api_key);

        // Prepare site data
        $billing_email = $order->get_billing_email();
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name = $order->get_billing_last_name();
        
        $site_name = sanitize_title($product->get_name() . '-' . $order->get_id() . '-' . time());
        
        // Get expiry settings from product
        $expiry_type = get_post_meta($product->get_id(), '_iwp_site_expiry_type', true);
        $expiry_hours = get_post_meta($product->get_id(), '_iwp_site_expiry_hours', true);
        
        // Default to permanent if not set
        if (empty($expiry_type)) {
            $expiry_type = 'permanent';
        }
        
        $site_data = array(
            'name' => $site_name,
            'title' => $product->get_name() . ' - Order #' . $order->get_order_number(),
            'admin_email' => $billing_email,
            'admin_username' => sanitize_user($billing_first_name . $billing_last_name),
            'admin_password' => wp_generate_password(12, false),
            'order_id' => $order->get_id(),
            'product_id' => $product->get_id(),
            'customer_id' => $order->get_customer_id()
        );
        
        // Add subscription reference if this order is related to a subscription
        if (function_exists('wcs_order_contains_subscription') && function_exists('wcs_get_subscriptions_for_order')) {
            if (wcs_order_contains_subscription($order->get_id(), 'parent')) {
                $subscriptions = wcs_get_subscriptions_for_order($order->get_id(), array('order_type' => 'parent'));
                if (!empty($subscriptions)) {
                    $subscription = reset($subscriptions);
                    $site_data['subscription_id'] = $subscription->get_id();
                }
            } elseif (wcs_order_contains_subscription($order->get_id(), 'renewal')) {
                $subscriptions = wcs_get_subscriptions_for_order($order->get_id(), array('order_type' => 'renewal'));
                if (!empty($subscriptions)) {
                    $subscription = reset($subscriptions);
                    $site_data['subscription_id'] = $subscription->get_id();
                }
            }
        }
        
        // Add expiry settings to site data
        if ($expiry_type === 'temporary' && !empty($expiry_hours)) {
            $site_data['expiry_hours'] = intval($expiry_hours);
            $site_data['is_reserved'] = false; // Temporary sites are not reserved
        } else {
            // Permanent sites
            $site_data['is_reserved'] = true; // Permanent sites are reserved
        }

        // Use site manager to create site with progress tracking
        if (!class_exists('IWP_Site_Manager')) {
            require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-site-manager.php';
        }
        $site_manager = new IWP_Site_Manager();
        $result = $site_manager->create_site_with_tracking(
            $snapshot_slug, 
            $site_data, 
            $order->get_id(), 
            $product->get_id(),
            $plan_id
        );
        
        if (is_wp_error($result)) {
            return $result;
        }

        // Store additional data for backward compatibility
        $result['site_credentials'] = array(
            'admin_username' => $site_data['admin_username'],
            'admin_password' => $site_data['admin_password'],
            'admin_email' => $site_data['admin_email']
        );

        return $result;
    }

    /**
     * Upgrade site plan for a specific product
     *
     * @param WC_Order $order
     * @param WC_Product $product
     * @param int $site_id
     * @param string $plan_id
     * @param WC_Order_Item_Product $item
     * @return array|WP_Error
     */
    private function upgrade_site_plan($order, $product, $site_id, $plan_id, $item) {
        // Get API key from settings
        $options = get_option('iwp_options', array());
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API key not configured', 'iwp-woo-v2'));
        }

        $this->api_client->set_api_key($api_key);

        // Call the upgrade API
        $result = $this->api_client->upgrade_site_plan($site_id, $plan_id);
        
        if (is_wp_error($result)) {
            return $result;
        }

        // Plan upgrade successful - now disable demo helper plugin
        error_log('IWP WooCommerce V2: Plan upgrade successful, attempting to disable demo helper plugin');
        $demo_disable_result = $this->api_client->disable_demo_helper($site_id);
        
        if (is_wp_error($demo_disable_result)) {
            error_log('IWP WooCommerce V2: Failed to disable demo helper: ' . $demo_disable_result->get_error_message());
            // Don't fail the upgrade if demo helper disable fails - just log it
        } else {
            error_log('IWP WooCommerce V2: Demo helper disable result: ' . $demo_disable_result['message']);
        }

        // Extract site details from upgrade response if available
        $upgrade_site_data = array(
            'order_id' => $order->get_id(),
            'product_id' => $product->get_id(),
            'customer_id' => $order->get_customer_id(),
            'upgrade_response' => $result
        );

        // Try to extract site details from the API response
        if (is_array($result) && isset($result['data'])) {
            $site_details = $result['data'];
            
            // Get site details to update database
            if (isset($site_details['site_details']) && is_array($site_details['site_details'])) {
                $details = $site_details['site_details'];
                if (isset($details['data']) && is_array($details['data'])) {
                    $site_info = $details['data'];
                    
                    if (isset($site_info['url'])) {
                        $upgrade_site_data['site_url'] = $site_info['url'];
                    }
                    if (isset($site_info['wp_username'])) {
                        $upgrade_site_data['wp_username'] = $site_info['wp_username'];
                    }
                    if (isset($site_info['wp_password'])) {
                        $upgrade_site_data['wp_password'] = $site_info['wp_password'];
                    }
                    if (isset($site_info['s_hash'])) {
                        $upgrade_site_data['s_hash'] = $site_info['s_hash'];
                    }
                }
            }
        }

        // Update the database with the plan change
        IWP_Sites_Model::init();
        $db_update_result = IWP_Sites_Model::update_plan($site_id, $plan_id, $upgrade_site_data);
        
        if (!$db_update_result) {
            error_log('IWP WooCommerce V2: Failed to update site plan in database for site_id: ' . $site_id);
        } else {
            error_log('IWP WooCommerce V2: Successfully updated site plan in database for site_id: ' . $site_id);
        }

        // Prepare response data for order processing
        $upgrade_data = array(
            'site_id' => $site_id,
            'plan_id' => $plan_id,
            'action' => 'upgraded',
            'order_id' => $order->get_id(),
            'product_id' => $product->get_id(),
            'customer_id' => $order->get_customer_id(),
            'upgrade_response' => $result
        );

        // Add site details to response if we got them
        if (isset($upgrade_site_data['site_url'])) {
            $upgrade_data['site_url'] = $upgrade_site_data['site_url'];
        }
        if (isset($upgrade_site_data['wp_username'])) {
            $upgrade_data['wp_username'] = $upgrade_site_data['wp_username'];
        }
        if (isset($upgrade_site_data['s_hash'])) {
            $upgrade_data['s_hash'] = $upgrade_site_data['s_hash'];
        }

        // Add this to order meta as well
        $existing_upgrades = get_post_meta($order->get_id(), '_iwp_site_upgrades', true);
        if (!is_array($existing_upgrades)) {
            $existing_upgrades = array();
        }
        $existing_upgrades[] = $upgrade_data;
        update_post_meta($order->get_id(), '_iwp_site_upgrades', $existing_upgrades);

        return $upgrade_data;
    }

    /**
     * Generate order note for site creation results
     *
     * @param array $sites_created
     * @param array $errors
     * @return string
     */
    private function generate_order_note($sites_created, $errors) {
        $note = __('Processing Results:', 'iwp-woo-v2') . "\n\n";
        
        if (!empty($sites_created)) {
            $created_sites = array_filter($sites_created, function($site) {
                return !isset($site['action']) || $site['action'] === 'created';
            });
            
            $upgraded_sites = array_filter($sites_created, function($site) {
                return isset($site['action']) && $site['action'] === 'upgraded';
            });
            
            if (!empty($created_sites)) {
                $note .= __('Sites Created:', 'iwp-woo-v2') . "\n";
                foreach ($created_sites as $site) {
                    $note .= sprintf(
                        "- %s: %s\n",
                        $site['product_name'],
                        isset($site['site_data']['site_url']) ? $site['site_data']['site_url'] : __('Site creation in progress', 'iwp-woo-v2')
                    );
                }
                $note .= "\n";
            }
            
            if (!empty($upgraded_sites)) {
                $note .= __('Sites Upgraded:', 'iwp-woo-v2') . "\n";
                foreach ($upgraded_sites as $site) {
                    $site_url = '';
                    
                    // Check if we have site details from the upgrade response (use correct API structure)
                    if (isset($site['site_data']['upgrade_response']['site_details']['data']['url'])) {
                        $site_url = $site['site_data']['upgrade_response']['site_details']['data']['url'];
                    } elseif (isset($site['site_data']['site_details']['data']['url'])) {
                        $site_url = $site['site_data']['site_details']['data']['url'];
                    }
                    
                    if (!empty($site_url)) {
                        $note .= sprintf(
                            "- %s: Site ID %s upgraded to plan %s - %s\n",
                            $site['product_name'],
                            $site['site_data']['site_id'],
                            $site['site_data']['plan_id'],
                            $site_url
                        );
                    } else {
                        $note .= sprintf(
                            "- %s: Site ID %s upgraded to plan %s\n",
                            $site['product_name'],
                            $site['site_data']['site_id'],
                            $site['site_data']['plan_id']
                        );
                    }
                }
                $note .= "\n";
            }
        }
        
        if (!empty($errors)) {
            $note .= __('Errors:', 'iwp-woo-v2') . "\n";
            foreach ($errors as $error) {
                $note .= sprintf(
                    "- %s: %s\n",
                    $error['product_name'],
                    $error['error']
                );
            }
        }
        
        return $note;
    }

    /**
     * Add meta box to order edit page
     */
    public function add_order_meta_box() {
        add_meta_box(
            'iwp-order-sites',
            __('Sites', 'iwp-woo-v2'),
            array($this, 'render_order_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }

    /**
     * Render order meta box
     *
     * @param WP_Post $post
     */
    public function render_order_meta_box($post) {
        $order_id = $post->ID;
        $sites_created = get_post_meta($order_id, '_iwp_sites_created', true);
        $errors = get_post_meta($order_id, '_iwp_creation_errors', true);
        $processed = get_post_meta($order_id, '_iwp_processed', true);
        
        // Check if order has site-enabled products
        $order = wc_get_order($order_id);
        $has_instawp_products = false;
        
        if ($order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $snapshot_slug = get_post_meta($product_id, '_iwp_selected_snapshot', true);
                if (!empty($snapshot_slug)) {
                    $has_instawp_products = true;
                    break;
                }
            }
        }
        
        if (!$has_instawp_products) {
            echo '<p>' . __('No site-enabled products in this order.', 'iwp-woo-v2') . '</p>';
            return;
        }
        
        if (!$processed) {
            // Check if auto-create is disabled globally
            $options = get_option('iwp_options', array());
            $auto_create_enabled = isset($options['auto_create_sites_on_purchase']) ? $options['auto_create_sites_on_purchase'] : 'yes';
            
            if ($auto_create_enabled !== 'yes') {
                // Show Setup Site button
                echo '<div style="margin-bottom: 15px;">';
                echo '<p>' . __('Auto-create is disabled. Click the button below to manually create sites for this order.', 'iwp-woo-v2') . '</p>';
                echo '<button type="button" id="iwp-setup-sites-btn" class="button button-primary" data-order-id="' . esc_attr($order_id) . '">';
                echo __('Setup Sites', 'iwp-woo-v2');
                echo '</button>';
                echo '<span id="iwp-setup-sites-status" style="margin-left: 10px;"></span>';
                echo '</div>';
                
                // Add nonce for security
                wp_nonce_field('iwp_setup_sites_' . $order_id, 'iwp_setup_sites_nonce');
                
                // Add JavaScript for the button
                echo '<script type="text/javascript">
                jQuery(document).ready(function($) {
                    $("#iwp-setup-sites-btn").on("click", function() {
                        var $btn = $(this);
                        var $status = $("#iwp-setup-sites-status");
                        var orderId = $btn.data("order-id");
                        var nonce = $("#iwp_setup_sites_nonce").val();
                        
                        $btn.prop("disabled", true).text("Creating Sites...");
                        $status.html("<span style=\"color: blue;\">Processing...</span>");
                        
                        $.ajax({
                            url: ajaxurl,
                            type: "POST",
                            data: {
                                action: "iwp_setup_sites",
                                order_id: orderId,
                                nonce: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    $status.html("<span style=\"color: green;\">✓ " + response.data.message + "</span>");
                                    // Reload the page after 2 seconds to show created sites
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                } else {
                                    $status.html("<span style=\"color: red;\">✗ " + response.data.message + "</span>");
                                    $btn.prop("disabled", false).text("Setup Sites");
                                }
                            },
                            error: function() {
                                $status.html("<span style=\"color: red;\">✗ An error occurred. Please try again.</span>");
                                $btn.prop("disabled", false).text("Setup Sites");
                            }
                        });
                    });
                });
                </script>';
            } else {
                echo '<p>' . __('Order not yet processed for site creation. Sites will be created automatically when the order is completed.', 'iwp-woo-v2') . '</p>';
            }
            return;
        }
        
        if (!empty($sites_created)) {
            echo '<h4>' . __('Created Sites:', 'iwp-woo-v2') . '</h4>';
            echo '<ul>';
            foreach ($sites_created as $site) {
                echo '<li>';
                echo '<strong>' . esc_html($site['product_name']) . '</strong><br>';
                if (isset($site['site_data']['site_url'])) {
                    echo '<a href="' . esc_url($site['site_data']['site_url']) . '" target="_blank">' . esc_html($site['site_data']['site_url']) . '</a>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        
        if (!empty($errors)) {
            echo '<h4>' . __('Errors:', 'iwp-woo-v2') . '</h4>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li>';
                echo '<strong>' . esc_html($error['product_name']) . '</strong><br>';
                echo '<span style="color: red;">' . esc_html($error['error']) . '</span>';
                echo '</li>';
            }
            echo '</ul>';
        }
        
        if (empty($sites_created) && empty($errors)) {
            echo '<p>' . __('No sites were created for this order.', 'iwp-woo-v2') . '</p>';
        }
    }

    /**
     * Add custom columns to orders list
     *
     * @param array $columns
     * @return array
     */
    public function add_order_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            // Add Sites column after order status
            if ('order_status' === $key) {
                $new_columns['iwp_sites'] = __('Sites', 'iwp-woo-v2');
            }
        }
        
        return $new_columns;
    }

    /**
     * Populate custom order columns
     *
     * @param string $column
     * @param int $post_id
     */
    public function populate_order_columns($column, $post_id) {
        if ('iwp_sites' === $column) {
            $sites_created = get_post_meta($post_id, '_iwp_sites_created', true);
            $errors = get_post_meta($post_id, '_iwp_creation_errors', true);
            
            if (!empty($sites_created)) {
                echo '<span style="color: green;">✓ ' . count($sites_created) . ' ' . __('sites', 'iwp-woo-v2') . '</span>';
            } elseif (!empty($errors)) {
                echo '<span style="color: red;">✗ ' . __('errors', 'iwp-woo-v2') . '</span>';
            } else {
                echo '<span style="color: gray;">-</span>';
            }
        }
    }

    /**
     * Manually create sites for an order (used when auto-create is disabled)
     *
     * @param int $order_id
     * @return array Result array with success and error information
     */
    public function manually_create_sites($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'success' => false,
                'message' => __('Order not found.', 'iwp-woo-v2')
            );
        }

        // Check if already processed
        $processed = get_post_meta($order_id, '_iwp_processed', true);
        if ($processed) {
            return array(
                'success' => false,
                'message' => __('Order has already been processed for site creation.', 'iwp-woo-v2')
            );
        }

        // Temporarily enable auto-create for this manual process
        error_log('IWP WooCommerce V2: Manually creating sites for order: ' . $order_id);
        
        // Call the same processing logic but bypass the global setting check
        $this->process_order_internal($order_id, 'manual');
        
        // Check results
        $sites_created = get_post_meta($order_id, '_iwp_sites_created', true);
        $errors = get_post_meta($order_id, '_iwp_creation_errors', true);
        
        if (!empty($sites_created)) {
            $message = sprintf(
                __('Successfully created %d site(s).', 'iwp-woo-v2'),
                count($sites_created)
            );
            
            if (!empty($errors)) {
                $message .= ' ' . sprintf(
                    __('However, %d error(s) occurred.', 'iwp-woo-v2'),
                    count($errors)
                );
            }
            
            return array(
                'success' => true,
                'message' => $message,
                'sites_created' => $sites_created,
                'errors' => $errors
            );
        } elseif (!empty($errors)) {
            return array(
                'success' => false,
                'message' => __('Site creation failed. Check order notes for details.', 'iwp-woo-v2'),
                'errors' => $errors
            );
        } else {
            return array(
                'success' => false,
                'message' => __('No site-enabled products found in this order.', 'iwp-woo-v2')
            );
        }
    }

    /**
     * Internal processing method that bypasses global auto-create setting
     *
     * @param int $order_id
     * @param string $status
     */
    private function process_order_internal($order_id, $status) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            error_log('IWP WooCommerce V2: Order not found: ' . $order_id);
            return;
        }

        // Skip the processed check for manual creation, but log it
        $processed = get_post_meta($order_id, '_iwp_processed', true);
        if ($processed && $status !== 'manual') {
            error_log('IWP WooCommerce V2: Order already processed: ' . $order_id);
            return;
        }

        $sites_created = array();
        $errors = array();

        // Check if site_id upgrade mode is active
        if (!class_exists('IWP_Frontend')) {
            require_once IWP_PLUGIN_PATH . 'includes/frontend/class-iwp-frontend.php';
        }
        $frontend = new IWP_Frontend();
        $upgrade_site_id = $frontend->get_stored_site_id();
        
        if ($upgrade_site_id) {
            error_log('IWP WooCommerce V2: Site upgrade mode detected for site ID: ' . $upgrade_site_id);
        }

        // Process each item in the order (same logic as process_order)
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if (!$product) {
                error_log('IWP WooCommerce V2: Product not found: ' . $product_id);
                continue;
            }

            // Get snapshot and plan
            $snapshot_slug = get_post_meta($product_id, '_iwp_selected_snapshot', true);
            $plan_id = get_post_meta($product_id, '_iwp_selected_plan', true);
            
            if (empty($snapshot_slug)) {
                error_log('IWP WooCommerce V2: No snapshot selected for product ID: ' . $product->get_id());
                continue;
            }

            // Handle site upgrade mode
            if ($upgrade_site_id && !empty($plan_id)) {
                // Same upgrade logic as in process_order
                $upgrade_result = $this->upgrade_site_plan($order, $product, $upgrade_site_id, $plan_id, $item);
                
                if (is_wp_error($upgrade_result)) {
                    $errors[] = array(
                        'product_id' => $product_id,
                        'product_name' => $product->get_name(),
                        'error' => $upgrade_result->get_error_message()
                    );
                } else {
                    $sites_created[] = array(
                        'product_id' => $product_id,
                        'product_name' => $product->get_name(),
                        'site_data' => $upgrade_result,
                        'action' => 'upgraded'
                    );
                }
                continue;
            }
            
            // Regular site creation
            error_log('IWP WooCommerce V2: Processing product with snapshot slug: ' . $snapshot_slug . ', plan ID: ' . $plan_id);

            $site_result = $this->create_site_for_product($order, $product, $snapshot_slug, $item, $plan_id);
            
            if (is_wp_error($site_result)) {
                $errors[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'error' => $site_result->get_error_message()
                );
            } else {
                $sites_created[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'site_data' => $site_result,
                    'action' => 'created'
                );
            }
        }

        // Store results
        if (!empty($sites_created)) {
            update_post_meta($order_id, '_iwp_sites_created', $sites_created);
        }
        
        if (!empty($errors)) {
            update_post_meta($order_id, '_iwp_creation_errors', $errors);
        }

        // Mark as processed
        update_post_meta($order_id, '_iwp_processed', true);
        update_post_meta($order_id, '_iwp_processed_date', current_time('mysql'));

        // Add order note
        $note = $this->generate_order_note($sites_created, $errors);
        if ($status === 'manual') {
            $note = __('Manually created sites:', 'iwp-woo-v2') . "\n\n" . $note;
        }
        $order->add_order_note($note, 1); // 1 = customer visible

        // Always clear stored site_id after processing order (whether upgrade happened or not)
        if (!class_exists('IWP_Frontend')) {
            require_once IWP_PLUGIN_PATH . 'includes/frontend/class-iwp-frontend.php';
        }
        $frontend = new IWP_Frontend();
        $frontend->clear_stored_site_id();
        error_log('IWP WooCommerce V2: Cleared stored site_id after order processing');

        error_log('IWP WooCommerce V2: Order processing completed for order: ' . $order_id);
    }

    /**
     * Find and reconcile demo sites to the current order
     * Matches by billing email and converts demo sites to paid
     *
     * @param WC_Order $order
     * @return array Array of reconciled site IDs
     */
    private function reconcile_demo_sites_to_order($order, $upgrade_site_id = null) {
        $billing_email = $order->get_billing_email();
        $customer_id = $order->get_customer_id();
        $order_id = $order->get_id();

        IWP_Sites_Model::init();
        $demo_sites = array();

        // FIRST PRIORITY: Check if there's an upgrade_site_id from session (site upgrade flow)
        if ($upgrade_site_id) {
            $existing_site = IWP_Sites_Model::get_by_site_id($upgrade_site_id);

            // If this site is still marked as demo, reconcile it
            if ($existing_site && $existing_site->site_type === 'demo') {
                $demo_sites[] = $existing_site;
                IWP_Logger::info('Found demo site from upgrade session', 'order-processor', array(
                    'site_id' => $upgrade_site_id,
                    'order_id' => $order_id
                ));
            }
        }

        // SECOND PRIORITY: Check order meta for upgraded sites (for completed orders)
        if (empty($demo_sites)) {
            $order_sites = get_post_meta($order_id, '_iwp_sites_created', true);
            if (is_array($order_sites)) {
                foreach ($order_sites as $order_site) {
                    if (isset($order_site['site_data']['action']) && $order_site['site_data']['action'] === 'upgraded') {
                        $site_id = $order_site['site_data']['site_id'];
                        $existing_site = IWP_Sites_Model::get_by_site_id($site_id);

                        // If this site is still marked as demo, reconcile it
                        if ($existing_site && $existing_site->site_type === 'demo') {
                            $demo_sites[] = $existing_site;
                            IWP_Logger::info('Found demo site in order upgrades', 'order-processor', array(
                                'site_id' => $site_id,
                                'order_id' => $order_id
                            ));
                        }
                    }
                }
            }
        }

        // THIRD PRIORITY: Email-based matching for new site creation
        if (empty($demo_sites) && !empty($billing_email)) {
            $demo_sites = IWP_Sites_Model::get_demo_sites_by_email($billing_email);
        }

        if (empty($demo_sites)) {
            IWP_Logger::debug('No demo sites found for reconciliation', 'order-processor', array(
                'email' => $billing_email,
                'order_id' => $order_id
            ));
            return array();
        }

        $reconciled_sites = array();

        foreach ($demo_sites as $demo_site) {
            // Check for subscription ID
            $subscription_id = null;
            if (function_exists('wcs_get_subscriptions_for_order')) {
                $subscriptions = wcs_get_subscriptions_for_order($order_id);
                if (!empty($subscriptions)) {
                    $subscription = reset($subscriptions);
                    $subscription_id = $subscription->get_id();
                }
            }

            // Prepare source_data with subscription info and demo history
            $source_data = json_decode($demo_site->source_data, true);
            if (!is_array($source_data)) {
                $source_data = array();
            }

            // Store original demo information for history
            $source_data['original_demo_expiry_hours'] = $demo_site->expiry_hours;
            $source_data['original_demo_created_at'] = $demo_site->created_at;
            $source_data['original_demo_source'] = $demo_site->source;
            $source_data['subscription_id'] = $subscription_id;
            $source_data['converted_at'] = current_time('mysql');
            $source_data['converted_from'] = 'demo';

            // Convert demo to paid
            $success = IWP_Sites_Model::update($demo_site->site_id, array(
                'site_type' => 'paid',
                'order_id' => $order_id,
                'user_id' => $customer_id,
                'source' => 'demo_to_paid', // Track conversion
                'source_data' => $source_data,
                'expiry_hours' => null,      // Clear expiry - now permanent
                'is_reserved' => true,       // Mark as permanent site
                'updated_at' => current_time('mysql')
            ));

            if ($success) {
                $reconciled_sites[] = $demo_site->site_id;

                IWP_Logger::info('Demo site reconciled to order', 'order-processor', array(
                    'site_id' => $demo_site->site_id,
                    'demo_email' => $billing_email,
                    'order_id' => $order_id,
                    'customer_id' => $customer_id
                ));

                // Note: We don't add reconciled sites to order meta because they're already
                // tracked in the database with updated information. Adding stale data to
                // order meta would cause the admin table to display old values.

                // Disable demo helper plugin if site has one
                $this->disable_demo_helper_for_site($demo_site);
            }
        }

        if (!empty($reconciled_sites)) {
            // Add order note
            $order->add_order_note(
                sprintf(
                    __('Converted %d demo site(s) to paid: %s', 'iwp-wp-integration'),
                    count($reconciled_sites),
                    implode(', ', $reconciled_sites)
                )
            );
        }

        return $reconciled_sites;
    }

    /**
     * Add reconciled demo site to order meta
     *
     * @param int $order_id
     * @param object $demo_site
     */
    private function add_reconciled_site_to_order_meta($order_id, $demo_site) {
        $existing_sites = get_post_meta($order_id, '_iwp_sites_created', true);
        if (!is_array($existing_sites)) {
            $existing_sites = array();
        }

        // Add reconciled demo site to order meta
        $existing_sites[] = array(
            'site_id' => $demo_site->site_id,
            'site_url' => $demo_site->site_url,
            'wp_username' => $demo_site->wp_username,
            'wp_password' => $demo_site->wp_password,
            's_hash' => $demo_site->s_hash,
            'status' => $demo_site->status,
            'action' => 'reconciled', // Mark as reconciled demo
            'site_data' => array(
                'site_id' => $demo_site->site_id,
                'site_url' => $demo_site->site_url,
                'wp_username' => $demo_site->wp_username,
                'wp_password' => $demo_site->wp_password,
                's_hash' => $demo_site->s_hash,
            ),
            'reconciled_at' => current_time('mysql')
        );

        update_post_meta($order_id, '_iwp_sites_created', $existing_sites);
    }

    /**
     * Disable demo helper plugin for converted site
     *
     * @param object $demo_site
     */
    private function disable_demo_helper_for_site($demo_site) {
        if (empty($demo_site->site_url)) {
            return;
        }

        // Get API key from settings
        $options = get_option('iwp_options', array());
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';

        if (empty($api_key)) {
            IWP_Logger::warning('Cannot disable demo helper - no API key', 'order-processor', array(
                'site_id' => $demo_site->site_id
            ));
            return;
        }

        $this->api_client->set_api_key($api_key);
        $result = $this->api_client->disable_demo_helper($demo_site->site_id, $demo_site->site_url);

        if (is_wp_error($result)) {
            IWP_Logger::warning('Failed to disable demo helper', 'order-processor', array(
                'site_id' => $demo_site->site_id,
                'error' => $result->get_error_message()
            ));
        } else {
            IWP_Logger::info('Demo helper disabled for converted site', 'order-processor', array(
                'site_id' => $demo_site->site_id
            ));
        }
    }
}
