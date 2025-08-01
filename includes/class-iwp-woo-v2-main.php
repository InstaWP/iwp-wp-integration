<?php
/**
 * Main plugin class for IWP WooCommerce Integration v2
 *
 * @package IWP_Woo_V2
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_V2_Main class
 */
class IWP_Woo_V2_Main {

    /**
     * Plugin version
     *
     * @var string
     */
    public $version = IWP_WOO_V2_VERSION;

    /**
     * The single instance of the class
     *
     * @var IWP_Woo_V2_Main
     */
    protected static $_instance = null;

    /**
     * Admin instance
     *
     * @var IWP_Woo_V2_Admin
     */
    public $admin = null;

    /**
     * Frontend instance
     *
     * @var IWP_Woo_V2_Frontend
     */
    public $frontend = null;

    /**
     * API instance
     *
     * @var IWP_Woo_V2_API
     */
    public $api = null;

    /**
     * HPOS instance
     *
     * @var IWP_Woo_V2_HPOS
     */
    public $hpos = null;

    /**
     * Product Integration instance
     *
     * @var IWP_Woo_V2_Product_Integration
     */
    public $product_integration = null;

    /**
     * Order Processor instance
     *
     * @var IWP_Woo_V2_Order_Processor
     */
    public $order_processor = null;

    /**
     * Shortcode instance
     *
     * @var IWP_Woo_V2_Shortcode
     */
    public $shortcode = null;

    /**
     * Main instance
     *
     * @static
     * @return IWP_Woo_V2_Main
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }

    /**
     * Define constants
     */
    private function define_constants() {
        $this->define('IWP_WOO_V2_ABSPATH', dirname(IWP_WOO_V2_PLUGIN_FILE) . '/');
    }

    /**
     * Define constant if not already set
     *
     * @param string $name  Constant name
     * @param string $value Constant value
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Hook into actions and filters
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'), 0);
        add_action('init', array($this, 'load_textdomain'));
        
        
        // Add custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize plugin components (works with or without WooCommerce)
        $this->init_includes();
        $this->init_classes();

        // Hook into WooCommerce if available
        if ($this->is_woocommerce_active()) {
            add_action('woocommerce_init', array($this, 'woocommerce_init'));
        }

        // Trigger action
        do_action('iwp_woo_v2_init');
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Include required files
     */
    private function init_includes() {
        // Core includes
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-installer.php';
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-security.php';
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-database.php';
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-logger.php';
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-form-helper.php';
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-utilities.php';
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-hpos.php';
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-api-client.php';
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-service.php';
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-product-integration.php';
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-order-processor.php';
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-site-manager.php';
        include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/class-iwp-woo-v2-shortcode.php';

        // Admin includes
        if ($this->is_request('admin')) {
            include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/admin/class-iwp-woo-v2-admin.php';
        }

        // Frontend includes
        if ($this->is_request('frontend')) {
            include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/frontend/class-iwp-woo-v2-frontend.php';
        }

        // API includes
        if ($this->is_request('api')) {
            include_once IWP_WOO_V2_PLUGIN_PATH . 'includes/api/class-iwp-woo-v2-api.php';
        }
    }

    /**
     * Initialize classes
     */
    private function init_classes() {
        // Initialize security
        new IWP_Woo_V2_Security();

        // Initialize HPOS compatibility (only if WooCommerce is active)
        if ($this->is_woocommerce_active()) {
            $this->hpos = new IWP_Woo_V2_HPOS();
        }

        // Initialize admin
        if ($this->is_request('admin')) {
            $this->admin = new IWP_Woo_V2_Admin();
            // Only initialize product integration if WooCommerce is active
            if ($this->is_woocommerce_active()) {
                $this->product_integration = new IWP_Woo_V2_Product_Integration();
            }
        }

        // Initialize order processor (only if WooCommerce is active)
        if ($this->is_woocommerce_active()) {
            $this->order_processor = new IWP_Woo_V2_Order_Processor();
            if ($this->product_integration) {
                $this->order_processor->set_product_integration($this->product_integration);
            }
        }

        // Initialize site manager (always needed for site tracking)
        $this->site_manager = new IWP_Woo_V2_Site_Manager();

        // Initialize shortcode (always needed for shortcode functionality)
        $this->shortcode = new IWP_Woo_V2_Shortcode();

        // Initialize frontend
        if ($this->is_request('frontend')) {
            $this->frontend = new IWP_Woo_V2_Frontend();
        }

        // Initialize API
        if ($this->is_request('api')) {
            $this->api = new IWP_Woo_V2_API();
        }
    }

    /**
     * What type of request is this?
     *
     * @param string $type admin, ajax, cron, frontend, api
     * @return bool
     */
    private function is_request($type) {
        switch ($type) {
            case 'admin':
                return is_admin();
            case 'ajax':
                return defined('DOING_AJAX');
            case 'cron':
                return defined('DOING_CRON');
            case 'frontend':
                return (!is_admin() || defined('DOING_AJAX')) && !defined('DOING_CRON') && !defined('REST_REQUEST');
            case 'api':
                return defined('REST_REQUEST');
        }
        return false;
    }

    /**
     * WooCommerce initialization
     */
    public function woocommerce_init() {
        // Add WooCommerce-specific functionality here
        do_action('iwp_woo_v2_woocommerce_init');
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        $locale = apply_filters('plugin_locale', get_locale(), 'instawp-integration');
        
        load_textdomain('instawp-integration', WP_LANG_DIR . '/instawp-integration/instawp-integration-' . $locale . '.mo');
        load_plugin_textdomain('instawp-integration', false, plugin_basename(dirname(IWP_WOO_V2_PLUGIN_FILE)) . '/languages');
    }


    /**
     * Get the plugin URL
     *
     * @return string
     */
    public function plugin_url() {
        return untrailingslashit(plugins_url('/', IWP_WOO_V2_PLUGIN_FILE));
    }

    /**
     * Get the plugin path
     *
     * @return string
     */
    public function plugin_path() {
        return untrailingslashit(plugin_dir_path(IWP_WOO_V2_PLUGIN_FILE));
    }

    /**
     * Get the template path
     *
     * @return string
     */
    public function template_path() {
        return apply_filters('iwp_woo_v2_template_path', 'iwp-woo-v2/');
    }

    /**
     * Get Ajax URL
     *
     * @return string
     */
    public function ajax_url() {
        return admin_url('admin-ajax.php', 'relative');
    }

    /**
     * Add custom cron schedules
     *
     * @param array $schedules
     * @return array
     */
    public function add_cron_schedules($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display' => __('Every Minute', 'instawp-integration')
        );
        return $schedules;
    }
}