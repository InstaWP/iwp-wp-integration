<?php
/**
 * WooCommerce Product Integration for IWP WooCommerce Integration v2
 *
 * @package IWP
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_Product_Integration class
 */
class IWP_Woo_Product_Integration {

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
        // Add custom tab to product data
        add_filter('woocommerce_product_data_tabs', array($this, 'add_product_data_tab'));
        
        // Add custom fields to the tab
        add_action('woocommerce_product_data_panels', array($this, 'add_product_data_fields'));
        
        // Save custom fields
        add_action('woocommerce_process_product_meta', array($this, 'save_product_data_fields'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add InstaWP tab to product data
     *
     * @param array $tabs
     * @return array
     */
    public function add_product_data_tab($tabs) {
        $tabs['iwp_instawp'] = array(
            'label'    => __('InstaWP', 'iwp-wp-integration'),
            'target'   => 'iwp_instawp_product_data',
            'class'    => array('show_if_simple', 'show_if_variable'),
            'priority' => 80,
        );
        
        return $tabs;
    }

    /**
     * Add custom fields to the InstaWP tab
     */
    public function add_product_data_fields() {
        global $post;
        
        IWP_Logger::debug('Rendering product data fields', 'product-integration', array('product_id' => $post->ID));
        
        echo '<div id="iwp_instawp_product_data" class="panel woocommerce_options_panel">';
        
        echo '<div class="options_group">';
        echo '<h4>' . __('InstaWP Snapshot Selection', 'iwp-wp-integration') . '</h4>';
        // Get current values
        $selected_snapshot = get_post_meta($post->ID, '_iwp_selected_snapshot', true);
        
        // Get snapshots from API
        $snapshots = $this->get_snapshot_options();
        
        // Snapshot selection dropdown
        woocommerce_wp_select(array(
            'id'          => '_iwp_selected_snapshot',
            'label'       => __('InstaWP Snapshot', 'iwp-wp-integration'),
            'description' => __('Select a snapshot to use for this product.', 'iwp-wp-integration'),
            'desc_tip'    => true,
            'options'     => $snapshots,
            'value'       => $selected_snapshot,
        ));
        
        
        // Snapshot refresh button
        echo '<p class="form-field">';
        echo '<label>&nbsp;</label>';
        echo '<button type="button" class="button button-secondary" id="iwp-refresh-product-snapshots">';
        echo __('Refresh Snapshots', 'iwp-wp-integration');
        echo '</button>';
        echo '<span class="description">' . __('Click to refresh the snapshot list from InstaWP.', 'iwp-wp-integration') . '</span>';
        echo '</p>';
        
        echo '</div>'; // .options_group
        
        // Plans section
        echo '<div class="options_group">';
        echo '<h4>' . __('InstaWP Plan Selection', 'iwp-wp-integration') . '</h4>';
        
        // Get selected plan
        $selected_plan = get_post_meta($post->ID, '_iwp_selected_plan', true);
        
        // Plan dropdown
        woocommerce_wp_select(array(
            'id'          => '_iwp_selected_plan',
            'label'       => __('Select Plan', 'iwp-wp-integration'),
            'description' => __('Choose an InstaWP plan for site creation.', 'iwp-wp-integration'),
            'desc_tip'    => true,
            'options'     => $this->get_plans_for_dropdown(),
            'value'       => $selected_plan,
        ));
        
        // Plan refresh button
        echo '<p class="form-field">';
        echo '<label>&nbsp;</label>';
        echo '<button type="button" class="button button-secondary" id="iwp-refresh-product-plans">';
        echo __('Refresh Plans', 'iwp-wp-integration');
        echo '</button>';
        echo '<span class="description">' . __('Click to refresh the plan list from InstaWP.', 'iwp-wp-integration') . '</span>';
        echo '</p>';
        
        echo '</div>'; // .options_group
        
        // Site Expiry section
        echo '<div class="options_group">';
        echo '<h4>' . __('Site Expiry Settings', 'iwp-wp-integration') . '</h4>';
        
        // Get current expiry type
        $expiry_type = get_post_meta($post->ID, '_iwp_site_expiry_type', true);
        if (empty($expiry_type)) {
            $expiry_type = 'permanent'; // Default to permanent
        }
        
        // Site expiry type selection
        echo '<p class="form-field _iwp_site_expiry_type_field">';
        echo '<label>' . __('Site Expiry', 'iwp-wp-integration') . '</label>';
        echo '<span class="wrap">';
        echo '<label>';
        echo '<input type="radio" name="_iwp_site_expiry_type" value="permanent" ' . checked($expiry_type, 'permanent', false) . ' /> ';
        echo __('Permanent', 'iwp-wp-integration');
        echo '</label>';
        echo '<label>';
        echo '<input type="radio" name="_iwp_site_expiry_type" value="temporary" ' . checked($expiry_type, 'temporary', false) . ' /> ';
        echo __('Temporary', 'iwp-wp-integration');
        echo '</label>';
        echo '</span>';
        echo '<span class="description">' . __('Choose whether sites created from this product are permanent or temporary.', 'iwp-wp-integration') . '</span>';
        echo '</p>';
        
        // Expiry hours field (shown only when temporary is selected)
        $expiry_hours = get_post_meta($post->ID, '_iwp_site_expiry_hours', true);
        if (empty($expiry_hours)) {
            $expiry_hours = 24; // Default to 24 hours
        }
        
        echo '<p class="form-field _iwp_site_expiry_hours_field" style="' . ($expiry_type === 'temporary' ? '' : 'display: none;') . '">';
        echo '<label for="_iwp_site_expiry_hours">' . __('Expiry Hours', 'iwp-wp-integration') . '</label>';
        echo '<input type="number" name="_iwp_site_expiry_hours" id="_iwp_site_expiry_hours" value="' . esc_attr($expiry_hours) . '" min="1" max="8760" step="1" />';
        echo '<span class="description">' . __('Number of hours before the site expires (1-8760 hours, default: 24).', 'iwp-wp-integration') . '</span>';
        echo '</p>';
        
        echo '</div>'; // .options_group
        
        // Snapshot preview section
        // if (!empty($selected_snapshot)) {
        //     echo '<div class="options_group">';
        //     echo '<h4>' . __('Selected Snapshot Preview', 'iwp-wp-integration') . '</h4>';
        //     echo '<div id="iwp-snapshot-preview">';
        //     $this->render_snapshot_preview($selected_snapshot);
        //     echo '</div>';
        //     echo '</div>';
        // }
        
        echo '</div>'; // #iwp_instawp_product_data
    }

    /**
     * Save custom fields
     *
     * @param int $post_id
     */
    public function save_product_data_fields($post_id) {
        IWP_Logger::debug('Saving product data fields', 'product-integration', array('product_id' => $post_id));
        
        // Save selected snapshot
        $selected_snapshot = isset($_POST['_iwp_selected_snapshot']) ? sanitize_text_field($_POST['_iwp_selected_snapshot']) : '';
        update_post_meta($post_id, '_iwp_selected_snapshot', $selected_snapshot);
        
        // Save selected plan
        $selected_plan = isset($_POST['_iwp_selected_plan']) ? sanitize_text_field($_POST['_iwp_selected_plan']) : '';
        update_post_meta($post_id, '_iwp_selected_plan', $selected_plan);
        
        // Save site expiry type
        $expiry_type = isset($_POST['_iwp_site_expiry_type']) ? sanitize_text_field($_POST['_iwp_site_expiry_type']) : 'permanent';
        update_post_meta($post_id, '_iwp_site_expiry_type', $expiry_type);
        
        // Save expiry hours (only if temporary)
        if ($expiry_type === 'temporary') {
            $expiry_hours = isset($_POST['_iwp_site_expiry_hours']) ? intval($_POST['_iwp_site_expiry_hours']) : 24;
            // Ensure expiry hours is within valid range
            $expiry_hours = max(1, min(8760, $expiry_hours));
            update_post_meta($post_id, '_iwp_site_expiry_hours', $expiry_hours);
        } else {
            // Clear expiry hours for permanent sites
            delete_post_meta($post_id, '_iwp_site_expiry_hours');
        }
        
        IWP_Logger::info('Saved product configuration', 'product-integration', array(
            'snapshot' => $selected_snapshot, 
            'plan' => $selected_plan,
            'expiry_type' => $expiry_type,
            'expiry_hours' => ($expiry_type === 'temporary' ? ($expiry_hours ?? 24) : null)
        ));
    }

    /**
     * Get site expiry type for a product
     *
     * @param int $product_id
     * @return string 'permanent' or 'temporary'
     */
    public function get_product_expiry_type($product_id) {
        $expiry_type = get_post_meta($product_id, '_iwp_site_expiry_type', true);
        return !empty($expiry_type) ? $expiry_type : 'permanent';
    }

    /**
     * Get site expiry hours for a product
     *
     * @param int $product_id
     * @return int|null Returns hours if temporary, null if permanent
     */
    public function get_product_expiry_hours($product_id) {
        $expiry_type = $this->get_product_expiry_type($product_id);
        if ($expiry_type === 'temporary') {
            $expiry_hours = get_post_meta($product_id, '_iwp_site_expiry_hours', true);
            return !empty($expiry_hours) ? intval($expiry_hours) : 24;
        }
        return null;
    }

    /**
     * Check if a product creates permanent sites
     *
     * @param int $product_id
     * @return bool
     */
    public function is_product_permanent($product_id) {
        return $this->get_product_expiry_type($product_id) === 'permanent';
    }

    /**
     * Get snapshots formatted for dropdown
     *
     * @return array
     */
    private function get_snapshot_options() {
        IWP_Logger::debug('Getting snapshots for product dropdown via centralized service', 'product-integration');
        return IWP_Service::get_snapshots_for_dropdown();
    }

    /**
     * Get plans formatted for dropdown
     *
     * @return array
     */
    private function get_plans_for_dropdown() {
        IWP_Logger::debug('Getting plans for product dropdown via centralized service', 'product-integration');
        return IWP_Service::get_plans_for_dropdown();
    }

    /**
     * Render snapshot preview
     *
     * @param string $snapshot_slug
     */
    private function render_snapshot_preview($snapshot_slug) {
        if (empty($snapshot_slug)) {
            return;
        }
        
        // Get API key from settings
        $plugin_options = get_option('iwp_options', array());
        $api_key = isset($plugin_options['api_key']) ? $plugin_options['api_key'] : '';
        
        if (empty($api_key)) {
            echo '<p>' . __('API key not configured.', 'iwp-wp-integration') . '</p>';
            return;
        }
        
        $this->api_client->set_api_key($api_key);
        $snapshot = $this->api_client->get_snapshot($snapshot_slug);
        
        if (is_wp_error($snapshot)) {
            echo '<p>' . __('Error loading snapshot details: ', 'iwp-wp-integration') . esc_html($snapshot->get_error_message()) . '</p>';
            return;
        }
        
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
        }
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on product edit pages
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        
        global $post;
        if (!$post || 'product' !== $post->post_type) {
            return;
        }
        
        wp_enqueue_script(
            'iwp-wp-integration-product-admin',
            IWP_PLUGIN_URL . 'assets/js/integrations/woocommerce/woo-product.js',
            array('jquery'),
            IWP_VERSION,
            true
        );
        
        wp_enqueue_style(
            'iwp-wp-integration-product-admin',
            IWP_PLUGIN_URL . 'assets/css/integrations/woocommerce/woo-product.css',
            array(),
            IWP_VERSION
        );
        
        wp_localize_script('iwp-wp-integration-product-admin', 'iwp_product_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('iwp_product_admin_nonce'),
            'strings'  => array(
                'refreshing' => __('Refreshing snapshots...', 'iwp-wp-integration'),
                'error'      => __('Error refreshing snapshots', 'iwp-wp-integration'),
                'success'    => __('Snapshots refreshed successfully', 'iwp-wp-integration'),
                'refreshing_plans' => __('Refreshing plans...', 'iwp-wp-integration'),
                'plans_error'      => __('Error refreshing plans', 'iwp-wp-integration'),
                'plans_success'    => __('Plans refreshed successfully', 'iwp-wp-integration'),
            ),
        ));
    }

    /**
     * Get product's selected snapshot
     *
     * @param int $product_id
     * @return string|false
     */
    public function get_product_snapshot($product_id) {
        return get_post_meta($product_id, '_iwp_selected_snapshot', true);
    }

    /**
     * Get product's selected plan
     *
     * @param int $product_id
     * @return string|false
     */
    public function get_product_plan($product_id) {
        return get_post_meta($product_id, '_iwp_selected_plan', true);
    }

    /**
     * Backward compatibility: Get product's selected template
     *
     * @param int $product_id
     * @return string|false
     * @deprecated Use get_product_snapshot() instead
     */
    public function get_product_template($product_id) {
        return $this->get_product_snapshot($product_id);
    }

}
