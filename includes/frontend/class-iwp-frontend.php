<?php
/**
 * Frontend class for IWP WooCommerce Integration v2
 *
 * @package IWP
 * @since 0.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Frontend class
 */
class IWP_Frontend {

    /**
     * Track displayed orders to prevent duplicates
     *
     * @var array
     */
    private static $displayed_orders = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('woocommerce_before_shop_loop', array($this, 'before_shop_loop'));
        add_action('woocommerce_after_shop_loop', array($this, 'after_shop_loop'));
        add_action('woocommerce_before_single_product', array($this, 'before_single_product'));
        add_action('woocommerce_after_single_product', array($this, 'after_single_product'));
        add_action('woocommerce_before_cart', array($this, 'before_cart'));
        add_action('woocommerce_after_cart', array($this, 'after_cart'));
        add_action('woocommerce_before_checkout_form', array($this, 'before_checkout_form'));
        add_action('woocommerce_after_checkout_form', array($this, 'after_checkout_form'));
        add_filter('woocommerce_product_tabs', array($this, 'add_product_tab'));
        add_shortcode('iwp_info', array($this, 'info_shortcode'));
        
        // Customer order details integration
        // For thank you page (order-received)
        add_action('woocommerce_thankyou', array($this, 'display_order_sites_thankyou'), 10);
        
        // For order view page (view-order) - but not on thank you page
        add_action('woocommerce_view_order', array($this, 'display_order_sites_view'), 10);
        
        // Email integration
        add_action('woocommerce_email_order_details', array($this, 'add_sites_to_emails'), 15, 4);
        
        // My Account dashboard integration
        add_action('woocommerce_account_dashboard', array($this, 'display_customer_sites'), 15);
        
        // Site ID parameter handling
        add_action('init', array($this, 'handle_site_id_parameter'));
        add_action('woocommerce_before_shop_loop', array($this, 'display_site_id_notice'));
        add_action('woocommerce_before_single_product_summary', array($this, 'display_site_id_notice'));
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // Only enqueue on WooCommerce pages
        if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) {
            return;
        }

        wp_enqueue_script(
            'instawp-integration-frontend',
            IWP_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            IWP_VERSION,
            true
        );

        wp_enqueue_style(
            'instawp-integration-frontend',
            IWP_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            IWP_VERSION
        );

        wp_localize_script(
            'instawp-integration-frontend',
            'iwp_frontend',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('iwp_frontend_nonce'),
                'add_domain_nonce' => wp_create_nonce('iwp_add_domain_nonce'),
                'strings' => array(
                    'loading' => esc_html__('Loading...', 'iwp-wp-integration'),
                    'error' => esc_html__('An error occurred. Please try again.', 'iwp-wp-integration'),
                ),
            )
        );
    }

    /**
     * Before shop loop
     */
    public function before_shop_loop() {
        $options = get_option('iwp_options', array());
        
        if (isset($options['enabled']) && $options['enabled'] === 'yes') {
            echo '<div class="instawp-integration-shop-notice">';
            // Removed branding message
            echo '</div>';
        }
    }

    /**
     * After shop loop
     */
    public function after_shop_loop() {
        // Add any content after shop loop if needed
        do_action('iwp_after_shop_loop');
    }

    /**
     * Before single product
     */
    public function before_single_product() {
        global $product;
        
        if (!$product) {
            return;
        }

        $options = get_option('iwp_options', array());
        
        if (isset($options['enabled']) && $options['enabled'] === 'yes') {
            // Add product enhancement notice
            echo '<div class="instawp-integration-product-notice">';
            // Removed branding message
            echo '</div>';
        }
    }

    /**
     * After single product
     */
    public function after_single_product() {
        // Add any content after single product if needed
        do_action('iwp_after_single_product');
    }

    /**
     * Before cart
     */
    public function before_cart() {
        $options = get_option('iwp_options', array());
        
        if (isset($options['enabled']) && $options['enabled'] === 'yes') {
            echo '<div class="instawp-integration-cart-notice">';
            // Removed branding message
            echo '</div>';
        }
    }

    /**
     * After cart
     */
    public function after_cart() {
        // Add any content after cart if needed
        do_action('iwp_after_cart');
    }

    /**
     * Before checkout form
     */
    public function before_checkout_form() {
        $options = get_option('iwp_options', array());
        
        if (isset($options['enabled']) && $options['enabled'] === 'yes') {
            echo '<div class="instawp-integration-checkout-notice">';
            // Removed branding message
            echo '</div>';
        }
    }

    /**
     * After checkout form
     */
    public function after_checkout_form() {
        // Add any content after checkout form if needed
        do_action('iwp_after_checkout_form');
    }

    /**
     * Add product tab
     *
     * @param array $tabs Existing tabs
     * @return array
     */
    public function add_product_tab($tabs) {
        $options = get_option('iwp_options', array());
        
        if (isset($options['enabled']) && $options['enabled'] === 'yes') {
            $tabs['iwp_info'] = array(
                'title' => esc_html__('Site Info', 'iwp-wp-integration'),
                'priority' => 50,
                'callback' => array($this, 'product_tab_content')
            );
        }

        return $tabs;
    }

    /**
     * Product tab content
     */
    public function product_tab_content() {
        echo '<div class="instawp-integration-product-tab">';
        // Removed branding information
        echo '</div>';
    }

    /**
     * Info shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function info_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'basic',
            'show_version' => 'no',
        ), $atts, 'iwp_info');

        $output = '<div class="instawp-integration-info-shortcode">';
        
        switch ($atts['type']) {
            case 'basic':
                // Removed branding message
                $output .= '';
                break;
            case 'detailed':
                $output .= '<div class="instawp-integration-detailed-info">';
                // Removed branding information
                $output .= '';
                
                if ($atts['show_version'] === 'yes') {
                    $output .= '<p><small>' . esc_html__('Version:', 'iwp-wp-integration') . ' ' . esc_html(IWP_VERSION) . '</small></p>';
                }
                
                $output .= '</div>';
                break;
        }
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Get customer data
     *
     * @param int $customer_id Customer ID
     * @return array
     */
    public function get_customer_data($customer_id = 0) {
        if ($customer_id === 0) {
            $customer_id = get_current_user_id();
        }

        if (!$customer_id) {
            return array();
        }

        $customer = new WC_Customer($customer_id);
        
        return array(
            'id' => $customer->get_id(),
            'email' => $customer->get_email(),
            'first_name' => $customer->get_first_name(),
            'last_name' => $customer->get_last_name(),
            'username' => $customer->get_username(),
            'billing_address' => $customer->get_billing(),
            'shipping_address' => $customer->get_shipping(),
            'orders_count' => $customer->get_order_count(),
            'total_spent' => $customer->get_total_spent(),
        );
    }

    /**
     * Get cart data
     *
     * @return array
     */
    public function get_cart_data() {
        if (!WC()->cart) {
            return array();
        }

        $cart_data = array(
            'items' => array(),
            'total' => WC()->cart->get_total(''),
            'subtotal' => WC()->cart->get_subtotal(),
            'tax_total' => WC()->cart->get_total_tax(),
            'shipping_total' => WC()->cart->get_shipping_total(),
            'discount_total' => WC()->cart->get_discount_total(),
            'items_count' => WC()->cart->get_cart_contents_count(),
        );

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            
            $cart_data['items'][] = array(
                'key' => $cart_item_key,
                'product_id' => $cart_item['product_id'],
                'variation_id' => $cart_item['variation_id'],
                'quantity' => $cart_item['quantity'],
                'price' => $product->get_price(),
                'total' => $cart_item['line_total'],
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
            );
        }

        return $cart_data;
    }

    /**
     * Display custom message
     *
     * @param string $message The message to display
     * @param string $type The message type (success, error, warning, info)
     */
    public function display_message($message, $type = 'info') {
        $classes = array(
            'instawp-integration-message',
            'instawp-integration-message-' . $type
        );

        echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
    }

    /**
     * Check if integration is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        $options = get_option('iwp_options', array());
        return isset($options['enabled']) && $options['enabled'] === 'yes';
    }


    /**
     * Display sites on thank you page (order-received)
     *
     * @param int $order_id
     */
    public function display_order_sites_thankyou($order_id) {
        if (!$order_id) {
            return;
        }
        $this->display_order_sites($order_id, 'thank-you');
    }
    
    /**
     * Display sites on order view page (my-account/view-order)
     *
     * @param int $order_id
     */
    public function display_order_sites_view($order_id) {
        if (!$order_id) {
            return;
        }
        $this->display_order_sites($order_id, 'order-view');
    }

    /**
     * Display sites for a specific order
     *
     * @param int $order_id
     * @param string $context
     */
    private function display_order_sites($order_id, $context = 'order-view') {
        if (!$order_id) {
            return;
        }

        // Prevent duplicate displays of the same order in the same context
        $display_key = $order_id . '_' . $context;
        if (isset(self::$displayed_orders[$display_key])) {
            return;
        }
        self::$displayed_orders[$display_key] = true;

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Check if current user can view this order
        if (!current_user_can('view_order', $order_id)) {
            return;
        }

        // Get site manager instance
        if (!class_exists('IWP_Site_Manager')) {
            require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-api-client.php';
            require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-logger.php';
            require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-site-manager.php';
        }
        $site_manager = new IWP_Site_Manager();
        $sites = $site_manager->get_order_sites($order_id);

        if (empty($sites)) {
            return;
        }

        // Determine what types of actions were performed
        $has_created = false;
        $has_upgraded = false;
        foreach ($sites as $site) {
            $action = $site['action'] ?? 'created';
            if ($action === 'created') {
                $has_created = true;
            } elseif ($action === 'upgraded') {
                $has_upgraded = true;
            }
        }
        
        $title = '';
        $description = '';
        
        // Skip section header for upgrade-only orders since individual cards show upgrade info
        $show_section_header = true;
        if ($has_upgraded && !$has_created) {
            $show_section_header = false;
        }
        
        if ($show_section_header) {
            switch ($context) {
                case 'thank-you':
                    if ($has_created && !$has_upgraded) {
                        // Only creations
                        $title = __('Your Site Details', 'iwp-wp-integration');
                        $description = __('Here are your site details:', 'iwp-wp-integration');
                    } else {
                        // Mixed or unknown
                        $title = __('Your Sites', 'iwp-wp-integration');
                        $description = __('Here are your site details:', 'iwp-wp-integration');
                    }
                    break;
                case 'order-details':
                    $title = __('Sites', 'iwp-wp-integration');
                    if ($has_created && !$has_upgraded) {
                        $description = __('Sites created for this order:', 'iwp-wp-integration');
                    } else {
                        $description = __('Sites processed for this order:', 'iwp-wp-integration');
                    }
                    break;
                default:
                    $title = __('Your Sites', 'iwp-wp-integration');
                    $description = __('Sites associated with this order:', 'iwp-wp-integration');
                    break;
            }

            echo '<div class="instawp-integration-customer-sites instawp-integration-context-' . esc_attr($context) . '">';
            echo '<h2 class="iwp-sites-title">' . esc_html($title) . '</h2>';
            echo '<p class="iwp-sites-description">' . esc_html($description) . '</p>';
        } else {
            // For upgrade-only orders, just create the container without header
            echo '<div class="instawp-integration-customer-sites instawp-integration-context-' . esc_attr($context) . ' iwp-no-header">';
        }

        foreach ($sites as $site) {
            $this->render_customer_site_card($site, $context);
        }

        // Add domain mapping modal if this is order details context
        if ($context === 'order-details' || $context === 'order-view' || $context === 'thank-you') {
            $this->render_domain_mapping_modal($order->get_id());
        }

        echo '</div>';
    }

    /**
     * Render a single site card for customers
     *
     * @param array $site
     * @param string $context
     */
    private function render_customer_site_card($site, $context = 'order-view') {
        $status = $site['status'] ?? 'unknown';
        $wp_url = $site['wp_url'] ?? '';
        $wp_username = $site['wp_username'] ?? '';
        $wp_password = $site['wp_password'] ?? '';
        $created_at = $site['created_at'] ?? '';
        $snapshot_slug = $site['snapshot_slug'] ?? '';
        $action = $site['action'] ?? 'created';
        $site_id = $site['site_id'] ?? '';
        $plan_id = $site['plan_id'] ?? '';
        $product_name = $site['product_name'] ?? '';
        $site_name = $site['site_name'] ?? '';
        $wp_admin_url = $site['wp_admin_url'] ?? '';
        $s_hash = $site['s_hash'] ?? '';

        $status_class = 'iwp-status-' . esc_attr($status);
        $action_class = 'iwp-action-' . esc_attr($action);
        $status_text = '';
        $status_icon = '';

        switch ($status) {
            case 'completed':
                $status_text = __('Ready', 'iwp-wp-integration');
                $status_icon = '✅';
                break;
            case 'progress':
                $status_text = __('Creating...', 'iwp-wp-integration');
                $status_icon = '🔄';
                break;
            case 'failed':
                $status_text = __('Failed', 'iwp-wp-integration');
                $status_icon = '❌';
                break;
            default:
                $status_text = __('Unknown', 'iwp-wp-integration');
                $status_icon = '❓';
                break;
        }

        echo '<div class="iwp-site-card ' . esc_attr($status_class) . ' ' . esc_attr($action_class) . '">';
        
        echo '<div class="iwp-site-header">';
        echo '<h3 class="iwp-site-title">';
        echo '<span class="iwp-status-icon">' . $status_icon . '</span> ';
        
        // Show different title based on action
        if ($action === 'upgraded') {
            if ($site_name) {
                echo esc_html(sprintf(__('Upgraded: %s', 'iwp-wp-integration'), $site_name));
            } elseif ($product_name) {
                echo esc_html(sprintf(__('Upgraded: %s', 'iwp-wp-integration'), $product_name));
            } else {
                echo esc_html(__('Site Upgraded', 'iwp-wp-integration'));
            }
        } else {
            if ($site_name) {
                echo esc_html(sprintf(__('Site: %s', 'iwp-wp-integration'), $site_name));
            } elseif ($snapshot_slug) {
                echo esc_html(sprintf(__('Site: %s', 'iwp-wp-integration'), $snapshot_slug));
            } else {
                echo esc_html(__('Site', 'iwp-wp-integration'));
            }
        }
        
        echo '</h3>';
        echo '<span class="iwp-site-status">' . esc_html($status_text) . '</span>';
        echo '</div>';

        echo '<div class="iwp-site-content">';

        if ($status === 'completed' && !empty($wp_url)) {
            echo '<div class="iwp-site-details">';
            
            // Show upgrade-specific information
            if ($action === 'upgraded') {
                echo '<div class="iwp-upgrade-info">';
                echo '<div class="iwp-upgrade-badge">';
                echo '<span class="iwp-upgrade-icon">🔄</span>';
                echo '<strong>' . __('Plan Upgraded', 'iwp-wp-integration') . '</strong>';
                echo '</div>';
                
                if ($site_id) {
                    echo '<div class="iwp-site-meta-row">';
                    echo '<strong>' . __('Site ID:', 'iwp-wp-integration') . '</strong> ' . esc_html($site_id);
                    echo '</div>';
                }
                
                if ($plan_id) {
                    echo '<div class="iwp-site-meta-row">';
                    echo '<strong>' . __('New Plan:', 'iwp-wp-integration') . '</strong> ' . esc_html($plan_id);
                    echo '</div>';
                }
                echo '</div>';
            }
            
            echo '<div class="iwp-site-url">';
            echo '<strong>' . __('Site URL:', 'iwp-wp-integration') . '</strong> ';
            echo '<a href="' . esc_url($wp_url) . '" target="_blank" rel="noopener">' . esc_html($wp_url) . '</a>';
            echo '</div>';

            if (!empty($wp_username)) {
                echo '<div class="iwp-site-credentials">';
                echo '<div class="iwp-credential-row">';
                echo '<strong>' . __('Username:', 'iwp-wp-integration') . '</strong> ';
                echo '<code class="iwp-credential-value">' . esc_html($wp_username) . '</code>';
                echo '<button type="button" class="iwp-copy-btn" data-copy="' . esc_attr($wp_username) . '" title="' . esc_attr__('Copy to clipboard', 'iwp-wp-integration') . '">📋</button>';
                echo '</div>';

                if (!empty($wp_password)) {
                    echo '<div class="iwp-credential-row">';
                    echo '<strong>' . __('Password:', 'iwp-wp-integration') . '</strong> ';
                    echo '<code class="iwp-credential-value iwp-password-hidden" data-password="' . esc_attr($wp_password) . '">••••••••</code>';
                    echo '<button type="button" class="iwp-show-password-btn" title="' . esc_attr__('Show/Hide password', 'iwp-wp-integration') . '">👁️</button>';
                    echo '<button type="button" class="iwp-copy-btn" data-copy="' . esc_attr($wp_password) . '" title="' . esc_attr__('Copy to clipboard', 'iwp-wp-integration') . '">📋</button>';
                    echo '</div>';
                }
                echo '</div>';
            }

            echo '<div class="iwp-site-actions">';
            echo '<a href="' . esc_url($wp_url) . '" target="_blank" rel="noopener" class="iwp-btn iwp-btn-primary">' . __('Visit Site', 'iwp-wp-integration') . '</a>';
            
            // Use magic login if s_hash is available, otherwise fall back to regular admin login
            if (!empty($s_hash)) {
                $magic_login_url = 'https://app.instawp.io/wordpress-auto-login?site=' . urlencode($s_hash);
                echo '<a href="' . esc_url($magic_login_url) . '" target="_blank" rel="noopener" class="iwp-btn iwp-btn-secondary">' . __('Magic Login', 'iwp-wp-integration') . '</a>';
            } else {
                // Fallback to regular admin login
                $admin_url = '';
                if (!empty($wp_admin_url)) {
                    $admin_url = $wp_admin_url;
                } elseif (!empty($wp_url)) {
                    $admin_url = trailingslashit($wp_url) . 'wp-admin';
                }
                
                if (!empty($admin_url)) {
                    echo '<a href="' . esc_url($admin_url) . '" target="_blank" rel="noopener" class="iwp-btn iwp-btn-secondary">' . __('Admin Login', 'iwp-wp-integration') . '</a>';
                }
            }
            
            // Add domain mapping button if site_id is available
            if (!empty($site_id) && ($context === 'order-details' || $context === 'order-view' || $context === 'thank-you')) {
                echo '<button type="button" class="iwp-btn iwp-btn-tertiary iwp-map-domain-btn" data-site-id="' . esc_attr($site_id) . '" data-site-url="' . esc_attr($wp_url) . '">' . __('Map Domain', 'iwp-wp-integration') . '</button>';
            }
            echo '</div>';
            echo '</div>';

        } elseif ($status === 'progress' || $status === 'creating') {
            echo '<div class="iwp-site-progress">';
            echo '<p>' . __('Your site is being created. This usually takes a few minutes. Please refresh this page to check the latest status.', 'iwp-wp-integration') . '</p>';
            echo '<button type="button" class="iwp-btn iwp-btn-secondary" onclick="location.reload()">' . __('Refresh Status', 'iwp-wp-integration') . '</button>';
            echo '</div>';

        } elseif ($status === 'completed' && $action === 'upgraded' && empty($wp_url)) {
            // Handle upgraded sites without complete details
            echo '<div class="iwp-upgrade-minimal">';
            echo '<div class="iwp-upgrade-badge">';
            echo '<span class="iwp-upgrade-icon">🔄</span>';
            echo '<strong>' . __('Plan Successfully Upgraded', 'iwp-wp-integration') . '</strong>';
            echo '</div>';
            
            echo '<div class="iwp-upgrade-details">';
            if ($site_id) {
                echo '<p><strong>' . __('Site ID:', 'iwp-wp-integration') . '</strong> ' . esc_html($site_id) . '</p>';
            }
            if ($plan_id) {
                echo '<p><strong>' . __('New Plan:', 'iwp-wp-integration') . '</strong> ' . esc_html($plan_id) . '</p>';
            }
            if ($product_name) {
                echo '<p><strong>' . __('Product:', 'iwp-wp-integration') . '</strong> ' . esc_html($product_name) . '</p>';
            }
            echo '<p>' . __('Your site plan has been successfully upgraded. The site continues to operate with the new plan features.', 'iwp-wp-integration') . '</p>';
            echo '</div>';
            echo '</div>';
            
        } elseif ($status === 'failed') {
            echo '<div class="iwp-site-error">';
            echo '<p>' . __('Sorry, there was an issue creating your site. Please contact support for assistance.', 'iwp-wp-integration') . '</p>';
            echo '</div>';
        }

        if (!empty($created_at)) {
            echo '<div class="iwp-site-meta">';
            if ($action === 'upgraded') {
                echo '<small>' . sprintf(__('Upgraded: %s', 'iwp-wp-integration'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($created_at))) . '</small>';
            } else {
                echo '<small>' . sprintf(__('Created: %s', 'iwp-wp-integration'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($created_at))) . '</small>';
            }
            echo '</div>';
        }

        echo '</div>'; // iwp-site-content
        echo '</div>'; // iwp-site-card
    }

    /**
     * Render domain mapping modal
     *
     * @param int $order_id
     */
    private function render_domain_mapping_modal($order_id) {
        ?>
        <!-- Domain Mapping Modal -->
        <div id="iwp-domain-modal" class="iwp-modal" style="display: none;">
            <div class="iwp-modal-content">
                <div class="iwp-modal-header">
                    <h3><?php _e('Map Custom Domain', 'iwp-wp-integration'); ?></h3>
                    <span class="iwp-modal-close">&times;</span>
                </div>
                <div class="iwp-modal-body">
                    <div class="iwp-domain-instructions">
                        <p><?php _e('Create a CNAME record in your DNS settings:', 'iwp-wp-integration'); ?></p>
                        <div class="iwp-cname-example">
                            <code>
                                <span class="iwp-domain-placeholder"><?php _e('your-domain.com', 'iwp-wp-integration'); ?></span> → 
                                <span class="iwp-target-url"></span>
                            </code>
                        </div>
                    </div>
                    
                    <form id="iwp-domain-form">
                        <div class="iwp-form-group">
                            <label for="iwp-domain-name"><?php _e('Domain Name:', 'iwp-wp-integration'); ?></label>
                            <input type="text" id="iwp-domain-name" name="domain_name" placeholder="example.com or www.example.com" required>
                            <small><?php _e('Enter without http:// or https://', 'iwp-wp-integration'); ?></small>
                        </div>
                        
                        <div class="iwp-form-group">
                            <label for="iwp-domain-type"><?php _e('Domain Type:', 'iwp-wp-integration'); ?></label>
                            <select id="iwp-domain-type" name="domain_type">
                                <option value="primary"><?php _e('Primary (main domain)', 'iwp-wp-integration'); ?></option>
                                <option value="alias"><?php _e('Alias (redirects to primary)', 'iwp-wp-integration'); ?></option>
                            </select>
                            <small><?php _e('Use Primary for your main domain, Alias for www version', 'iwp-wp-integration'); ?></small>
                        </div>
                        
                        
                        <input type="hidden" id="iwp-modal-site-id" name="site_id" value="">
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('iwp_add_domain_nonce'); ?>">
                        
                        <div class="iwp-form-actions">
                            <button type="button" class="iwp-btn iwp-btn-secondary iwp-modal-cancel"><?php _e('Cancel', 'iwp-wp-integration'); ?></button>
                            <button type="submit" class="iwp-btn iwp-btn-primary"><?php _e('Map Domain', 'iwp-wp-integration'); ?></button>
                        </div>
                    </form>
                    
                    <div id="iwp-domain-result" style="display: none;"></div>
                </div>
            </div>
        </div>
        
        <!-- Display existing mapped domains -->
        <?php
        $mapped_domains = get_post_meta($order_id, '_iwp_mapped_domains', true);
        if (is_array($mapped_domains) && !empty($mapped_domains)) {
            echo '<div class="iwp-existing-domains">';
            echo '<h4>' . __('Mapped Domains:', 'iwp-wp-integration') . '</h4>';
            echo '<div class="iwp-domains-list">';
            foreach ($mapped_domains as $domain) {
                echo '<div class="iwp-domain-item">';
                echo '<span class="iwp-domain-name">' . esc_html($domain['domain_name']) . '</span>';
                echo '<span class="iwp-domain-type iwp-domain-type-' . esc_attr($domain['domain_type']) . '">' . esc_html(ucfirst($domain['domain_type'])) . '</span>';
                echo '<small class="iwp-domain-date">' . sprintf(__('Added: %s', 'iwp-wp-integration'), date_i18n(get_option('date_format'), strtotime($domain['mapped_at']))) . '</small>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Add sites to order emails
     *
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     * @param WC_Email $email
     */
    public function add_sites_to_emails($order, $sent_to_admin, $plain_text, $email) {
        // Only add to customer emails, not admin emails
        if ($sent_to_admin) {
            return;
        }

        // Only add to completed order emails and customer invoice emails
        if (!in_array($email->id, array('customer_completed_order', 'customer_invoice', 'customer_processing_order'))) {
            return;
        }

        $site_manager = new IWP_Site_Manager();
        $sites = $site_manager->get_order_sites($order->get_id());

        if (empty($sites)) {
            return;
        }

        if ($plain_text) {
            echo "\n" . __('YOUR SITES', 'iwp-wp-integration') . "\n";
            echo str_repeat('=', 50) . "\n\n";

            foreach ($sites as $site) {
                $status = $site['status'] ?? 'unknown';
                $wp_url = $site['wp_url'] ?? '';
                $wp_username = $site['wp_username'] ?? '';
                $wp_password = $site['wp_password'] ?? '';
                $s_hash = $site['s_hash'] ?? '';
                $snapshot_slug = $site['snapshot_slug'] ?? '';

                echo sprintf(__('Site: %s', 'iwp-wp-integration'), $snapshot_slug ?: __('Site', 'iwp-wp-integration')) . "\n";
                echo sprintf(__('Status: %s', 'iwp-wp-integration'), ucfirst($status)) . "\n";

                if ($status === 'completed' && !empty($wp_url)) {
                    echo sprintf(__('URL: %s', 'iwp-wp-integration'), $wp_url) . "\n";
                    if (!empty($wp_username)) {
                        echo sprintf(__('Username: %s', 'iwp-wp-integration'), $wp_username) . "\n";
                    }
                    if (!empty($wp_password)) {
                        echo sprintf(__('Password: %s', 'iwp-wp-integration'), $wp_password) . "\n";
                    }
                    // Use magic login if s_hash is available
                    if (!empty($s_hash)) {
                        echo sprintf(__('Magic Login: %s', 'iwp-wp-integration'), 'https://app.instawp.io/wordpress-auto-login?site=' . urlencode($s_hash)) . "\n";
                    } else {
                        echo sprintf(__('Admin URL: %s', 'iwp-wp-integration'), trailingslashit($wp_url) . 'wp-admin') . "\n";
                    }
                } elseif ($status === 'progress') {
                    echo __('Your site is being created. You will receive another email when it\'s ready.', 'iwp-wp-integration') . "\n";
                }
                echo "\n" . str_repeat('-', 30) . "\n\n";
            }
        } else {
            echo '<div class="instawp-integration-email-sites" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">';
            echo '<h2 style="color: #333; margin-top: 0;">' . __('Your Sites', 'iwp-wp-integration') . '</h2>';

            foreach ($sites as $site) {
                $status = $site['status'] ?? 'unknown';
                $wp_url = $site['wp_url'] ?? '';
                $wp_username = $site['wp_username'] ?? '';
                $wp_password = $site['wp_password'] ?? '';
                $s_hash = $site['s_hash'] ?? '';
                $snapshot_slug = $site['snapshot_slug'] ?? '';

                echo '<div style="margin: 15px 0; padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #0073aa;">';
                echo '<h3 style="margin-top: 0; color: #333;">' . esc_html($snapshot_slug ?: __('Site', 'iwp-wp-integration')) . '</h3>';

                if ($status === 'completed' && !empty($wp_url)) {
                    echo '<p><strong>' . __('Site URL:', 'iwp-wp-integration') . '</strong> <a href="' . esc_url($wp_url) . '" target="_blank">' . esc_html($wp_url) . '</a></p>';
                    if (!empty($wp_username)) {
                        echo '<p><strong>' . __('Username:', 'iwp-wp-integration') . '</strong> <code style="background: #f1f1f1; padding: 2px 4px;">' . esc_html($wp_username) . '</code></p>';
                    }
                    if (!empty($wp_password)) {
                        echo '<p><strong>' . __('Password:', 'iwp-wp-integration') . '</strong> <code style="background: #f1f1f1; padding: 2px 4px;">' . esc_html($wp_password) . '</code></p>';
                    }
                    
                    // Use magic login if s_hash is available
                    if (!empty($s_hash)) {
                        $magic_login_url = 'https://app.instawp.io/wordpress-auto-login?site=' . urlencode($s_hash);
                        echo '<p><a href="' . esc_url($magic_login_url) . '" target="_blank" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block;">' . __('Magic Login', 'iwp-wp-integration') . '</a></p>';
                    } else {
                        echo '<p><a href="' . esc_url(trailingslashit($wp_url) . 'wp-admin') . '" target="_blank" style="background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block;">' . __('Login to Admin', 'iwp-wp-integration') . '</a></p>';
                    }
                } elseif ($status === 'progress') {
                    echo '<p style="color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px;">' . __('Your site is being created. You will receive another email when it\'s ready.', 'iwp-wp-integration') . '</p>';
                } elseif ($status === 'failed') {
                    echo '<p style="color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px;">' . __('There was an issue creating your site. Please contact support.', 'iwp-wp-integration') . '</p>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
    }

    /**
     * Display customer's sites on My Account dashboard
     */
    public function display_customer_sites() {
        $customer_id = get_current_user_id();
        if (!$customer_id) {
            return;
        }

        // Get customer's orders with sites
        $orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'limit' => -1,
            'status' => array('completed', 'processing'),
            'meta_query' => array(
                array(
                    'key' => '_iwp_created_sites',
                    'compare' => 'EXISTS'
                )
            )
        ));

        if (empty($orders)) {
            return;
        }

        $site_manager = new IWP_Site_Manager();
        $all_sites = array();

        foreach ($orders as $order) {
            $sites = $site_manager->get_order_sites($order->get_id());
            if (!empty($sites)) {
                foreach ($sites as $site) {
                    $site['order_id'] = $order->get_id();
                    $site['order_number'] = $order->get_order_number();
                    $all_sites[] = $site;
                }
            }
        }

        if (empty($all_sites)) {
            return;
        }

        echo '<div class="instawp-integration-dashboard-sites">';
        echo '<h2>' . __('Your Sites', 'iwp-wp-integration') . '</h2>';
        echo '<p>' . sprintf(_n('You have %d site:', 'You have %d sites:', count($all_sites), 'iwp-wp-integration'), count($all_sites)) . '</p>';

        echo '<div class="iwp-sites-grid">';
        foreach ($all_sites as $site) {
            $this->render_dashboard_site_card($site);
        }
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render a site card for the dashboard
     *
     * @param array $site
     */
    private function render_dashboard_site_card($site) {
        $status = $site['status'] ?? 'unknown';
        $wp_url = $site['wp_url'] ?? '';
        $snapshot_slug = $site['snapshot_slug'] ?? '';
        $order_id = $site['order_id'] ?? '';
        $order_number = $site['order_number'] ?? '';

        $status_class = 'iwp-status-' . esc_attr($status);

        echo '<div class="iwp-dashboard-site-card ' . esc_attr($status_class) . '">';
        echo '<div class="iwp-site-info">';
        echo '<h4>' . esc_html($snapshot_slug ?: __('Site', 'iwp-wp-integration')) . '</h4>';
        echo '<p class="iwp-order-info">' . sprintf(__('From Order #%s', 'iwp-wp-integration'), $order_number) . '</p>';
        echo '</div>';

        echo '<div class="iwp-site-actions">';
        if ($status === 'completed' && !empty($wp_url)) {
            echo '<a href="' . esc_url($wp_url) . '" target="_blank" class="iwp-btn iwp-btn-sm">' . __('Visit Site', 'iwp-wp-integration') . '</a>';
        }
        if ($order_id) {
            echo '<a href="' . esc_url(wc_get_endpoint_url('view-order', $order_id, wc_get_page_permalink('myaccount'))) . '" class="iwp-btn iwp-btn-sm iwp-btn-secondary">' . __('View Details', 'iwp-wp-integration') . '</a>';
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Handle site_id parameter from URL
     */
    public function handle_site_id_parameter() {
        // Check if site_id parameter functionality is enabled
        $options = get_option('iwp_options', array());
        if (!isset($options['use_site_id_parameter']) || $options['use_site_id_parameter'] !== 'yes') {
            return;
        }

        // Check if site_id parameter is present in the URL
        if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
            $site_id = sanitize_text_field($_GET['site_id']);
            
            // Validate site_id format (should be numeric)
            if (is_numeric($site_id) && intval($site_id) > 0) {
                // Store site_id in session for persistence across pages
                if (!session_id()) {
                    session_start();
                }
                $_SESSION['iwp_site_id_for_upgrade'] = intval($site_id);
                
                // Also store in cookie as backup (expires in 24 hours)
                setcookie('iwp_site_id_for_upgrade', intval($site_id), time() + (24 * 60 * 60), '/');
                
                // Redirect to clean URL (remove site_id parameter from URL)
                if (is_shop() || is_product_category() || is_product_tag() || is_product()) {
                    $clean_url = remove_query_arg('site_id');
                    wp_safe_redirect($clean_url);
                    exit;
                }
            }
        }
    }

    /**
     * Get stored site_id for upgrade
     *
     * @return int|null
     */
    public function get_stored_site_id() {
        $options = get_option('iwp_options', array());
        if (!isset($options['use_site_id_parameter']) || $options['use_site_id_parameter'] !== 'yes') {
            return null;
        }

        // Check session first
        if (!session_id()) {
            session_start();
        }
        if (isset($_SESSION['iwp_site_id_for_upgrade']) && is_numeric($_SESSION['iwp_site_id_for_upgrade'])) {
            return intval($_SESSION['iwp_site_id_for_upgrade']);
        }

        // Check cookie as backup
        if (isset($_COOKIE['iwp_site_id_for_upgrade']) && is_numeric($_COOKIE['iwp_site_id_for_upgrade'])) {
            return intval($_COOKIE['iwp_site_id_for_upgrade']);
        }

        return null;
    }

    /**
     * Clear stored site_id
     */
    public function clear_stored_site_id() {
        // Clear session
        if (!session_id()) {
            session_start();
        }
        unset($_SESSION['iwp_site_id_for_upgrade']);

        // Clear cookie
        setcookie('iwp_site_id_for_upgrade', '', time() - 3600, '/');
    }

    /**
     * Display site_id notice on shop and product pages
     */
    public function display_site_id_notice() {
        $site_id = $this->get_stored_site_id();
        if (!$site_id) {
            return;
        }

        // Only show on shop and product pages
        if (!is_shop() && !is_product_category() && !is_product_tag() && !is_product()) {
            return;
        }

        echo '<div class="instawp-integration-notice instawp-integration-site-id-notice" style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin: 15px 0;">';
        echo '<p style="margin: 0; color: #333;">';
        echo '<strong>' . esc_html__('Site Upgrade Mode Active', 'iwp-wp-integration') . '</strong><br>';
        echo sprintf(
            esc_html__('You are upgrading site ID: %s. Products purchased will upgrade this existing site instead of creating a new one.', 'iwp-wp-integration'),
            '<code>' . esc_html($site_id) . '</code>'
        );
        echo ' <a href="' . esc_url(add_query_arg('iwp_clear_site_id', '1')) . '" style="color: #0073aa; text-decoration: underline;">' . esc_html__('Cancel upgrade mode', 'iwp-wp-integration') . '</a>';
        echo '</p>';
        echo '</div>';

        // Handle clear site_id request
        if (isset($_GET['iwp_clear_site_id']) && $_GET['iwp_clear_site_id'] === '1') {
            $this->clear_stored_site_id();
            wp_safe_redirect(remove_query_arg('iwp_clear_site_id'));
            exit;
        }
    }
}