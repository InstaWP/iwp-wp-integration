<?php
/**
 * Post-Purchase Onboarding
 *
 * Provides the [iwp_onboarding] shortcode that collects customer credentials
 * after purchase and triggers deferred site creation.
 *
 * @package IWP
 * @since 0.0.10
 */

defined('ABSPATH') || exit;

class IWP_Onboarding {

    public function __construct() {
        add_shortcode('iwp_onboarding', array($this, 'render_shortcode'));

        add_action('wp_ajax_iwp_onboarding_create_site', array($this, 'handle_create_site'));
        add_action('wp_ajax_nopriv_iwp_onboarding_create_site', array($this, 'handle_create_site'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Redirect thank-you page to onboarding page when deferred items exist
        add_action('template_redirect', array($this, 'maybe_redirect_to_onboarding'));

        // Ensure shortcodes render inside Kadence Conversions popups
        add_filter('kadence_conversions_output', 'do_shortcode', 8);
    }

    // ------------------------------------------------------------------
    // Shortcode
    // ------------------------------------------------------------------

    /**
     * Render the [iwp_onboarding] shortcode
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect' => '', // URL to redirect to after site creation
        ), $atts, 'iwp_onboarding');

        $order = $this->get_order_from_context();

        if (!$order) {
            if (is_user_logged_in()) {
                return '<div class="iwp-onboarding-empty">' .
                    esc_html__('No pending site setup found.', 'iwp-wp-integration') .
                    '</div>';
            }
            return '<div class="iwp-onboarding-empty">' .
                esc_html__('Please log in or use the link from your order confirmation email to set up your site.', 'iwp-wp-integration') .
                '</div>';
        }

        $order_id       = $order->get_id();
        $deferred_items = get_post_meta($order_id, '_iwp_deferred_items', true);
        $sites_created  = get_post_meta($order_id, '_iwp_sites_created', true);
        $redirect_url   = !empty($atts['redirect']) ? esc_url($atts['redirect']) : '';

        ob_start();
        echo '<div class="iwp-onboarding-wrap" data-order-id="' . esc_attr($order_id) . '" data-order-key="' . esc_attr($order->get_order_key()) . '"' . ($redirect_url ? ' data-redirect="' . esc_attr($redirect_url) . '"' : '') . '>';

        // Show already-created sites (from auto-create or previous onboarding)
        if (!empty($sites_created) && is_array($sites_created)) {
            $this->render_completed_sites($sites_created);
        }

        // Show credential form for each deferred item
        if (!empty($deferred_items) && is_array($deferred_items)) {
            $this->render_onboarding_form($order, $deferred_items);
        } elseif (empty($sites_created)) {
            echo '<div class="iwp-onboarding-empty">' .
                esc_html__('No pending site setup found for this order.', 'iwp-wp-integration') .
                '</div>';
        }

        echo '</div>';

        // Hidden fields for JS
        wp_nonce_field('iwp_onboarding_nonce', 'iwp_onboarding_nonce');

        return ob_get_clean();
    }

    // ------------------------------------------------------------------
    // Rendering helpers
    // ------------------------------------------------------------------

    /**
     * Render completed sites section
     */
    private function render_completed_sites($sites) {
        foreach ($sites as $site) {
            $site_data = isset($site['site_data']) ? $site['site_data'] : array();
            $site_url  = $site_data['site_url'] ?? '';
            $username  = $site_data['wp_username'] ?? ($site_data['site_credentials']['admin_username'] ?? '');
            $password  = $site_data['wp_password'] ?? '';
            $s_hash    = $site_data['s_hash'] ?? '';
            $product   = $site['product_name'] ?? '';

            echo '<div class="iwp-onboarding-site-card iwp-onboarding-completed">';
            echo '<h3>' . esc_html($product) . ' &mdash; ' . esc_html__('Site Ready', 'iwp-wp-integration') . '</h3>';

            if ($site_url) {
                echo '<div class="iwp-onboarding-detail"><strong>' . esc_html__('URL:', 'iwp-wp-integration') . '</strong> ';
                echo '<a href="' . esc_url($site_url) . '" target="_blank">' . esc_html($site_url) . '</a></div>';
            }

            if ($username) {
                echo '<div class="iwp-onboarding-detail"><strong>' . esc_html__('Username:', 'iwp-wp-integration') . '</strong> ';
                echo '<span class="iwp-onboarding-credential">' . esc_html($username) . '</span>';
                echo ' <button type="button" class="iwp-onboarding-copy-btn" data-copy-text="' . esc_attr($username) . '">' . esc_html__('Copy', 'iwp-wp-integration') . '</button>';
                echo '</div>';
            }

            if ($password) {
                echo '<div class="iwp-onboarding-detail"><strong>' . esc_html__('Password:', 'iwp-wp-integration') . '</strong> ';
                echo '<span class="iwp-onboarding-password-hidden">' . esc_html(str_repeat('*', 8)) . '</span>';
                echo '<span class="iwp-onboarding-password-value" style="display:none;">' . esc_html($password) . '</span>';
                echo ' <button type="button" class="iwp-onboarding-toggle-pw">' . esc_html__('Show', 'iwp-wp-integration') . '</button>';
                echo ' <button type="button" class="iwp-onboarding-copy-btn" data-copy-text="' . esc_attr($password) . '">' . esc_html__('Copy', 'iwp-wp-integration') . '</button>';
                echo '</div>';
            }

            if ($s_hash) {
                $magic_url = IWP_PLUGIN_APP_URL . '/wordpress-auto-login?site=' . urlencode($s_hash);
                echo '<div class="iwp-onboarding-actions">';
                echo '<a href="' . esc_url($magic_url) . '" target="_blank" class="iwp-onboarding-btn iwp-onboarding-btn-primary">' . esc_html__('Magic Login', 'iwp-wp-integration') . '</a>';
                echo '</div>';
            }

            echo '</div>';
        }
    }

    /**
     * Render credential form for deferred items
     */
    private function render_onboarding_form($order, $deferred_items) {
        foreach ($deferred_items as $index => $item) {
            $product_name = esc_html($item['product_name']);
            $form_id      = 'iwp-onboarding-form-' . intval($index);

            echo '<div class="iwp-onboarding-site-card iwp-onboarding-pending" id="iwp-onboarding-item-' . intval($index) . '">';
            echo '<h3>' . $product_name . ' &mdash; ' . esc_html__('Set Up Your Site', 'iwp-wp-integration') . '</h3>';
            echo '<p class="iwp-onboarding-description">' . esc_html__('Choose your WordPress admin username and site subdomain below. Both are optional — leave blank for auto-generated values.', 'iwp-wp-integration') . '</p>';

            echo '<form class="iwp-onboarding-form" id="' . esc_attr($form_id) . '" data-item-index="' . intval($index) . '">';

            // Username
            echo '<div class="iwp-onboarding-field">';
            echo '<label for="' . esc_attr($form_id) . '-username">' . esc_html__('WordPress Admin Username', 'iwp-wp-integration') . '</label>';
            echo '<input type="text" id="' . esc_attr($form_id) . '-username" name="admin_username" maxlength="20" placeholder="' . esc_attr__('e.g. john_doe', 'iwp-wp-integration') . '" autocomplete="off" />';
            echo '<span class="iwp-onboarding-field-hint">' . esc_html__('3-20 characters, letters, numbers, underscores only.', 'iwp-wp-integration') . '</span>';
            echo '<span class="iwp-onboarding-field-error" style="display:none;"></span>';
            echo '</div>';

            // Subdomain
            echo '<div class="iwp-onboarding-field">';
            echo '<label for="' . esc_attr($form_id) . '-subdomain">' . esc_html__('Site Subdomain', 'iwp-wp-integration') . '</label>';
            echo '<input type="text" id="' . esc_attr($form_id) . '-subdomain" name="subdomain" maxlength="30" placeholder="' . esc_attr__('e.g. my-store', 'iwp-wp-integration') . '" autocomplete="off" />';
            echo '<span class="iwp-onboarding-field-hint">' . esc_html__('3-30 characters, letters, numbers, hyphens only.', 'iwp-wp-integration') . '</span>';
            echo '<span class="iwp-onboarding-field-error" style="display:none;"></span>';
            echo '</div>';

            echo '<div class="iwp-onboarding-form-actions">';
            echo '<button type="submit" class="iwp-onboarding-btn iwp-onboarding-btn-primary iwp-onboarding-submit">' . esc_html__('Set Up My Site', 'iwp-wp-integration') . '</button>';
            echo '</div>';

            // Progress area
            echo '<div class="iwp-onboarding-progress" style="display:none;">';
            echo '<div class="iwp-onboarding-progress-message"></div>';
            echo '<div class="iwp-onboarding-progress-bar-wrap"><div class="iwp-onboarding-progress-bar"></div></div>';
            echo '</div>';

            // Results area (filled by JS after creation)
            echo '<div class="iwp-onboarding-results" style="display:none;"></div>';

            echo '</form>';
            echo '</div>';
        }
    }

    // ------------------------------------------------------------------
    // AJAX — create site
    // ------------------------------------------------------------------

    /**
     * Handle AJAX site creation from the onboarding page
     */
    public function handle_create_site() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iwp_onboarding_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page.', 'iwp-wp-integration')));
        }

        $order_id   = absint($_POST['order_id'] ?? 0);
        $order_key  = sanitize_text_field($_POST['order_key'] ?? '');
        $item_index = absint($_POST['item_index'] ?? 0);
        $username   = sanitize_text_field($_POST['admin_username'] ?? '');
        $subdomain  = sanitize_text_field($_POST['subdomain'] ?? '');

        // Validate order access
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found.', 'iwp-wp-integration')));
        }

        if (!$this->can_access_order($order, $order_key)) {
            wp_send_json_error(array('message' => __('You do not have permission to access this order.', 'iwp-wp-integration')));
        }

        // Get deferred items
        $deferred_items = get_post_meta($order_id, '_iwp_deferred_items', true);
        if (empty($deferred_items) || !isset($deferred_items[$item_index])) {
            wp_send_json_error(array('message' => __('No pending site setup found for this item.', 'iwp-wp-integration')));
        }

        $item = $deferred_items[$item_index];

        // Validate optional fields
        if (!empty($username) && !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            wp_send_json_error(array('message' => __('Username must be 3-20 characters: letters, numbers, underscores.', 'iwp-wp-integration')));
        }
        if (!empty($subdomain) && !preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{1,28})[a-zA-Z0-9]$/', $subdomain)) {
            wp_send_json_error(array('message' => __('Subdomain must be 3-30 characters: letters, numbers, hyphens (no leading/trailing hyphen).', 'iwp-wp-integration')));
        }

        // Build site data
        $snapshot_slug = $item['snapshot_slug'];
        $plan_id       = $item['plan_id'] ?? '';
        $product_id    = $item['product_id'];
        $variation_id  = $item['variation_id'] ?? 0;

        $billing_email      = $order->get_billing_email();
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name  = $order->get_billing_last_name();

        // Site name from subdomain or auto-generated
        if (!empty($subdomain)) {
            $site_name = sanitize_title($subdomain);
        } else {
            $product = wc_get_product($product_id);
            $product_label = $product ? $product->get_name() : 'site';
            $suffix = '-' . $order_id . '-' . substr(time(), -5);
            $max_name_len = 30 - strlen($suffix);
            $site_name = substr(sanitize_title($product_label), 0, $max_name_len) . $suffix;
        }

        // Admin username
        if (empty($username)) {
            $username = sanitize_user($billing_first_name . $billing_last_name);
            $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
            if (strlen($username) < 3) {
                $username = 'user_' . wp_rand(10000, 99999);
            }
        }

        // Expiry settings — check variation first, then parent
        $expiry_type  = '';
        $expiry_hours = '';
        if ($variation_id) {
            $expiry_type  = get_post_meta($variation_id, '_iwp_site_expiry_type', true);
            $expiry_hours = get_post_meta($variation_id, '_iwp_site_expiry_hours', true);
        }
        if (empty($expiry_type)) {
            $expiry_type = get_post_meta($product_id, '_iwp_site_expiry_type', true);
        }
        if (empty($expiry_hours)) {
            $expiry_hours = get_post_meta($product_id, '_iwp_site_expiry_hours', true);
        }
        if (empty($expiry_type)) {
            $expiry_type = 'permanent';
        }

        $site_data = array(
            'site_name'   => $site_name,
            'user_name'   => $username,
            'email'       => $billing_email,
            'order_id'    => $order_id,
            'product_id'  => $product_id,
            'customer_id' => $order->get_customer_id(),
        );

        // Subscription reference
        if (function_exists('wcs_get_subscriptions_for_order')) {
            $subscriptions = wcs_get_subscriptions_for_order($order_id);
            if (!empty($subscriptions)) {
                $subscription = reset($subscriptions);
                $site_data['subscription_id'] = $subscription->get_id();
            }
        }

        // Expiry
        if ($expiry_type === 'temporary' && !empty($expiry_hours)) {
            $site_data['expiry_hours'] = intval($expiry_hours);
            $site_data['is_reserved']  = false;
        } else {
            $site_data['is_reserved'] = true;
        }

        // Create the site via Site Manager
        if (!class_exists('IWP_Site_Manager')) {
            require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-site-manager.php';
        }
        $site_manager = new IWP_Site_Manager();
        $result = $site_manager->create_site_with_tracking(
            $snapshot_slug,
            $site_data,
            $order_id,
            $product_id,
            $plan_id
        );

        if (is_wp_error($result)) {
            IWP_Logger::error('Onboarding site creation failed', 'onboarding', array(
                'order_id' => $order_id,
                'error'    => $result->get_error_message(),
            ));
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Store result in _iwp_sites_created
        $sites_created = get_post_meta($order_id, '_iwp_sites_created', true);
        if (!is_array($sites_created)) {
            $sites_created = array();
        }
        $sites_created[] = array(
            'product_id'   => $product_id,
            'product_name' => $item['product_name'],
            'site_data'    => $result,
            'action'       => 'created',
            'source'       => 'onboarding',
        );
        update_post_meta($order_id, '_iwp_sites_created', $sites_created);

        // Remove this item from deferred list
        unset($deferred_items[$item_index]);
        $deferred_items = array_values($deferred_items); // Re-index
        if (empty($deferred_items)) {
            delete_post_meta($order_id, '_iwp_deferred_items');
        } else {
            update_post_meta($order_id, '_iwp_deferred_items', $deferred_items);
        }

        // Add order note
        $order->add_order_note(
            sprintf(
                __('Site created via post-purchase onboarding for %s.', 'iwp-wp-integration'),
                $item['product_name']
            ),
            1
        );

        IWP_Logger::info('Onboarding site created', 'onboarding', array(
            'order_id'   => $order_id,
            'product_id' => $product_id,
            'site_id'    => $result['site_id'] ?? '',
        ));

        // Return the same structure the shortcode JS expects
        wp_send_json_success(array(
            'site_url'       => $result['site_url'] ?? '',
            'admin_username' => $result['wp_username'] ?? $username,
            'admin_password' => $result['wp_password'] ?? '',
            'admin_url'      => $result['wp_admin_url'] ?? '',
            's_hash'         => $result['s_hash'] ?? '',
            'status'         => $result['status'] ?? 'progress',
            'task_id'        => $result['task_id'] ?? null,
            'site_id'        => $result['site_id'] ?? '',
            'is_pool'        => $result['is_pool'] ?? false,
        ));
    }

    // ------------------------------------------------------------------
    // Thank-you page redirect
    // ------------------------------------------------------------------

    /**
     * Redirect order-received page to onboarding URL when deferred items exist
     */
    public function maybe_redirect_to_onboarding() {
        if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-received')) {
            return;
        }

        global $wp;
        $order_id = isset($wp->query_vars['order-received']) ? absint($wp->query_vars['order-received']) : 0;
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Verify order key
        $order_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        if ($order->get_order_key() !== $order_key) {
            return;
        }

        $deferred_items = get_post_meta($order_id, '_iwp_deferred_items', true);
        if (empty($deferred_items) || !is_array($deferred_items)) {
            return;
        }

        // Find redirect URL from product meta (variation first, then parent)
        $redirect_url = '';
        foreach ($deferred_items as $item) {
            $pid = !empty($item['variation_id']) ? $item['variation_id'] : $item['product_id'];
            $url = get_post_meta($pid, '_iwp_onboarding_redirect_url', true);
            if (empty($url) && !empty($item['product_id'])) {
                $url = get_post_meta($item['product_id'], '_iwp_onboarding_redirect_url', true);
            }
            if (!empty($url)) {
                $redirect_url = $url;
                break;
            }
        }

        /**
         * Filter the onboarding redirect URL.
         *
         * @param string $redirect_url The URL to redirect to (empty = no redirect).
         * @param int    $order_id     The WooCommerce order ID.
         * @param array  $deferred_items Deferred items array.
         */
        $redirect_url = apply_filters('iwp_onboarding_redirect_url', $redirect_url, $order_id, $deferred_items);

        if (empty($redirect_url)) {
            return;
        }

        // Append order params so the onboarding page can detect the order
        $redirect_url = add_query_arg(array(
            'order_id' => $order_id,
            'key'      => $order->get_order_key(),
        ), $redirect_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    // ------------------------------------------------------------------
    // Order detection
    // ------------------------------------------------------------------

    /**
     * Find the relevant order from URL params or current user
     */
    private function get_order_from_context() {
        // 1. URL params (from redirect or email link)
        if (!empty($_GET['order_id']) && !empty($_GET['key'])) {
            $order_id  = absint($_GET['order_id']);
            $order_key = sanitize_text_field($_GET['key']);
            $order     = wc_get_order($order_id);

            if ($order && $order->get_order_key() === $order_key) {
                return $order;
            }
        }

        // 2. Current user's most recent order with deferred items
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $orders = wc_get_orders(array(
                'customer_id' => $user_id,
                'meta_key'    => '_iwp_deferred_items',
                'orderby'     => 'date',
                'order'       => 'DESC',
                'limit'       => 1,
            ));

            if (!empty($orders)) {
                return $orders[0];
            }

            // Also check for recent orders that already had sites created via onboarding
            // (so user can see their completed sites if they revisit)
            $orders = wc_get_orders(array(
                'customer_id' => $user_id,
                'meta_key'    => '_iwp_sites_created',
                'orderby'     => 'date',
                'order'       => 'DESC',
                'limit'       => 1,
                'date_created' => '>' . date('Y-m-d', strtotime('-7 days')),
            ));

            if (!empty($orders)) {
                return $orders[0];
            }
        }

        return null;
    }

    /**
     * Check if the current request can access this order
     */
    private function can_access_order($order, $order_key) {
        // Valid order key grants access (supports guest checkout)
        if (!empty($order_key) && $order->get_order_key() === $order_key) {
            return true;
        }

        // Logged-in user owns the order
        if (is_user_logged_in() && $order->get_customer_id() === get_current_user_id()) {
            return true;
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Scripts
    // ------------------------------------------------------------------

    /**
     * Enqueue onboarding scripts and styles
     */
    public function enqueue_scripts() {
        if (!is_admin()) {
            wp_enqueue_style(
                'iwp-onboarding-css',
                IWP_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                IWP_VERSION
            );

            wp_enqueue_script(
                'iwp-onboarding',
                IWP_PLUGIN_URL . 'assets/js/onboarding.js',
                array('jquery'),
                IWP_VERSION,
                true
            );

            wp_localize_script('iwp-onboarding', 'iwp_onboarding', array(
                'ajax_url'           => admin_url('admin-ajax.php'),
                'app_url'            => IWP_PLUGIN_APP_URL,
                'nonce'              => wp_create_nonce('iwp_onboarding_nonce'),
                'nonce_check_status' => wp_create_nonce('iwp_check_task_status'),
                'i18n' => array(
                    'creating'         => __('Creating your site...', 'iwp-wp-integration'),
                    'checking_status'  => __('Setting up your site...', 'iwp-wp-integration'),
                    'almost_ready'     => __('Almost ready...', 'iwp-wp-integration'),
                    'success'          => __('Your site is ready!', 'iwp-wp-integration'),
                    'error_generic'    => __('Something went wrong. Please try again.', 'iwp-wp-integration'),
                    'copy_success'     => __('Copied!', 'iwp-wp-integration'),
                    'copy_fail'        => __('Failed to copy', 'iwp-wp-integration'),
                    'show'             => __('Show', 'iwp-wp-integration'),
                    'hide'             => __('Hide', 'iwp-wp-integration'),
                    'username_invalid' => __('Username must be 3-20 characters: letters, numbers, underscores.', 'iwp-wp-integration'),
                    'subdomain_invalid' => __('Subdomain must be 3-30 characters: letters, numbers, hyphens (no leading/trailing hyphen).', 'iwp-wp-integration'),
                ),
            ));
        }
    }
}
