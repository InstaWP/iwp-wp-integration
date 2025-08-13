# InstaWP Integration Plugin - Technical Documentation

## Overview

The InstaWP Integration plugin is a comprehensive WordPress plugin that provides seamless integration with InstaWP's site creation and management platform. Originally designed as "IWP WooCommerce Integration v2", it has evolved into a full-featured WordPress plugin supporting multiple integration points and standalone functionality.

**Current Version**: 0.0.1  
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
│   │   └── class-iwp-form-helper.php # Form generation
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

---

*This documentation reflects the current state of the InstaWP Integration plugin as of January 2025. For the most up-to-date information, refer to the plugin's admin interface and settings pages.*