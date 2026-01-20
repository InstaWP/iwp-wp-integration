<?php
/**
 * Sites List Table for InstaWP Integration
 *
 * @package IWP
 * @since 0.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load WP_List_Table if not already loaded
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * IWP_Sites_List_Table class
 */
class IWP_Sites_List_Table extends WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'iwp-site',
            'plural'   => 'iwp-sites',
            'ajax'     => false
        ));
    }

    /**
     * Get list table columns
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'site_url'    => __('Site URL', 'iwp-wp-integration'),
            'username'    => __('Username', 'iwp-wp-integration'),
            'password'    => __('Password', 'iwp-wp-integration'),
            'plan_id'     => __('Plan', 'iwp-wp-integration'),
            'user'        => __('User', 'iwp-wp-integration'),
            'source'      => __('Source', 'iwp-wp-integration'),
            'status'      => __('Status', 'iwp-wp-integration'),
            'created'     => __('Created', 'iwp-wp-integration')
            // Removed 'actions' column - action buttons now appear below site URL
        );
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'site_url' => array('site_url', false),
            'plan_id'  => array('plan_id', false),
            'user'     => array('user', false),
            'source'   => array('source', false),
            'status'   => array('status', false),
            'created'  => array('created', true)
        );
    }

    /**
     * Get bulk actions
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array(); // No bulk actions
    }

    /**
     * Prepare table items
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        // Get all sites data
        $sites = $this->get_all_sites();

        // Apply search filter
        $sites = $this->apply_search_filter($sites);

        // Apply status filter
        $sites = $this->apply_status_filter($sites);

        // Apply source filter
        $sites = $this->apply_source_filter($sites);

        // Handle sorting
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'created';
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'desc';
        $sites = $this->sort_sites($sites, $orderby, $order);

        // Handle pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count($sites);

        $sites = array_slice($sites, (($current_page - 1) * $per_page), $per_page);

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));

        $this->items = $sites;
    }

    /**
     * Get view links (All, Active, Creating, etc.)
     *
     * @return array
     */
    protected function get_views() {
        $all_sites = $this->get_all_sites();
        $total = count($all_sites);

        // Count by status
        $status_counts = array(
            'all' => $total,
            'completed' => 0,
            'progress' => 0,
            'failed' => 0,
            'expired' => 0,
        );

        foreach ($all_sites as $site) {
            // Count expired sites separately
            if (!empty($site['is_expired'])) {
                $status_counts['expired']++;
            } elseif (isset($site['status']) && isset($status_counts[$site['status']])) {
                $status_counts[$site['status']]++;
            }
        }

        $current_status = isset($_GET['status']) ? $_GET['status'] : 'all';
        $base_url = admin_url('admin.php?page=instawp-sites');

        $views = array();

        // All
        $class = ($current_status === 'all') ? 'current' : '';
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url($base_url),
            $class,
            __('All', 'iwp-wp-integration'),
            $status_counts['all']
        );

        // Active
        if ($status_counts['completed'] > 0) {
            $class = ($current_status === 'completed') ? 'current' : '';
            $views['completed'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', 'completed', $base_url)),
                $class,
                __('Active', 'iwp-wp-integration'),
                $status_counts['completed']
            );
        }

        // Creating
        if ($status_counts['progress'] > 0) {
            $class = ($current_status === 'progress') ? 'current' : '';
            $views['progress'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', 'progress', $base_url)),
                $class,
                __('Creating', 'iwp-wp-integration'),
                $status_counts['progress']
            );
        }

        // Failed
        if ($status_counts['failed'] > 0) {
            $class = ($current_status === 'failed') ? 'current' : '';
            $views['failed'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', 'failed', $base_url)),
                $class,
                __('Failed', 'iwp-wp-integration'),
                $status_counts['failed']
            );
        }

        // Expired
        if ($status_counts['expired'] > 0) {
            $class = ($current_status === 'expired') ? 'current' : '';
            $views['expired'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', 'expired', $base_url)),
                $class,
                __('Expired', 'iwp-wp-integration'),
                $status_counts['expired']
            );
        }

        return $views;
    }

    /**
     * Display extra tablenav (filters and search)
     *
     * @param string $which Top or bottom
     */
    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        ?>
        <div class="alignleft actions">
            <?php $this->source_filter_dropdown(); ?>
            <?php submit_button(__('Filter', 'iwp-wp-integration'), '', 'filter_action', false); ?>
        </div>
        <?php
    }

    /**
     * Display source filter dropdown
     */
    protected function source_filter_dropdown() {
        $all_sites = $this->get_all_sites();
        $sources = array();

        // Collect unique sources
        foreach ($all_sites as $site) {
            if (!empty($site['source'])) {
                $source_key = strtolower(str_replace(' ', '_', $site['source']));
                if (!isset($sources[$source_key])) {
                    $sources[$source_key] = $site['source'];
                }
            }
        }

        $current_source = isset($_GET['source_filter']) ? $_GET['source_filter'] : '';

        ?>
        <select name="source_filter" id="source-filter">
            <option value=""><?php _e('All Sources', 'iwp-wp-integration'); ?></option>
            <?php foreach ($sources as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($current_source, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Apply search filter to sites
     *
     * @param array $sites Sites array
     * @return array Filtered sites
     */
    private function apply_search_filter($sites) {
        if (empty($_GET['s'])) {
            return $sites;
        }

        $search = strtolower(trim($_GET['s']));

        return array_filter($sites, function($site) use ($search) {
            // Search in site URL
            if (stripos($site['site_url'], $search) !== false) {
                return true;
            }

            // Search in username
            if (stripos($site['username'], $search) !== false) {
                return true;
            }

            // Search in user
            if (stripos($site['user'], $search) !== false) {
                return true;
            }

            // Search in site ID
            if (stripos($site['site_id'], $search) !== false) {
                return true;
            }

            // Search in order ID
            if (!empty($site['order_id']) && stripos((string)$site['order_id'], $search) !== false) {
                return true;
            }

            return false;
        });
    }

    /**
     * Apply status filter to sites
     *
     * @param array $sites Sites array
     * @return array Filtered sites
     */
    private function apply_status_filter($sites) {
        if (empty($_GET['status']) || $_GET['status'] === 'all') {
            return $sites;
        }

        $status = $_GET['status'];

        return array_filter($sites, function($site) use ($status) {
            // Handle expired status (calculated, not a database status)
            if ($status === 'expired') {
                return !empty($site['is_expired']);
            }

            // Handle regular database statuses
            return isset($site['status']) && $site['status'] === $status;
        });
    }

    /**
     * Apply source filter to sites
     *
     * @param array $sites Sites array
     * @return array Filtered sites
     */
    private function apply_source_filter($sites) {
        if (empty($_GET['source_filter'])) {
            return $sites;
        }

        $source_filter = strtolower($_GET['source_filter']);

        return array_filter($sites, function($site) use ($source_filter) {
            $site_source = strtolower(str_replace(' ', '_', $site['source']));
            return $site_source === $source_filter;
        });
    }

    /**
     * Get all sites from various sources
     *
     * @return array
     */
    private function get_all_sites() {
        $all_sites = array();

        // Get sites from database table (real-time tracking)
        $db_sites = $this->get_sites_from_database();
        $all_sites = array_merge($all_sites, $db_sites);

        // Get sites from WooCommerce orders (legacy/backup)
        $order_sites = $this->get_sites_from_orders();
        $all_sites = array_merge($all_sites, $order_sites);

        // Get sites from shortcode usage (stored in options)
        $shortcode_sites = $this->get_sites_from_shortcode();
        $all_sites = array_merge($all_sites, $shortcode_sites);

        // Remove duplicates based on site_id or URL
        $unique_sites = array();
        $seen_sites = array();

        foreach ($all_sites as $site) {
            // Create unique key based on site_id if available, otherwise site_url
            $unique_key = '';
            if (!empty($site['site_id']) && strpos($site['site_id'], 'pending-') !== 0) {
                $unique_key = 'site_' . $site['site_id'];
            } elseif (!empty($site['site_url'])) {
                $unique_key = 'url_' . md5($site['site_url']);
            } else {
                $unique_key = 'index_' . count($unique_sites);
            }

            if (!isset($seen_sites[$unique_key])) {
                $unique_sites[] = $site;
                $seen_sites[$unique_key] = true;
            }
        }

        return $unique_sites;
    }

    /**
     * Get sites from database table
     *
     * @return array
     */
    private function get_sites_from_database() {
        $sites = array();

        // Get sites from database table
        $db_sites = IWP_Sites_Model::get_all(array('limit' => 100));

        foreach ($db_sites as $db_site) {
            $order = null;
            $order_link = '';

            if (!empty($db_site->order_id)) {
                $order = wc_get_order($db_site->order_id);
                if ($order) {
                    $order_link = admin_url('post.php?post=' . $db_site->order_id . '&action=edit');
                }
            }

            // Check if this site has upgrade history
            $upgrade_info = '';
            $is_upgraded = false;
            if (!empty($db_site->api_response)) {
                $api_data = json_decode($db_site->api_response, true);
                if (is_array($api_data)) {
                    if (isset($api_data['created_from_upgrade']) && $api_data['created_from_upgrade']) {
                        $is_upgraded = true;
                        $upgrade_info = ' (Upgraded)';
                    } elseif (isset($api_data['upgrade_history']) && is_array($api_data['upgrade_history']) && count($api_data['upgrade_history']) > 0) {
                        $is_upgraded = true;
                        $upgrade_count = count($api_data['upgrade_history']);
                        $upgrade_info = sprintf(' (%d upgrade%s)', $upgrade_count, $upgrade_count > 1 ? 's' : '');
                    }
                }
            }

            // Determine source type and badges
            $source_type = $this->determine_source_type($db_site->source, $order);

            // Calculate if site is expired (for temporary sites with expiry_hours)
            $is_expired = false;
            $hours_remaining = null;
            if (!empty($db_site->expiry_hours) && !empty($db_site->created_at)) {
                $created_time = strtotime($db_site->created_at);
                $current_time = current_time('timestamp');
                $hours_elapsed = ($current_time - $created_time) / 3600;

                if ($hours_elapsed >= $db_site->expiry_hours) {
                    $is_expired = true;
                } else {
                    $hours_remaining = $db_site->expiry_hours - $hours_elapsed;
                }
            }

            $site = array(
                'site_url'     => $db_site->site_url ?: '',
                'username'     => $db_site->wp_username ?: '',
                'password'     => $db_site->wp_password ?: '',
                'user'         => $this->get_user_display_name($order, $db_site->user_id),
                'source'       => $source_type['text'],
                'source_link'  => $order_link,
                'source_id'    => $db_site->order_id ?: '',
                'source_badge' => $source_type['badge'],
                'status'       => $db_site->status,
                'created'      => $db_site->created_at,
                'site_id'      => $db_site->site_id,
                'order_id'     => $db_site->order_id,
                's_hash'       => $db_site->s_hash ?: '',
                'plan_id'      => $db_site->plan_id ?: '',
                'is_upgraded'  => $is_upgraded,
                'is_expired'   => $is_expired,
                'hours_remaining' => $hours_remaining,
                'expiry_hours' => $db_site->expiry_hours
            );

            $sites[] = $site;
        }

        return $sites;
    }

    /**
     * Get sites from WooCommerce orders
     *
     * @return array
     */
    private function get_sites_from_orders() {
        global $wpdb;
        
        $sites = array();

        // Query all orders with InstaWP sites
        $results = $wpdb->get_results("
            SELECT p.ID as order_id, pm.meta_value as sites_data
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND pm.meta_key IN ('_iwp_sites_created', '_iwp_created_sites')
            AND pm.meta_value IS NOT NULL
            AND pm.meta_value != ''
        ");

        foreach ($results as $result) {
            $order_id = $result->order_id;
            $sites_data = maybe_unserialize($result->sites_data);

            if (!is_array($sites_data)) {
                continue;
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            foreach ($sites_data as $site_data) {
                $site = $this->format_order_site($site_data, $order);
                if ($site) {
                    $sites[] = $site;
                }
            }
        }

        return $sites;
    }

    /**
     * Get sites from shortcode usage
     *
     * @return array
     */
    private function get_sites_from_shortcode() {
        // For now, return empty array
        // In the future, we could store shortcode-created sites in a custom table or option
        return array();
    }

    /**
     * Format order site data for display
     *
     * @param array $site_data Raw site data
     * @param WC_Order $order Order object
     * @return array|null Formatted site data
     */
    private function format_order_site($site_data, $order) {
        // Handle both new and legacy data formats
        $wp_url = '';
        $wp_username = '';
        $wp_password = '';
        $status = 'unknown';
        $created = '';
        $site_id = '';
        $s_hash = '';
        $plan_id = '';
        $action = 'created';

        if (isset($site_data['site_data'])) {
            // New format from order processor
            $inner_data = $site_data['site_data'];
            $wp_url = $inner_data['wp_url'] ?? '';
            $wp_username = $inner_data['wp_username'] ?? '';
            $wp_password = $inner_data['wp_password'] ?? '';
            $status = $site_data['site_data']['status'] ?? $site_data['status'] ?? 'unknown';
            $created = $inner_data['created_at'] ?? '';
            $site_id = $inner_data['site_id'] ?? $inner_data['id'] ?? '';
            $s_hash = $inner_data['s_hash'] ?? '';
            $plan_id = $inner_data['plan_id'] ?? $site_data['plan_id'] ?? '';
            $action = $site_data['action'] ?? 'created';
        } else {
            // Legacy format
            $wp_url = $site_data['wp_url'] ?? '';
            $wp_username = $site_data['wp_username'] ?? '';
            $wp_password = $site_data['wp_password'] ?? '';
            $status = $site_data['status'] ?? 'unknown';
            $created = $site_data['created_at'] ?? '';
            $site_id = $site_data['site_id'] ?? $site_data['id'] ?? '';
            $s_hash = $site_data['s_hash'] ?? '';
            $plan_id = $site_data['plan_id'] ?? '';
            $action = $site_data['action'] ?? 'created';
        }

        if (empty($wp_url)) {
            return null; // Skip sites without URL
        }

        // Determine badge based on action
        $badge = null;
        if ($action === 'reconciled') {
            $badge = 'converted_demo';
        } elseif ($action === 'upgraded') {
            $badge = 'upgraded';
        }

        return array(
            'site_url'     => $wp_url,
            'username'     => $wp_username,
            'password'     => $wp_password,
            'plan_id'      => $plan_id,
            'user'         => $this->get_user_display_name($order, $order->get_user_id()),
            'source'       => 'WooCommerce',
            'source_link'  => admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
            'source_id'    => $order->get_id(),
            'source_badge' => $badge,
            'status'       => $status,
            'created'      => $created ?: $order->get_date_created()->format('Y-m-d H:i:s'),
            'site_id'      => $site_id,
            'order_id'     => $order->get_id(),
            's_hash'       => $s_hash,
            'is_upgraded'  => ($action === 'upgraded')
        );
    }

    /**
     * Determine source type and badge for a site
     *
     * @param string $source Source from database
     * @param WC_Order|null $order Order object if available
     * @return array Array with 'text' and 'badge' keys
     */
    private function determine_source_type($source, $order = null) {
        $result = array(
            'text' => '',
            'badge' => null
        );

        // WooCommerce sources (with or without order)
        if ($source === 'demo_to_paid' || $source === 'woocommerce') {
            $result['text'] = 'WooCommerce';

            // Set badge for converted demos
            if ($source === 'demo_to_paid') {
                $result['badge'] = 'converted_demo';
            }
        }
        // Shortcode and other sources
        else {
            $result['text'] = $this->format_source_display($source, 'paid');
        }

        return $result;
    }

    /**
     * Get badge HTML for source column
     *
     * @param string $badge_type Type of badge: 'converted_demo', 'upgraded', etc.
     * @return string Badge HTML
     */
    private function get_source_badge_html($badge_type) {
        switch ($badge_type) {
            case 'converted_demo':
                return '<span class="iwp-badge iwp-badge-converted" title="Converted from demo site">Converted Demo</span>';

            case 'upgraded':
                return '<span class="iwp-badge iwp-badge-upgraded" title="Site plan was upgraded">Upgraded</span>';

            default:
                return '';
        }
    }

    /**
     * Format source display text based on source type
     *
     * @param string $source Source from database
     * @param string $site_type Site type (demo, paid, trial)
     * @return string Formatted source display text
     */
    private function format_source_display($source, $site_type = 'paid') {
        // Handle different source types
        switch ($source) {
            case 'demo_to_paid':
                return 'Demo → Paid';

            case 'shortcode':
                if ($site_type === 'demo') {
                    return 'Shortcode (Demo)';
                }
                return 'Shortcode';

            case 'woocommerce':
                return 'WooCommerce';

            default:
                // Fallback: capitalize and replace underscores
                return ucwords(str_replace('_', ' ', $source));
        }
    }

    /**
     * Get user display name from order or user_id
     *
     * @param WC_Order|null $order Order object
     * @param int|null $user_id WordPress user ID from database
     * @return string User display name
     */
    private function get_user_display_name($order = null, $user_id = null) {
        // Try to use user_id from database first (more reliable for demo conversions)
        if ($user_id && $user_id > 0) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                return sprintf('%s (%s)', $user->display_name, $user->user_login);
            }
        }

        // Fallback to order if available
        if ($order) {
            $order_user_id = $order->get_user_id();

            if ($order_user_id) {
                // Order has a WordPress user
                $user = get_user_by('id', $order_user_id);
                if ($user) {
                    return sprintf('%s (%s)', $user->display_name, $user->user_login);
                }
            }

            // Guest order - use billing information
            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            $email = $order->get_billing_email();

            if ($first_name || $last_name) {
                $name = trim($first_name . ' ' . $last_name);
                return sprintf('%s (guest) <%s>', $name, $email);
            }

            return sprintf('Guest <%s>', $email);
        }

        // No order and user_id is 0 (guest demo site not yet converted)
        if ($user_id === 0 || $user_id === '0') {
            return 'Guest (Demo)';
        }

        return 'Unknown';
    }

    /**
     * Sort sites array
     *
     * @param array $sites Sites data
     * @param string $orderby Column to sort by
     * @param string $order Sort direction
     * @return array Sorted sites
     */
    private function sort_sites($sites, $orderby, $order) {
        usort($sites, function($a, $b) use ($orderby, $order) {
            $val_a = $a[$orderby] ?? '';
            $val_b = $b[$orderby] ?? '';

            if ($orderby === 'created') {
                $val_a = strtotime($val_a);
                $val_b = strtotime($val_b);
            }

            $result = 0;
            if ($val_a < $val_b) {
                $result = -1;
            } elseif ($val_a > $val_b) {
                $result = 1;
            }

            return ($order === 'desc') ? -$result : $result;
        });

        return $sites;
    }

    /**
     * Display checkbox column - Removed as bulk actions are disabled
     *
     * @param array $item Site data
     * @return string
     */
    /*
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="site[]" value="%s" />',
            $item['site_id'] ?: md5($item['site_url'])
        );
    }
    */

    /**
     * Display site URL column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_site_url($item) {
        $url = esc_url($item['site_url']);
        $site_link = sprintf('<a href="%s" target="_blank"><strong>%s</strong></a>', $url, esc_html($url));
        
        // Build row actions - consolidating all actions from the removed Actions column
        $actions = array();
        
        // Add "Open in InstaWP" action first (moved from Actions column)
        if (!empty($item['site_id'])) {
            $actions['open_instawp'] = sprintf('<a href="%s" target="_blank">%s</a>', 
                esc_url('https://app.instawp.io/sites/' . $item['site_id'] . '/dashboard'), 
                __('Open in InstaWP', 'iwp-wp-integration')
            );
        }
        
        // Always show "Visit Site" action
        $actions['visit'] = sprintf('<a href="%s" target="_blank">%s</a>', $url, __('Visit Site', 'iwp-wp-integration'));
        
        // Add Magic Login if s_hash is available
        if (!empty($item['s_hash'])) {
            $magic_login_url = 'https://app.instawp.io/wordpress-auto-login?site=' . urlencode($item['s_hash']);
            $actions['magic_login'] = sprintf('<a href="%s" target="_blank">%s</a>', esc_url($magic_login_url), __('Magic Login', 'iwp-wp-integration'));
        } else {
            // Fallback to regular wp-admin if no s_hash
            $admin_url = trailingslashit($url) . 'wp-admin';
            $actions['admin_login'] = sprintf('<a href="%s" target="_blank">%s</a>', esc_url($admin_url), __('Admin Login', 'iwp-wp-integration'));
        }
        
        // Add delete action if we have a site_id
        if (!empty($item['site_id'])) {
            $delete_url = wp_nonce_url(
                admin_url('admin.php?page=instawp-sites&action=delete&site_id=' . urlencode($item['site_id'])),
                'delete_site_' . $item['site_id']
            );
            $actions['delete'] = sprintf('<a href="%s" onclick="return confirm(\'%s\')">%s</a>', 
                esc_url($delete_url), 
                esc_js(__('Are you sure you want to delete this site?', 'iwp-wp-integration')),
                __('Delete', 'iwp-wp-integration')
            );
        }
        
        return $site_link . $this->row_actions($actions);
    }

    /**
     * Display username column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_username($item) {
        return esc_html($item['username']);
    }

    /**
     * Display password column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_password($item) {
        if (empty($item['password'])) {
            return '—';
        }
        
        return sprintf(
            '<span class="iwp-password-hidden">••••••••</span> <button type="button" class="iwp-show-password button-link" data-password="%s">%s</button>',
            esc_attr($item['password']),
            __('Show', 'iwp-wp-integration')
        );
    }

    /**
     * Display plan column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_plan_id($item) {
        if (empty($item['plan_id'])) {
            return '—';
        }

        // Get plan name from plan ID using the service helper
        require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-service.php';
        $plan_name = IWP_Service::get_plan_name_by_id($item['plan_id']);
        
        // If plan name is different from plan ID, show name with ID as title
        if ($plan_name !== $item['plan_id']) {
            $plan_display = '<span title="Plan ID: ' . esc_attr($item['plan_id']) . '">' . esc_html($plan_name) . '</span>';
        } else {
            // Fallback to showing plan ID if name not found
            $plan_display = esc_html($item['plan_id']);
        }
        
        // Add upgrade indicator if site has been upgraded
        if (!empty($item['is_upgraded'])) {
            $plan_display .= ' <span class="iwp-upgrade-badge" title="This site has been upgraded">⬆</span>';
        }
        
        return $plan_display;
    }

    /**
     * Display user column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_user($item) {
        return esc_html($item['user']);
    }

    /**
     * Display source column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_source($item) {
        $output = '';

        // Build the base source display
        if (!empty($item['source_link'])) {
            // Has order link
            $output = sprintf(
                '<a href="%s">Order #%s</a>',
                esc_url($item['source_link']),
                esc_html($item['source_id'])
            );
        } else {
            // No order link - just show source text
            $output = esc_html($item['source']);
        }

        // Add badge if present (reusing badge logic)
        if (!empty($item['source_badge'])) {
            $output .= ' ' . $this->get_source_badge_html($item['source_badge']);
        }

        return $output;
    }

    /**
     * Display status column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_status($item) {
        // Check if site is expired first
        if (!empty($item['is_expired'])) {
            return sprintf(
                '<span class="iwp-status iwp-status-expired" title="%s">%s</span>',
                esc_attr__('Site has passed its expiry time', 'iwp-wp-integration'),
                __('Expired', 'iwp-wp-integration')
            );
        }

        $status = $item['status'];
        $class = '';
        $text = '';

        switch ($status) {
            case 'completed':
                $class = 'iwp-status-completed';
                $text = __('Active', 'iwp-wp-integration');

                // Show time remaining for temporary sites
                if (!empty($item['hours_remaining']) && !empty($item['expiry_hours'])) {
                    $hours_remaining = round($item['hours_remaining'], 1);
                    if ($hours_remaining < 1) {
                        $minutes_remaining = round($hours_remaining * 60);
                        $text .= sprintf(' <span class="iwp-expiry-warning" title="%s">(%d min)</span>',
                            esc_attr__('Time remaining until expiry', 'iwp-wp-integration'),
                            $minutes_remaining
                        );
                    } else {
                        $text .= sprintf(' <span class="iwp-expiry-info" title="%s">(%d hrs)</span>',
                            esc_attr__('Time remaining until expiry', 'iwp-wp-integration'),
                            $hours_remaining
                        );
                    }
                }
                break;
            case 'progress':
                $class = 'iwp-status-progress';
                $text = __('Creating', 'iwp-wp-integration');
                break;
            case 'failed':
                $class = 'iwp-status-failed';
                $text = __('Failed', 'iwp-wp-integration');
                break;
            default:
                $class = 'iwp-status-unknown';
                $text = __('Unknown', 'iwp-wp-integration');
        }

        return sprintf('<span class="iwp-status %s">%s</span>', $class, $text);
    }

    /**
     * Display created column
     *
     * @param array $item Site data
     * @return string
     */
    public function column_created($item) {
        if (empty($item['created'])) {
            return '—';
        }

        $created = strtotime($item['created']);
        if ($created) {
            return sprintf(
                '<time datetime="%s" title="%s">%s</time>',
                date('c', $created),
                date('Y-m-d H:i:s', $created),
                human_time_diff($created, current_time('timestamp')) . ' ago'
            );
        }

        return esc_html($item['created']);
    }

    // Note: column_actions method removed - all actions now consolidated in site_url column

    /**
     * Handle default column display
     *
     * @param array $item Site data
     * @param string $column_name Column name
     * @return string
     */
    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '—';
    }

    /**
     * Display when no items found
     */
    public function no_items() {
        esc_html_e('No InstaWP sites found. Sites will appear here after they are created through WooCommerce orders or shortcodes.', 'iwp-wp-integration');
    }
}