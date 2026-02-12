# InstaWP Integration Plugin - Technical Documentation

## Overview

The InstaWP Integration plugin is a comprehensive WordPress plugin that provides seamless integration with InstaWP's site creation and management platform. Originally designed as "IWP WooCommerce Integration v2", it has evolved into a full-featured WordPress plugin supporting multiple integration points and standalone functionality.

**Current Version**: 0.0.4
**WordPress Compatibility**: 5.0+
**WooCommerce Compatibility**: 5.0+ (Optional)
**PHP Requirements**: 7.4+  

## Architecture

### Plugin Structure

```
iwp-wp-integration/
├── iwp-wp-integration.php          # Main plugin file
├── includes/
│   ├── core/                       # Core functionality
│   │   ├── class-iwp-main.php      # Main plugin controller
│   │   ├── class-iwp-api-client.php # InstaWP API integration
│   │   ├── class-iwp-site-manager.php # Site creation/management
│   │   ├── class-iwp-sites-model.php # Database operations
│   │   ├── class-iwp-service.php    # Service layer
│   │   ├── class-iwp-logger.php     # Structured logging
│   │   ├── class-iwp-database.php   # Database helpers
│   │   ├── class-iwp-security.php   # Security utilities
│   │   ├── class-iwp-utilities.php  # General utilities
│   │   ├── class-iwp-installer.php  # Installation/activation
│   │   ├── class-iwp-autoloader.php # PSR-4 autoloader
│   │   ├── class-iwp-shortcode.php  # Shortcode functionality
│   │   ├── class-iwp-form-helper.php # Form generation
│   │   └── class-iwp-github-updater.php # GitHub auto-updater
│   ├── admin/                      # Administrative interface
│   │   ├── class-iwp-admin-simple.php # Simplified admin
│   │   ├── class-iwp-settings-page.php # Settings management
│   │   └── class-iwp-sites-list-table.php # Sites table
│   ├── frontend/                   # Customer-facing features
│   │   └── class-iwp-frontend.php   # Frontend integration
│   └── integrations/               # Third-party integrations
│       └── woocommerce/            # WooCommerce integration
│           ├── class-iwp-woo-product-integration.php
│           ├── class-iwp-woo-order-processor.php
│           ├── class-iwp-woo-hpos.php
│           ├── class-iwp-woo-product-fields.php
│           ├── class-iwp-woo-subscriptions-integration.php
│           └── class-iwp-woo-subscription-site-manager.php
└── assets/                         # Static assets
    ├── css/                        # Stylesheets
    └── js/                         # JavaScript files
```

### Design Patterns

- **Singleton Pattern**: Main plugin class for global access
- **Service Layer**: Centralized business logic
- **Repository Pattern**: Database operations through models
- **Observer Pattern**: WordPress hooks and actions
- **Strategy Pattern**: Different integration handlers

## Core Components

### 1. API Client (`class-iwp-api-client.php`)

Handles all communication with InstaWP's REST API.

#### Key Features
- **Authentication**: Bearer token with API key
- **Endpoints**: Complete API coverage (v1 and v2)
- **Caching**: Intelligent caching with TTL
- **Error Handling**: Comprehensive WordPress error integration
- **Rate Limiting**: Built-in request throttling
- **Logging**: Detailed debug logging with sanitized responses

#### Main Methods
```php
// Site Operations
create_site_from_snapshot($snapshot_slug, $site_data, $plan_id = null)
get_site_details($site_id)
upgrade_site_plan($site_id, $plan_id)
delete_site($site_id)

// Data Retrieval
get_snapshots($team_id = null, $force_refresh = false)
get_plans($team_id = null, $force_refresh = false)
get_teams()

// Task Management
get_task_status($task_id)

// Domain Management
add_domain_to_site($site_id, $domain_name, $domain_type = 'primary')

// Demo Helper Integration
disable_demo_helper($site_id, $site_url = '')
```

#### API Endpoints Used
| Endpoint | Method | Purpose | API Version |
|----------|--------|---------|-------------|
| `/teams` | GET | Fetch user teams | v2 |
| `/snapshots` | GET | Get available snapshots | v2 |
| `/get-plans` | GET | Get hosting plans | v2 |
| `/sites/template` | POST | Create site from snapshot | v2 |
| `/tasks/{id}/status` | GET | Check task progress | v2 |
| `/sites/{id}` | GET | Get site details | v2 |
| `/sites/{id}/upgrade-plan` | POST | Upgrade site plan | v2 |
| `/sites/{id}` | DELETE | Delete site | v2 |
| `/site/add-domain/{id}` | POST | Add custom domain | v1 |

### 2. Site Manager (`class-iwp-site-manager.php`)

Manages site creation workflows and status tracking.

#### Key Features
- **Site Creation**: Orchestrates API calls and database storage
- **Status Tracking**: Real-time progress monitoring
- **Pool Detection**: Handles instant vs. task-based sites
- **Credential Management**: Secure storage and retrieval
- **Error Recovery**: Comprehensive error handling

#### Workflow
1. **Initial Creation**: API call to create site
2. **Database Storage**: Store site record with pending status
3. **Status Monitoring**: Poll task status for non-pool sites
4. **Credential Fetching**: Get final credentials when complete
5. **Database Update**: Update with completed status and credentials

### 3. Sites Model (`class-iwp-sites-model.php`)

Database operations for the `wp_iwp_sites` table.

#### Database Schema
```sql
CREATE TABLE wp_iwp_sites (
    id bigint(20) AUTO_INCREMENT PRIMARY KEY,
    site_id varchar(255) NOT NULL,
    site_url varchar(500),
    wp_username varchar(255),
    wp_password varchar(255),
    wp_admin_url varchar(500),
    s_hash varchar(500),
    status varchar(50) DEFAULT 'creating',
    site_type varchar(50) DEFAULT 'paid',  -- NEW in v0.0.3: 'demo', 'paid', 'trial'
    task_id varchar(255),
    snapshot_slug varchar(255),
    plan_id varchar(255),
    product_id bigint(20),
    order_id bigint(20),
    user_id bigint(20) DEFAULT 0,
    source varchar(100) DEFAULT 'woocommerce',
    source_data longtext,
    is_pool tinyint(1) DEFAULT 0,
    is_reserved tinyint(1) DEFAULT 1,
    expiry_hours int(11),
    api_response longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_site_id (site_id),
    INDEX idx_status (status),
    INDEX idx_site_type (site_type),  -- NEW in v0.0.3
    INDEX idx_order_id (order_id),
    INDEX idx_user_id (user_id)
);
```

#### Key Methods
```php
// CRUD Operations
create($data)                    // Create new site record
update($site_id, $data)          // Update existing site
get_by_site_id($site_id)         // Retrieve by InstaWP site ID
delete($site_id)                 // Delete site record

// Queries
get_pending_sites()              // Get sites being created
get_by_order_id($order_id)       // Get sites for specific order
get_by_user_id($user_id)         // Get user's sites
get_total_count($filters)        // Count with filters

// Demo Site Queries (NEW in v0.0.3)
get_demo_sites_by_email($email)  // Find demo sites by email for reconciliation
get_demo_sites_by_user($user_id) // Get user's demo sites
mark_expired_demos()             // Mark expired demo sites

// Plan Management
update_plan($site_id, $plan_id, $upgrade_data)
get_upgrade_history($site_id)
```

### 4. Frontend Integration (`class-iwp-frontend.php`)

Customer-facing features and WooCommerce integration.

#### Integration Points
- **Thank You Pages**: Display sites after checkout
- **Order Details**: Show sites in My Account order view
- **Email Notifications**: Include site details in emails
- **Dashboard**: Customer site overview
- **Domain Mapping**: Custom domain interface

#### Key Features
- **Magic Login**: Direct WordPress admin access via s_hash
- **Responsive Design**: Mobile-optimized interfaces
- **Real-time Updates**: Auto-refresh for pending sites
- **Security**: Proper nonce verification and capability checks
- **Error Handling**: Graceful degradation

#### Hook Positioning
```php
// Positioned right after order table for better visibility
add_action('woocommerce_order_details_after_order_table', 
    array($this, 'display_order_sites_after_table'), 10);

// Thank you page integration
add_action('woocommerce_thankyou', 
    array($this, 'display_order_sites_thankyou'), 10);

// Email integration
add_action('woocommerce_email_order_details', 
    array($this, 'add_sites_to_emails'), 15, 4);
```

### 5. Shortcode System (`class-iwp-shortcode.php`)

Standalone site creation functionality.

#### Shortcode: `[iwp_site_creator]`

**Basic Usage:**
```
[iwp_site_creator snapshot_slug="wordpress-blog"]
```

**Advanced Usage:**
```
[iwp_site_creator 
    snapshot_slug="ecommerce-store" 
    email="customer@example.com" 
    name="My Store"
    expiry_hours="24"
    sandbox="true"]
```

**Parameters:**
- `snapshot_slug` (required): InstaWP snapshot identifier
- `email` (optional): Pre-fill email field
- `name` (optional): Pre-fill site name field
- `expiry_hours` (optional): Site expiration (1-8760 hours)
- `sandbox` (optional): Create shared/sandbox site

#### Features
- **Real-time Progress**: Live status updates during creation
- **Mobile Responsive**: Touch-friendly interface
- **Form Validation**: Client and server-side validation
- **Error Handling**: Comprehensive error management
- **Pool Support**: Handles instant and task-based creation
- **Demo Site Storage**: Automatic database storage for reconciliation

### 6. Demo Site Storage & Reconciliation

Automatic system for storing demo sites and converting them to paid sites upon purchase.

#### Overview

All sites created via the `[iwp_site_creator]` shortcode are automatically stored in the database with `site_type='demo'`. When a customer makes a purchase, the system finds matching demo sites by email and converts them to paid sites.

#### Database Schema

```sql
-- Added in version 0.0.3
site_type varchar(50) DEFAULT 'paid',  -- Values: 'demo', 'paid', 'trial'
INDEX idx_site_type (site_type)
```

#### Storage Process

When a site is created via shortcode (`class-iwp-shortcode.php:250-320`):

```php
private function store_demo_site_in_database($response_data, $email, $site_name, $snapshot_slug, $expiry_hours = null) {
    try {
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;

        $site_data = array(
            'site_id' => $response_data['site_id'],
            'site_url' => isset($response_data['site_url']) ? $response_data['site_url'] : '',
            'wp_username' => isset($response_data['admin_username']) ? $response_data['admin_username'] : '',
            'wp_password' => isset($response_data['admin_password']) ? $response_data['admin_password'] : '',
            'status' => $response_data['status'],
            'site_type' => 'demo', // Mark as demo site
            'user_id' => $user_id, // 0 for guests, user_id for logged-in
            'source' => 'shortcode',
            'source_data' => array(
                'email' => $email, // Store for reconciliation
                'site_name' => $site_name,
                'snapshot_slug' => $snapshot_slug,
                'created_via' => 'shortcode'
            ),
            'is_reserved' => !empty($expiry_hours) ? false : true,
            'expiry_hours' => !empty($expiry_hours) ? intval($expiry_hours) : null,
        );

        IWP_Sites_Model::init();
        $db_site_id = IWP_Sites_Model::create($site_data);

        return $db_site_id;
    } catch (Exception $e) {
        IWP_Logger::error('Exception storing demo site in database', 'shortcode', array(
            'site_id' => $response_data['site_id'] ?? 'unknown',
            'error' => $e->getMessage()
        ));
        return false;
    }
}
```

#### Reconciliation Process

Automatic conversion when customer purchases (`class-iwp-woo-order-processor.php:921-1005`):

```php
private function reconcile_demo_sites_to_order($order) {
    $billing_email = $order->get_billing_email();
    $customer_id = $order->get_customer_id();
    $order_id = $order->get_id();

    if (empty($billing_email)) {
        return array();
    }

    // Find demo sites with matching email using JSON_EXTRACT
    IWP_Sites_Model::init();
    $demo_sites = IWP_Sites_Model::get_demo_sites_by_email($billing_email);

    if (empty($demo_sites)) {
        return array();
    }

    $reconciled_sites = array();

    foreach ($demo_sites as $demo_site) {
        // Get subscription ID if available
        $subscription_id = null;
        if (function_exists('wcs_get_subscriptions_for_order')) {
            $subscriptions = wcs_get_subscriptions_for_order($order_id);
            if (!empty($subscriptions)) {
                $subscription = reset($subscriptions);
                $subscription_id = $subscription->get_id();
            }
        }

        // Update source_data with subscription info
        $source_data = json_decode($demo_site->source_data, true);
        $source_data['subscription_id'] = $subscription_id;
        $source_data['converted_at'] = current_time('mysql');
        $source_data['converted_from'] = 'demo';

        // Convert demo to paid
        $success = IWP_Sites_Model::update($demo_site->site_id, array(
            'site_type' => 'paid',
            'order_id' => $order_id,
            'user_id' => $customer_id,
            'source' => 'demo_to_paid',
            'source_data' => $source_data,
        ));

        if ($success) {
            $reconciled_sites[] = $demo_site->site_id;
            $this->add_reconciled_site_to_order_meta($order_id, $demo_site);
            $this->disable_demo_helper_for_site($demo_site);
        }
    }

    // Add order note documenting conversion
    if (!empty($reconciled_sites)) {
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
```

#### Query Methods

Email-based reconciliation query (`class-iwp-sites-model.php:453-470`):

```php
public static function get_demo_sites_by_email($email) {
    global $wpdb;
    if (!self::$table_name) {
        self::init();
    }

    // Uses MySQL JSON_EXTRACT for email matching
    $sites = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM " . self::$table_name . "
         WHERE site_type = 'demo'
         AND order_id IS NULL
         AND JSON_EXTRACT(source_data, '$.email') = %s
         ORDER BY created_at DESC",
        $email
    ));

    return $sites;
}
```

#### Go Live Page Redirect

Prevents users with paid sites from accessing demo creation pages (`class-iwp-golive-page.php`):

```php
class IWP_GoLive_Page {
    public function __construct() {
        add_action('template_redirect', array($this, 'check_golive_page_access'));
    }

    public function check_golive_page_access() {
        // Check if user is on Go Live page
        if (!is_page('go-live') && !is_page('launch-your-demo-site')) {
            return;
        }

        // Only redirect logged-in users
        $user_id = get_current_user_id();
        if (!$user_id) {
            return; // Guests can access
        }

        // Check if user has any paid sites
        IWP_Sites_Model::init();
        $user_sites = IWP_Sites_Model::get_by_user_id($user_id);

        $has_paid_site = false;
        foreach ($user_sites as $site) {
            if ($site->site_type === 'paid') {
                $has_paid_site = true;
                break;
            }
        }

        // Redirect to My Account if user has paid site
        if ($has_paid_site) {
            if (function_exists('wc_get_page_permalink')) {
                wp_safe_redirect(wc_get_page_permalink('myaccount'));
            } else {
                wp_safe_redirect(home_url('/my-account'));
            }
            exit;
        }
    }
}
```

#### Frontend Display

Demo conversion badge (`class-iwp-frontend.php:560`):

```php
// Add demo badge if this site was converted from demo
if ($action === 'reconciled') {
    echo '<span class="iwp-site-badge iwp-demo-badge">' .
         esc_html__('Converted from Demo', 'iwp-wp-integration') .
         '</span>';
}
```

CSS styling (`assets/css/frontend.css`):

```css
.iwp-demo-badge {
    background: #ffa500;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    margin-left: 8px;
}
```

#### Database Migration

Automatic version-aware migration system (`class-iwp-installer.php:425`):

```php
// Database version tracking
private static $db_updates = array(
    '0.0.1' => array(...),
    '2.1.0' => array(...),
    '0.0.3' => array(
        array('IWP_Installer', 'add_site_type_column'),
    ),
);

public static function add_site_type_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'iwp_sites';

    // Check if column already exists (idempotent)
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'site_type'");

    if (empty($column_exists)) {
        // Add site_type column
        $result = $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN site_type VARCHAR(50) DEFAULT 'paid' AFTER status");

        if ($result !== false) {
            // Add index for performance
            $wpdb->query("CREATE INDEX idx_site_type ON {$table_name}(site_type)");
            error_log('InstaWP Integration: Successfully added site_type column');
        }
    }
}

// Automatic update check on plugin load
public static function needs_database_update() {
    $current_db_version = get_option('iwp_db_version', '0.0.0');
    return version_compare($current_db_version, IWP_VERSION, '<');
}

public static function update_database() {
    $current_db_version = get_option('iwp_db_version', '0.0.0');

    foreach (self::$db_updates as $version => $update_callbacks) {
        if (version_compare($current_db_version, $version, '<')) {
            foreach ($update_callbacks as $update) {
                call_user_func($update);
            }
        }
    }

    update_option('iwp_db_version', IWP_VERSION);
}
```

Main plugin file initialization (`iwp-wp-integration.php:50-55`):

```php
// Check if database needs updating
if (IWP_Installer::needs_database_update()) {
    IWP_Installer::update_database();
}
```

#### Benefits

✅ **No Data Loss**: Demo sites never lost, always tied to customer accounts
✅ **Seamless Experience**: Customers continue using same site after payment
✅ **Multiple Sites**: All demo sites with matching email converted
✅ **Email Matching**: Works even if customer creates account after demo
✅ **Zero Config**: Automatic - no setup required
✅ **Backward Compatible**: Existing sites default to `site_type='paid'`
✅ **Idempotent Migrations**: Safe to run multiple times

## WooCommerce Integration

### Product Configuration

Products can be enhanced with InstaWP functionality through the "InstaWP" tab.

#### Configuration Options
- **Snapshot Selection**: Choose from available snapshots
- **Plan Selection**: Optional hosting plan assignment
- **Auto-Create**: Automatically create sites on order completion
- **Site Expiry**: Configure permanent vs. temporary sites

#### Site Expiry Settings
```php
// Permanent Sites (default)
$expiry_type = 'permanent';     // is_reserved: true
$expiry_hours = null;           // No expiration

// Temporary Sites
$expiry_type = 'temporary';     // is_reserved: false  
$expiry_hours = 24;             // 1-8760 hours
```

### Order Processing

Automatic site creation triggered by order status changes.

#### Process Flow
1. **Order Completion**: `woocommerce_order_status_completed` hook
2. **Product Analysis**: Check for InstaWP-enabled products
3. **Site Creation**: API call with order details
4. **Customer Notification**: Order notes and email updates
5. **Status Tracking**: Background monitoring until completion

#### Integration with Subscriptions

Full WooCommerce Subscriptions compatibility:

```php
// Subscription Events
wcs_subscription_status_active    // Site creation on activation
wcs_subscription_status_on-hold   // Site suspension
wcs_subscription_status_cancelled // Site termination
wcs_renewal_order_created         // Renewal processing
```

### Advanced Features

#### Site Upgrade Functionality

Allow customers to upgrade existing sites instead of creating new ones.

**Workflow:**
1. Customer visits: `shop.example.com/?site_id=123`
2. Plugin enters "upgrade mode" (session storage)
3. Customer purchases plan-enabled product
4. Existing site upgraded instead of new creation
5. Order meta updated with upgrade details

**Implementation:**
```php
// URL Parameter Detection
if (isset($_GET['site_id']) && is_numeric($_GET['site_id'])) {
    $_SESSION['iwp_site_id_for_upgrade'] = intval($_GET['site_id']);
    // Show upgrade notice to customer
}

// Order Processing Check
$upgrade_site_id = $_SESSION['iwp_site_id_for_upgrade'] ?? null;
if ($upgrade_site_id && !empty($plan_id)) {
    // Upgrade existing site instead of creating new
    $this->upgrade_site_plan($order, $product, $upgrade_site_id, $plan_id);
}
```

#### Custom Domain Mapping

Customer interface for mapping custom domains to their sites.

**Features:**
- **Domain Types**: Primary and Alias domain support
- **DNS Guidance**: Step-by-step CNAME setup instructions
- **Validation**: Client and server-side domain validation
- **History**: Track all mapped domains per order

**Implementation:**
```php
// API Integration
add_domain_to_site($site_id, 'example.com', 'primary');

// Customer Interface
// Modal with DNS instructions and domain input form
// AJAX handler for domain mapping requests
```

#### Magic Login Integration

Seamless WordPress admin access using InstaWP's auto-login system.

**Features:**
- **s_hash Authentication**: Secure token-based login
- **Universal Access**: Works across all touchpoints
- **Graceful Fallback**: Regular wp-admin if s_hash unavailable

**Implementation:**
```php
// Magic Login URL Construction
if (!empty($s_hash)) {
    $magic_login_url = 'https://app.instawp.io/wordpress-auto-login?site=' . urlencode($s_hash);
}

// Frontend Display
// "Magic Login" button replaces "Admin Login" when s_hash available
```

## Admin Interface

### Simplified Admin System

Clean, tabbed interface replacing complex legacy systems.

#### Menu Structure
- **InstaWP** (Main Menu)
  - **Sites** (Site management table)
  - **Settings** (Plugin configuration)

#### Sites Management

WordPress-style table with row actions:

```php
// Available Actions
'visit'       => Visit Site (opens site URL)
'magic_login' => Magic Login (s_hash based)
'admin_login' => Admin Login (fallback)
'delete'      => Delete Site (with confirmation)
```

#### Settings Interface

Tabbed configuration system:

1. **General Settings**
   - Master enable/disable
   - Auto-create sites option
   - Site upgrade parameter option

2. **InstaWP Data**
   - Team selection dropdown
   - Snapshots management (with refresh)
   - Plans management (with refresh)
   - Cache status indicators

3. **Testing & Development**
   - Debug mode toggle
   - Test order creation
   - Log level configuration

### Test Order System

Comprehensive testing functionality for development and validation.

#### Customer Options
```php
// 1. Use Existing User (Default)
// Select from dropdown of site users
$test_user = get_users(array('number' => 50));

// 2. Guest Checkout
// Manual entry of guest details
$guest_data = array('email', 'first_name', 'last_name');

// 3. Create New User
// Automatic user account creation
$new_user = array('username', 'email', 'first_name', 'last_name');
wp_insert_user($new_user);
```

#### Testing Workflows
- **Customer Experience Testing**: Login as created user to verify site access
- **Order Processing Testing**: Verify automatic site creation
- **Email Testing**: Check notification delivery and formatting
- **Error Handling Testing**: Verify graceful failure scenarios

## Security Implementation

### Input Validation and Sanitization

All user inputs are properly sanitized using WordPress functions:

```php
// Text Sanitization
$clean_text = sanitize_text_field($_POST['field']);

// URL Sanitization  
$clean_url = esc_url_raw($_POST['url']);

// Email Validation
$clean_email = sanitize_email($_POST['email']);

// JSON Data
$clean_json = wp_json_encode($data);
```

### CSRF Protection

Comprehensive nonce verification on all forms and AJAX requests:

```php
// Nonce Generation
wp_nonce_field('iwp_admin_action', 'iwp_nonce');

// Nonce Verification
if (!wp_verify_nonce($_POST['iwp_nonce'], 'iwp_admin_action')) {
    wp_die(__('Security check failed', 'iwp-wp-integration'));
}

// AJAX Nonce Verification (Centralized)
IWP_Security::validate_ajax_request('iwp_admin_nonce', 'manage_options', 'nonce');
```

### Capability Checks

Proper user permission verification throughout the plugin:

```php
// Admin Operations
if (!current_user_can('manage_options')) {
    wp_die(__('Insufficient permissions', 'iwp-wp-integration'));
}

// WooCommerce Operations
if (!current_user_can('manage_woocommerce')) {
    wp_die(__('Insufficient permissions', 'iwp-wp-integration'));
}

// Product Management
if (!current_user_can('edit_products')) {
    return;
}
```

### SQL Injection Prevention

All database operations use prepared statements:

```php
// Parameterized Queries
$wpdb->prepare("SELECT * FROM {$table} WHERE site_id = %s", $site_id);

// Array Value Sanitization
foreach ($data as $key => $value) {
    if (is_array($value)) {
        $data[$key] = wp_json_encode($value);  // Fixed: No more array values in wpdb
    }
}
```

### Password Security

Secure handling of site credentials:

```php
// Password Generation
$password = wp_generate_password(12, false);

// Storage (Direct Database)
// Passwords stored as plain text for customer access
// (This is intentional for customer convenience)

// Frontend Display
// Hidden by default with toggle functionality
// Copy-to-clipboard with secure clipboard API
```

## Performance Optimizations

### Caching Strategy

Intelligent caching for expensive operations:

```php
// Snapshots Cache (15 minutes)
$cache_key = 'iwp_snapshots_' . ($team_id ?: 'all');
$snapshots = get_transient($cache_key);
if (false === $snapshots) {
    $snapshots = $this->api_client->get_snapshots($team_id);
    set_transient($cache_key, $snapshots, 15 * MINUTE_IN_SECONDS);
}

// Plans Cache (1 hour)  
$cache_key = 'iwp_plans_' . ($team_id ?: 'all');
$plans = get_transient($cache_key);
if (false === $plans) {
    $plans = $this->api_client->get_plans($team_id);
    set_transient($cache_key, $plans, HOUR_IN_SECONDS);
}
```

### Database Optimizations

Efficient queries with proper indexing:

```sql
-- Key Indexes
INDEX idx_site_id (site_id)      -- Primary lookups
INDEX idx_status (status)        -- Status filtering
INDEX idx_site_type (site_type)  -- NEW in v0.0.3: Demo/paid filtering
INDEX idx_order_id (order_id)    -- Order associations
INDEX idx_user_id (user_id)      -- User queries
INDEX idx_created_at (created_at) -- Time-based queries
```

### Conditional Loading

Resources loaded only when needed:

```php
// Admin Scripts (Only on plugin pages)
if (strpos($hook, 'instawp-integration') !== false) {
    wp_enqueue_script('iwp-admin');
}

// Frontend Scripts (Only on WooCommerce pages)
if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) {
    return; // Skip loading
}

// Integration Classes (Only when WooCommerce active)
if (class_exists('WooCommerce')) {
    new IWP_Woo_Order_Processor();
}
```

### API Rate Limiting

Built-in request throttling:

```php
// Request Timing
$last_request = get_transient('iwp_last_api_request_' . $endpoint);
if ($last_request && (time() - $last_request) < $this->min_request_interval) {
    sleep($this->min_request_interval);
}

// Request Tracking
set_transient('iwp_last_api_request_' . $endpoint, time(), 60);
```

## Error Handling and Logging

### Structured Logging System

Comprehensive logging with context and levels:

```php
// Log Levels
IWP_Logger::debug($message, $context, $data);   // Development info
IWP_Logger::info($message, $context, $data);    // General info
IWP_Logger::warning($message, $context, $data); // Warnings
IWP_Logger::error($message, $context, $data);   // Errors

// Context Examples
'api-client'     // API communication
'site-manager'   // Site operations
'order-processor' // Order handling
'frontend'       // Customer interface
'admin'          // Administrative actions
```

### API Error Handling

Comprehensive API error management:

```php
// WP_Error Integration
if (is_wp_error($response)) {
    IWP_Logger::error('API request failed', 'api-client', array(
        'endpoint' => $endpoint,
        'error_code' => $response->get_error_code(),
        'error_message' => $response->get_error_message()
    ));
    return $response;
}

// HTTP Status Handling
$status_code = wp_remote_retrieve_response_code($response);
if ($status_code >= 400) {
    return new WP_Error('api_error', 'API returned error status: ' . $status_code);
}
```

### Database Error Recovery

Graceful handling of database issues:

```php
// Transaction Safety
$wpdb->query('START TRANSACTION');
try {
    $result = $wpdb->update($table, $data, $where);
    if ($result === false) {
        throw new Exception('Database update failed: ' . $wpdb->last_error);
    }
    $wpdb->query('COMMIT');
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    IWP_Logger::error('Database operation failed', 'sites-model', array(
        'error' => $e->getMessage(),
        'query' => $wpdb->last_query
    ));
    return false;
}
```

### Frontend Error Handling

User-friendly error messages:

```php
// AJAX Error Responses
wp_send_json_error(array(
    'message' => __('Failed to create site. Please try again.', 'iwp-wp-integration'),
    'code' => 'site_creation_failed',
    'debug' => $debug_info // Only in debug mode
));

// Form Validation Errors
$errors = array();
if (empty($snapshot_slug)) {
    $errors[] = __('Snapshot is required', 'iwp-wp-integration');
}
if (!empty($errors)) {
    wp_send_json_error(array('validation_errors' => $errors));
}
```

## Recent Bug Fixes and Improvements

### Fixed wpdb::prepare Array Error

**Issue**: The `IWP_Sites_Model::update()` method was passing array values (specifically `api_response`) directly to `wpdb->update()`, causing the error:
```
Notice: Function wpdb::prepare was called incorrectly. Unsupported value type (array).
```

**Solution**: Added data sanitization loop to JSON-encode array values before database storage:

```php
// Sanitize data for database storage
$sanitized_data = array();
foreach ($data as $key => $value) {
    if (is_array($value)) {
        // JSON encode array values for database storage
        $sanitized_data[$key] = wp_json_encode($value);
    } else {
        $sanitized_data[$key] = $value;
    }
}
```

### Fixed Frontend Credential Display

**Issue**: Username and password weren't displaying in frontend even when correctly stored in database.

**Problem**: The `transform_site_data_for_frontend()` method was looking for credentials in the JSON `api_response` field instead of direct database columns.

**Solution**: Updated credential retrieval to prioritize direct database fields:

```php
// Update site details from database fields (direct columns)
if (!empty($db_site->wp_username) && empty($raw_site_data['wp_username'])) {
    $raw_site_data['wp_username'] = $db_site->wp_username;
}
if (!empty($db_site->wp_password) && empty($raw_site_data['wp_password'])) {
    $raw_site_data['wp_password'] = $db_site->wp_password;
}
```

### Improved Frontend Positioning

**Issue**: InstaWP site information was displaying at the bottom of order pages.

**Solution**: Changed hook from `woocommerce_view_order` to `woocommerce_order_details_after_order_table` for better positioning:

```php
// Old: Displayed at bottom
add_action('woocommerce_view_order', array($this, 'display_order_sites_view'), 10);

// New: Displayed after order table
add_action('woocommerce_order_details_after_order_table', array($this, 'display_order_sites_after_table'), 10);
```

### WooCommerce Subscriptions Integration

Fixed argument count errors in subscription hooks by making parameters optional:

```php
// Before: Required parameters caused fatal errors
public function handle_subscription_active($subscription, $old_status) {

// After: Optional parameters prevent errors
public function handle_subscription_active($subscription, $old_status = '') {
```

### Demo Helper Auto-Disable

Added automatic disabling of `iwp-demo-helper` plugin when sites upgrade from demo/trial to paid plans:

```php
// Integration points
// 1. After plan upgrades via WooCommerce orders
// 2. When site status changes from temporary to permanent

public function disable_demo_helper($site_id, $site_url = '') {
    $demo_helper_url = trailingslashit($site_url) . 'wp-json/iwp-demo-helper/v1/disable';
    // Makes REST API call to disable the plugin
}
```

## Team Management System

### Team Selection Feature

The plugin supports InstaWP's team functionality, allowing users to work with specific teams and filter data accordingly.

#### Implementation

```php
// Team Dropdown in Settings
$teams = $this->api_client->get_teams();
echo '<select name="iwp_options[selected_team_id]">';
echo '<option value="">' . __('User\'s Logged In Team', 'iwp-wp-integration') . '</option>';
foreach ($teams as $team) {
    $selected = selected($options['selected_team_id'], $team['id'], false);
    echo '<option value="' . esc_attr($team['id']) . '"' . $selected . '>';
    echo esc_html($team['name']);
    echo '</option>';
}
echo '</select>';
```

#### Team-Filtered API Calls

```php
// Snapshots with team filter
$team_id = $this->get_selected_team_id();
$snapshots = $this->api_client->get_snapshots($team_id);

// API URL construction
$url = 'https://app.instawp.io/api/v2/snapshots';
if ($team_id) {
    $url .= '?team_id=' . intval($team_id);
}
```

#### Caching per Team

```php
// Team-specific cache keys
$cache_key = 'iwp_snapshots_' . ($team_id ?: 'all');
$snapshots = get_transient($cache_key);

// Cache invalidation
delete_transient('iwp_snapshots_' . ($team_id ?: 'all'));
delete_transient('iwp_plans_' . ($team_id ?: 'all'));
```

## Database Schema Details

### Options Storage

```php
// Main plugin options
$iwp_options = array(
    'api_key' => '',                    // InstaWP API key
    'enable_integration' => true,       // Master toggle
    'auto_create_sites' => true,        // Auto-create on order completion
    'use_site_id_parameter' => true,    // Enable site upgrade functionality
    'selected_team_id' => '',           // Selected team for filtering
    'debug_mode' => false,              // Debug logging
    'log_level' => 'info'               // Logging verbosity
);

// Pending sites tracking
$pending_sites = array(
    'task_id_123' => array(
        'site_id' => 'pending-abc123',
        'order_id' => 456,
        'started_at' => '2025-01-01 12:00:00'
    )
);
```

### Order Meta Keys

```php
// WooCommerce order meta
'_iwp_sites_created'    // Array of created/upgraded sites
'_iwp_mapped_domains'   // Array of custom domain mappings
'_iwp_site_upgrades'    // Array of site upgrade details
'_iwp_processed'        // Order processing flag
'_iwp_processed_date'   // Processing timestamp
```

### Product Meta Keys

```php
// WooCommerce product meta
'_iwp_selected_snapshot'    // Selected snapshot slug
'_iwp_selected_plan'        // Selected plan ID
'_iwp_auto_create_site'     // Auto-create setting (yes/no)
'_iwp_site_expiry_type'     // 'permanent' or 'temporary'
'_iwp_site_expiry_hours'    // Expiry hours for temporary sites
```

### Transient Keys

```php
// Cache keys with TTL
'iwp_snapshots_all'         // All snapshots (15 min)
'iwp_snapshots_{team_id}'   // Team-specific snapshots (15 min)
'iwp_plans_all'             // All plans (1 hour)
'iwp_plans_{team_id}'       // Team-specific plans (1 hour)
'iwp_teams'                 // User teams (1 hour)
'iwp_users_dropdown_{limit}' // Users dropdown cache (5 min)
```

## Development Guidelines

### Code Standards

- **WordPress Coding Standards**: PSR-2 compatible
- **Security First**: All inputs sanitized, outputs escaped
- **Error Handling**: Comprehensive WP_Error usage
- **Documentation**: PHPDoc for all methods
- **Testing**: Manual testing workflows documented

### Adding New Features

1. **Core Functionality**: Add to `includes/core/`
2. **Admin Features**: Add to `includes/admin/`
3. **Frontend Features**: Add to `includes/frontend/`
4. **Integrations**: Add to `includes/integrations/{platform}/`
5. **Assets**: Add CSS/JS to `assets/`

### Database Operations

```php
// Always use the model layer
$site_data = IWP_Sites_Model::get_by_site_id($site_id);
$success = IWP_Sites_Model::update($site_id, $update_data);

// Use helper methods for complex operations
IWP_Database::append_order_meta($order_id, '_iwp_sites_created', $site_data);

// Proper error handling
if (!$success) {
    IWP_Logger::error('Failed to update site', 'sites-model', array(
        'site_id' => $site_id,
        'data' => $update_data
    ));
    return new WP_Error('update_failed', 'Could not update site record');
}
```

### API Integration

```php
// Always use the API client
$api_client = new IWP_API_Client();
$api_client->set_api_key($api_key);

// Handle errors properly
$result = $api_client->create_site_from_snapshot($slug, $data, $plan_id);
if (is_wp_error($result)) {
    IWP_Logger::error('Site creation failed', 'api-client', array(
        'error' => $result->get_error_message(),
        'snapshot' => $slug
    ));
    return $result;
}

// Log successful operations
IWP_Logger::info('Site created successfully', 'api-client', array(
    'site_id' => $result['data']['id'],
    'snapshot' => $slug
));
```

### Security Checklist

- [ ] All inputs sanitized with appropriate WordPress functions
- [ ] All outputs escaped (`esc_html`, `esc_attr`, `esc_url`)
- [ ] Nonces verified on all forms and AJAX requests
- [ ] Capability checks for all administrative operations
- [ ] SQL prepared statements for all database queries
- [ ] No sensitive data in debug logs
- [ ] Proper error messages (no internal details exposed)

## Testing Procedures

### Manual Testing Checklist

#### Basic Functionality
- [ ] Plugin activation/deactivation without errors
- [ ] Settings save and load correctly
- [ ] API connectivity with valid/invalid keys
- [ ] Snapshots and plans load correctly
- [ ] Team filtering works properly

#### WooCommerce Integration
- [ ] Product configuration saves correctly
- [ ] Sites create automatically on order completion
- [ ] Customer can view sites in My Account
- [ ] Email notifications include site details
- [ ] Magic login works when s_hash available

#### Frontend Features
- [ ] Shortcode renders correctly
- [ ] Site creation form validates input
- [ ] Real-time status updates work
- [ ] Mobile responsive design
- [ ] Copy-to-clipboard functionality

#### Advanced Features
- [ ] Site upgrade via URL parameter
- [ ] Custom domain mapping interface
- [ ] Demo helper auto-disable
- [ ] Subscription integration (if applicable)

#### Error Handling
- [ ] Invalid API key shows appropriate error
- [ ] Network failures handled gracefully
- [ ] Database errors logged properly
- [ ] User-friendly error messages displayed

### Performance Testing

```php
// Monitor API response times
$start_time = microtime(true);
$result = $api_client->get_snapshots();
$end_time = microtime(true);
$duration = ($end_time - $start_time) * 1000; // milliseconds

if ($duration > 5000) { // 5 seconds
    IWP_Logger::warning('Slow API response', 'performance', array(
        'endpoint' => 'snapshots',
        'duration_ms' => $duration
    ));
}
```

### Debug Mode Usage

Enable debug mode for troubleshooting:

1. **Enable Debug Mode**: InstaWP → Settings → Testing & Development
2. **Set Log Level**: Choose appropriate verbosity
3. **Reproduce Issue**: Trigger the problematic functionality
4. **Check Logs**: Review WordPress debug logs
5. **Filter Logs**: Look for entries containing "IWP" or specific context

## Deployment and Maintenance

### Pre-deployment Checklist

- [ ] All debug logging disabled in production
- [ ] Database schema updates tested
- [ ] Backward compatibility verified
- [ ] Performance impact assessed
- [ ] Security review completed
- [ ] Documentation updated

### Monitoring

```php
// Health check endpoints
add_action('wp_ajax_iwp_health_check', function() {
    $health = array(
        'api_connection' => $this->test_api_connection(),
        'database_status' => $this->check_database_health(),
        'cache_status' => $this->check_cache_status(),
        'pending_sites' => count($this->get_pending_sites())
    );
    wp_send_json_success($health);
});
```

### Backup Considerations

Critical data to backup:
- `wp_iwp_sites` table
- Order meta containing site information
- Plugin options and settings
- Customer email templates (if customized)

### Update Procedures

1. **Backup Database**: Especially `wp_iwp_sites` table
2. **Test in Staging**: Full functionality testing
3. **Check API Compatibility**: Verify InstaWP API changes
4. **Monitor Logs**: Watch for errors after deployment
5. **Validate Customer Experience**: Ensure frontend functionality

## GitHub Auto-Updater

### Overview

The plugin includes a built-in auto-updater (`class-iwp-github-updater.php`) that checks GitHub releases for new versions and integrates with WordPress's native plugin update system.

### How It Works

1. WordPress periodically checks for plugin updates via the `pre_set_site_transient_update_plugins` filter
2. The updater queries the GitHub API for the latest release: `GET /repos/InstaWP/iwp-wp-integration/releases/latest`
3. Compares the release tag version (stripped of `v` prefix) against `IWP_VERSION`
4. If a newer version exists, it registers an update in the WordPress transient
5. WordPress displays the update notification in the admin dashboard

### Key Features

- **15-minute cache**: GitHub API responses cached via `iwp_github_release` transient to avoid rate limits
- **Release asset detection**: Prefers `.zip` assets from GitHub Actions over source zipball
- **Plugin info modal**: Provides version, author, changelog in the WordPress "View Details" popup
- **Post-install rename**: Ensures the extracted folder matches `iwp-wp-integration` after upgrade
- **Zero configuration**: Works automatically — no API tokens or settings required

### Implementation

```php
// Loaded early in iwp-wp-integration.php (before plugins_loaded)
require_once IWP_PLUGIN_PATH . 'includes/core/class-iwp-github-updater.php';
new IWP_GitHub_Updater();
```

### WordPress Hooks Used

| Hook | Purpose |
|------|---------|
| `pre_set_site_transient_update_plugins` | Register available update |
| `plugins_api` | Provide plugin info for "View Details" modal |
| `upgrader_post_install` | Rename extracted directory after update |

### Transient Keys

```php
'iwp_github_release'  // Cached GitHub release data (15 min TTL)
```

## Custom Checkout Fields (Product Page)

### Overview

Customers can optionally choose their WP admin username and site subdomain when purchasing an IWP-enabled product. Both fields are optional — leaving them blank falls back to auto-generation.

### Implementation (`class-iwp-woo-product-fields.php`)

The class hooks into the full WooCommerce custom field chain:

| Hook | Method | Purpose |
|------|--------|---------|
| `woocommerce_before_add_to_cart_button` | `render_product_fields()` | Show fields on product page (only if product has `_iwp_selected_snapshot`) |
| `woocommerce_add_to_cart_validation` | `validate_fields()` | Server-side validation before add-to-cart |
| `woocommerce_add_cart_item_data` | `add_cart_item_data()` | Store validated values in cart item data |
| `woocommerce_get_item_data` | `display_cart_item_data()` | Display in cart & checkout review |
| `woocommerce_checkout_create_order_line_item` | `save_order_item_meta()` | Persist as order item meta |

### Fields

- **Username** — optional, alphanumeric + underscores, 3-20 chars, stored as `_iwp_admin_username`
- **Subdomain** — optional, alphanumeric + hyphens (no leading/trailing hyphen), 3-30 chars, stored as `_iwp_subdomain`

### Client-Side Validation (`assets/js/product-fields.js`)

- Real-time validation on input with visual feedback (green/red border)
- Auto-lowercase for subdomain field
- Prevents form submission if fields are invalid
- Scrolls to first error on validation failure

### Order Processing Integration

In `class-iwp-woo-order-processor.php`, `create_site_for_product()` reads custom values from order item meta:

```php
// Subdomain: use custom or auto-generate
$custom_subdomain = $item->get_meta('_iwp_subdomain');
if (!empty($custom_subdomain) && preg_match('/^[a-zA-Z0-9]...$/')) {
    $site_name = sanitize_title($custom_subdomain);
} else {
    $site_name = sanitize_title($product->get_name() . '-' . $order->get_id() . '-' . time());
}

// Username: use custom or billing name
$custom_username = $item->get_meta('_iwp_admin_username');
// Falls back to: sanitize_user($first_name . $last_name)
```

### API Parameter Names

The site creation API (`POST /sites/template`) expects these parameter names:

| Parameter | Description | Source |
|-----------|-------------|--------|
| `site_name` | Site subdomain | Custom field or auto-generated |
| `user_name` | WP admin username | Custom field or billing name |
| `email` | Admin email | Billing email |
| `template_slug` | Snapshot identifier | Product meta |

**Note**: These match the shortcode's working implementation. Previous versions incorrectly sent `name`, `admin_username`, `admin_email`.

## Release Workflow

The plugin uses GitHub Actions for automated releases. The workflow is triggered by pushing version tags.

### Release Process

**1. Update Version Number**

Update version in two locations in `iwp-wp-integration.php`:

```php
// Line 12: Plugin header
* Version:          0.0.4

// Line 51: Version constant
define('IWP_VERSION', '0.0.4');
```

**2. Update Changelogs**

Add release notes to both files:

`CHANGELOG.md`:
```markdown
## [0.0.4] - 2025-02-10

### Added
- Feature descriptions

### Fixed
- Bug fix descriptions
```

`README.md`:
```markdown
### Version 0.0.4
- Brief feature summary
```

**3. Commit and Tag**

```bash
git add -A
git commit -m "Bump version to 0.0.4"
git push origin main

git tag v0.0.4
git push origin v0.0.4
```

**4. Automated Build**

The GitHub Action (`.github/workflows/release.yml`) automatically:
- ✅ Verifies version matches tag
- ✅ Creates clean plugin directory
- ✅ Excludes development files
- ✅ Generates plugin zip file
- ✅ Creates GitHub release
- ✅ Uploads zip as release asset
- ✅ Extracts changelog from CHANGELOG.md

### Files Excluded from Release

The workflow automatically excludes:
- `CLAUDE.md`, `TESTING-PLAN.md`, `MIGRATION-GUIDE.md`
- `admin-migrate.php`, `migrate-db.php`
- `debug-*.php`, `test.php`
- `.git/`, `.github/`, `tests/`
- `node_modules/`, `vendor/`
- `composer.json`, `package.json`

### Files Included in Release

✅ User-facing documentation:
- `README.md` - User documentation
- `CHANGELOG.md` - Version history

✅ All production plugin files (PHP, CSS, JS)

### Release Download

The plugin zip will be available at:
```
https://github.com/InstaWP/iwp-wp-integration/releases/download/v0.0.4/iwp-wp-integration-0.0.4.zip
```

### Versioning

This project follows [Semantic Versioning](https://semver.org/):
- **MAJOR** version (x.0.0): Incompatible API changes
- **MINOR** version (0.x.0): New functionality (backward compatible)
- **PATCH** version (0.0.x): Bug fixes (backward compatible)

### Release Checklist

- [ ] Update version in `iwp-wp-integration.php` (header and constant)
- [ ] Update `CHANGELOG.md` with release notes
- [ ] Update `README.md` changelog section
- [ ] Test plugin thoroughly
- [ ] Commit all changes
- [ ] Create and push version tag
- [ ] Verify GitHub Action completed successfully
- [ ] Download and test release zip

For detailed release documentation, see `.github/RELEASE.md`.

---

*This documentation reflects the current state of the InstaWP Integration plugin as of February 2025. For the most up-to-date information, refer to the plugin's admin interface and settings pages.*