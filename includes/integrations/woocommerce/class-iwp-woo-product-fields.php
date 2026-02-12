<?php
/**
 * Product Fields for IWP WooCommerce Integration
 *
 * Adds custom username and subdomain fields to the product page,
 * carries them through cart/checkout, and saves as order item meta.
 *
 * @package IWP
 * @since 0.0.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_Product_Fields class
 */
class IWP_Woo_Product_Fields {

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
        // Render fields on product page
        add_action('woocommerce_before_add_to_cart_button', array($this, 'render_product_fields'));

        // Validate fields before adding to cart
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_fields'), 10, 3);

        // Store field values in cart item data
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);

        // Display values in cart and checkout order review
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);

        // Save as order item meta during checkout
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_meta'), 10, 4);

        // Enqueue scripts on product pages
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Check if a product has InstaWP snapshot configured
     *
     * @param int $product_id
     * @return bool
     */
    private function product_has_snapshot($product_id) {
        $snapshot_slug = get_post_meta($product_id, '_iwp_selected_snapshot', true);
        return !empty($snapshot_slug);
    }

    /**
     * Render username and subdomain fields on the product page
     */
    public function render_product_fields() {
        global $product;

        if (!$product || !$this->product_has_snapshot($product->get_id())) {
            return;
        }

        ?>
        <div class="iwp-product-custom-fields">
            <div class="iwp-field-group">
                <label for="iwp_admin_username"><?php esc_html_e('Choose a username', 'iwp-wp-integration'); ?></label>
                <input type="text" id="iwp_admin_username" name="iwp_admin_username" value="" placeholder="<?php esc_attr_e('Leave blank for auto-generated', 'iwp-wp-integration'); ?>" maxlength="20" autocomplete="off">
                <span class="iwp-field-hint"><?php esc_html_e('3-20 characters. Letters, numbers, and underscores only.', 'iwp-wp-integration'); ?></span>
                <span class="iwp-field-error" id="iwp_admin_username_error"></span>
            </div>

            <div class="iwp-field-group">
                <label for="iwp_subdomain"><?php esc_html_e('Choose a subdomain', 'iwp-wp-integration'); ?></label>
                <input type="text" id="iwp_subdomain" name="iwp_subdomain" value="" placeholder="<?php esc_attr_e('Leave blank for auto-generated', 'iwp-wp-integration'); ?>" maxlength="30" autocomplete="off">
                <span class="iwp-field-hint"><?php esc_html_e('3-30 characters. Letters, numbers, and hyphens only.', 'iwp-wp-integration'); ?></span>
                <span class="iwp-field-error" id="iwp_subdomain_error"></span>
            </div>
        </div>
        <?php
    }

    /**
     * Validate fields before adding to cart
     *
     * @param bool $passed
     * @param int $product_id
     * @param int $quantity
     * @return bool
     */
    public function validate_fields($passed, $product_id, $quantity) {
        if (!$this->product_has_snapshot($product_id)) {
            return $passed;
        }

        if (!empty($_POST['iwp_admin_username'])) {
            $username = sanitize_text_field($_POST['iwp_admin_username']);
            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                wc_add_notice(__('WP Admin Username must be 3-20 characters using only letters, numbers, and underscores.', 'iwp-wp-integration'), 'error');
                return false;
            }
        }

        if (!empty($_POST['iwp_subdomain'])) {
            $subdomain = sanitize_text_field($_POST['iwp_subdomain']);
            if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{1,28})[a-zA-Z0-9]$/', $subdomain)) {
                wc_add_notice(__('Subdomain must be 3-30 characters using only letters, numbers, and hyphens (no leading/trailing hyphen).', 'iwp-wp-integration'), 'error');
                return false;
            }
        }

        return $passed;
    }

    /**
     * Store validated values in cart item data
     *
     * @param array $cart_item_data
     * @param int $product_id
     * @param int $variation_id
     * @return array
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        if (!$this->product_has_snapshot($product_id)) {
            return $cart_item_data;
        }

        if (!empty($_POST['iwp_admin_username'])) {
            $username = sanitize_text_field($_POST['iwp_admin_username']);
            if (preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                $cart_item_data['iwp_admin_username'] = $username;
            }
        }

        if (!empty($_POST['iwp_subdomain'])) {
            $subdomain = sanitize_text_field($_POST['iwp_subdomain']);
            if (preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{1,28})[a-zA-Z0-9]$/', $subdomain)) {
                $cart_item_data['iwp_subdomain'] = strtolower($subdomain);
            }
        }

        return $cart_item_data;
    }

    /**
     * Display values in cart and checkout order review
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function display_cart_item_data($item_data, $cart_item) {
        if (!empty($cart_item['iwp_admin_username'])) {
            $item_data[] = array(
                'key'   => __('Username', 'iwp-wp-integration'),
                'value' => esc_html($cart_item['iwp_admin_username']),
            );
        }

        if (!empty($cart_item['iwp_subdomain'])) {
            $item_data[] = array(
                'key'   => __('Subdomain', 'iwp-wp-integration'),
                'value' => esc_html($cart_item['iwp_subdomain']),
            );
        }

        return $item_data;
    }

    /**
     * Save as order item meta during checkout
     *
     * @param WC_Order_Item_Product $item
     * @param string $cart_item_key
     * @param array $values
     * @param WC_Order $order
     */
    public function save_order_item_meta($item, $cart_item_key, $values, $order) {
        if (!empty($values['iwp_admin_username'])) {
            $item->add_meta_data('_iwp_admin_username', sanitize_text_field($values['iwp_admin_username']), true);
        }

        if (!empty($values['iwp_subdomain'])) {
            $item->add_meta_data('_iwp_subdomain', sanitize_text_field($values['iwp_subdomain']), true);
        }
    }

    /**
     * Enqueue product fields scripts
     */
    public function enqueue_scripts() {
        if (!is_product()) {
            return;
        }

        global $product;
        if (!$product || !$this->product_has_snapshot($product->get_id())) {
            return;
        }

        wp_enqueue_script(
            'iwp-product-fields',
            IWP_PLUGIN_URL . 'assets/js/product-fields.js',
            array('jquery'),
            IWP_VERSION,
            true
        );

        wp_localize_script('iwp-product-fields', 'iwp_product_fields', array(
            'i18n' => array(
                'username_invalid' => esc_html__('Must be 3-20 characters: letters, numbers, underscores only.', 'iwp-wp-integration'),
                'subdomain_invalid' => esc_html__('Must be 3-30 characters: letters, numbers, hyphens only. No leading/trailing hyphen.', 'iwp-wp-integration'),
            ),
        ));
    }
}
