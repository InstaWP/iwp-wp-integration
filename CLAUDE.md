# IWP WP Integration - Claude Documentation

## Overview

This plugin is a comprehensive WordPress integration for InstaWP that provides enhanced functionality, seamless integration, WooCommerce support, standalone site creation tools, and high-performance order management with HPOS (High Performance Order Storage) compatibility. The plugin has been fully refactored from WooCommerce-specific to a generic InstaWP Integration plugin that supports multiple e-commerce platforms, uses "snapshots" terminology instead of "templates", and utilizes snapshot slugs for all API interactions.

**Key Features:**
- ✅ Complete customer-facing site access and management
- ✅ Advanced test order creation with user authentication
- ✅ Real-time site creation status tracking with numeric status mapping and is_pool optimization
- ✅ Email integration with both HTML and plain text support
- ✅ Comprehensive My Account integration
- ✅ Interactive frontend with copy-to-clipboard and password toggle features
- ✅ Mobile-responsive design with comprehensive CSS styling
- ✅ Site upgrade functionality via site_id parameter
- ✅ Magic login integration with s_hash authentication
- ✅ WordPress-style row actions in Sites management with Magic Login support
- ✅ Custom domain mapping with DNS guidance and CNAME setup instructions
- ✅ Shortcode functionality for standalone site creation forms
- ✅ Admin settings moved to Tools menu for better accessibility
- ✅ **NEW**: Fixed task status polling for non-pool site creation
- ✅ **NEW**: Synchronized frontend and admin status displays
- ✅ **NEW**: Manual "Refresh Site Status" functionality
- ✅ **NEW**: Eliminated duplicate site boxes on order pages
- ✅ **NEW**: Simplified admin interface (removed API toggle)
- ✅ **NEW**: Enhanced API key field with direct helper links
- ✅ **NEW**: Automatic demo helper plugin disable after plan changes

## Project Structure

### Core Files
- `iwp-wp-integration.php` - Main plugin file with WordPress headers and initialization
- `includes/class-iwp-woo-v2-main.php` - Primary plugin class managing all components
- `includes/class-iwp-woo-v2-autoloader.php` - PSR-4 compatible class autoloader
- `includes/class-iwp-woo-v2-installer.php` - Plugin installation, activation, and cleanup
- `includes/class-iwp-woo-v2-security.php` - Security measures and input sanitization
- `includes/class-iwp-woo-v2-utilities.php` - Utility functions and helpers
- `includes/class-iwp-woo-v2-hpos.php` - HPOS compatibility layer
- `includes/class-iwp-woo-v2-api-client.php` - InstaWP API client for snapshots management with task status checking
- `includes/class-iwp-woo-v2-product-integration.php` - WooCommerce product integration for snapshot and plan selection
- `includes/class-iwp-woo-v2-order-processor.php` - Order processing and automatic site creation
- `includes/class-iwp-woo-v2-site-manager.php` - Site creation tracking and progress monitoring with status mapping and is_pool optimization
- `includes/class-iwp-woo-v2-shortcode.php` - Shortcode functionality for standalone site creation forms

### Admin Components
- `includes/admin/class-iwp-woo-v2-admin.php` - Backend administration interface with enhanced test order functionality (located under Tools menu)

### Frontend Components
- `includes/frontend/class-iwp-woo-v2-frontend.php` - Comprehensive customer-facing features including:
  - Order details integration
  - Thank you page display
  - My Account dashboard
  - Email integration
  - Interactive site management
  - Site ID parameter handling for upgrades
  - Session/cookie management for site upgrade mode
  - Magic login with s_hash authentication
  - Custom domain mapping interface with DNS guidance

### Assets
- `assets/css/admin-product.css` - Product admin panel styles for snapshot integration
- `assets/js/admin-product.js` - Product admin JavaScript for snapshot selection and preview
- `assets/css/frontend.css` - Customer-facing styles with responsive design, interactive elements, and domain mapping modal
- `assets/js/frontend.js` - Frontend JavaScript with copy-to-clipboard, password toggle, magic login, and domain mapping functionality
- `assets/css/admin.css` - Admin panel styles
- `assets/js/admin.js` - Admin JavaScript with enhanced test order functionality
- `assets/js/shortcode.js` - Shortcode JavaScript for form submission, status tracking, and interactive features

## Architecture

### Object-Oriented Design
- **Singleton Pattern**: Main plugin class uses singleton pattern for global access
- **Autoloader**: PSR-4 compatible autoloader for efficient class loading
- **Modular Structure**: Separate classes for different concerns (admin, frontend, security, etc.)
- **Hook System**: WordPress hooks integration for extensibility

### Security Implementation
- **Input Sanitization**: All user inputs are sanitized using WordPress functions
- **Nonce Verification**: CSRF protection on all forms and AJAX requests
- **Capability Checks**: Proper user permission verification
- **Rate Limiting**: Built-in rate limiting for API endpoints
- **SQL Injection Prevention**: Prepared statements and parameterized queries
- **Password Security**: Hidden passwords with toggle functionality, secure copy operations

### Performance Optimizations
- **Conditional Loading**: Admin/frontend classes loaded only when needed
- **Efficient Queries**: Optimized database queries with proper indexing
- **Caching Strategy**: Transient caching for expensive operations
- **HPOS Compatibility**: Leverages WooCommerce's high-performance order storage

## Customer-Facing Implementation

### Complete Frontend Integration
The plugin now provides comprehensive customer access to InstaWP sites through multiple touchpoints:

#### 1. Order Details Integration
- **My Account Orders**: Sites display in individual order view pages
- **Order History**: Direct access from order listings
- **Real-time Status**: Live status updates with visual indicators

#### 2. Thank You Page Integration
- **Immediate Access**: Site details displayed after successful checkout
- **Visual Design**: Special success styling with green theme
- **Action Buttons**: Direct links to visit site and admin login

#### 3. Email Integration
- **HTML Emails**: Beautifully formatted with inline CSS styling
- **Plain Text**: Clean text format for compatibility
- **Multiple Types**: Works with completed, processing, and invoice emails
- **Customer Only**: Smart filtering to exclude admin emails

#### 4. My Account Dashboard
- **Overview Section**: Grid display of all customer's InstaWP sites
- **Quick Actions**: Visit site and view details buttons
- **Order References**: Links back to original orders

#### 5. Interactive Features
- **Copy to Clipboard**: One-click copying of credentials with modern Clipboard API
- **Password Toggle**: Show/hide passwords with eye icon (defaults to hidden)
- **Visual Feedback**: Success/error states for copy operations
- **Responsive Design**: Mobile-optimized layouts

### Site Information Display
Customers can access:
- ✅ **Site URL** with direct clickable link
- ✅ **Admin Credentials** (username/password with copy buttons)
- ✅ **Direct Admin Login** (site.com/wp-admin link)
- ✅ **Creation Status** with real-time updates and visual icons
- ✅ **Snapshot Information** showing which template was used
- ✅ **Creation Timestamp** with proper date formatting
- ✅ **Order References** linking back to order details

### Status Tracking & API Integration
- **Numeric Status Mapping**: Correctly maps API status codes (0=completed, 1=progress, 2=failed)
- **Real-time Updates**: Scheduled checks every minute via WordPress cron
- **Customer Notifications**: Automatic order notes visible to customers
- **Progress Indicators**: Visual status with emoji icons and color coding

## Enhanced Test Order Functionality

### Advanced Test Order Creation
The admin panel now includes comprehensive test order functionality with three customer options:

#### 1. Use Existing User (Default)
- **User Dropdown**: Populated with all site users (limited to 50 for performance)
- **Display Format**: `Display Name (username) - email@example.com`
- **Authenticated Orders**: Creates proper user-linked orders
- **Customer Access**: Users can see orders in My Account

#### 2. Guest Checkout
- **Manual Entry**: Traditional guest details input
- **No Account Required**: Creates guest orders
- **Backward Compatibility**: Maintains existing functionality

#### 3. Create New User
- **User Creation**: Automatically creates WordPress user accounts
- **Required Fields**: Username, Email, First Name, Last Name
- **Validation**: Checks for unique username and email
- **Welcome Email**: Sends login credentials automatically
- **Immediate Access**: New users can access their orders immediately

### Test Order Features
- **Dynamic Form**: Radio buttons toggle between customer input sections
- **Real-time Validation**: Smart form validation with button state management
- **Comprehensive Feedback**: Detailed success messages with customer information
- **Direct Links**: Quick access to order admin, customer My Account, and orders list
- **Testing Guidance**: Instructions on how to test customer experience

### Testing Workflows

#### Testing Existing Customer Experience
1. Select "Use Existing User"
2. Choose user from dropdown
3. Create test order
4. Click "Customer My Account" to test customer view
5. Login as user to verify InstaWP sites display

#### Testing New Customer Onboarding
1. Select "Create New User"
2. Fill in user details
3. Create test order
4. User receives welcome email
5. Customer can login and access sites immediately

#### Quick Guest Testing
1. Select "Guest Checkout"
2. Fill in guest details
3. Create test order
4. Test guest order flow (no account access)

## Snapshots Integration & Refactoring

### Overview
The plugin has been comprehensively refactored from "templates" to "snapshots" terminology to align with InstaWP's updated API. All functionality now uses snapshot slugs instead of IDs for consistency and API compatibility.

### Key Components

#### API Client (`class-iwp-woo-v2-api-client.php`)
- **Endpoints**: Uses `/snapshots` API endpoints
- **Methods**: `get_snapshots()`, `get_snapshot($slug)`, `create_site_from_snapshot($slug)`
- **Task Status**: `get_task_status($task_id)` for monitoring site creation progress
- **Parameters**: All methods use snapshot slugs instead of IDs
- **Site Creation**: Uses `template_slug` parameter for InstaWP API compatibility
- **Caching**: Transient caching with `iwp_woo_v2_snapshots` key
- **Backward Compatibility**: Deprecated template methods redirect to snapshot methods

#### Product Integration (`class-iwp-woo-v2-product-integration.php`)
- **Meta Keys**: 
  - `_iwp_selected_snapshot` - Stores selected snapshot slug
  - `_iwp_selected_plan` - Stores selected plan ID
- **Dropdown**: Populates from API using snapshot slugs as values
- **Plan Integration**: Plan selection dropdown with API integration
- **Preview**: Dynamic snapshot preview with image and description
- **Auto-Create**: Option to automatically create sites on order completion
- **UI**: "InstaWP" tab in WooCommerce product data section

#### Site Manager (`class-iwp-woo-v2-site-manager.php`)
- **Progress Tracking**: Comprehensive site creation monitoring
- **Status Mapping**: Converts numeric API status codes to strings:
  - `0` → `'completed'` (Site ready)
  - `1` → `'progress'` (Creating)
  - `2` → `'failed'` (Error occurred)
- **Scheduled Checks**: WordPress cron job runs every minute
- **Storage**: Sites stored in `_iwp_created_sites` order meta
- **Customer Notes**: All order notes marked as customer-visible

#### Order Processing (`class-iwp-woo-v2-order-processor.php`)
- **Triggers**: Processes orders on `woocommerce_order_status_completed` and `woocommerce_order_status_processing`
- **Site Creation**: Automatically creates InstaWP sites from selected snapshots
- **Plan Support**: Includes plan selection in site creation
- **Credentials**: Generates unique admin credentials for each site
- **Order Notes**: Adds detailed customer-visible notes about site creation results
- **Error Handling**: Comprehensive error logging and user feedback

#### Admin Interface (`class-iwp-woo-v2-admin.php`)
- **Settings**: Snapshots and plans sections in plugin settings
- **AJAX Handlers**: Enhanced handlers for snapshots, plans, and test orders
- **Test Orders**: Comprehensive test order creation with user management
- **Display**: Grid layout showing snapshot cards with images and details
- **Refresh**: Manual snapshot and plan cache refresh functionality

### Backward Compatibility
- **Deprecated Methods**: All template-named methods marked with `@deprecated`
- **Method Forwarding**: Old methods redirect to new snapshot methods
- **Parameter Compatibility**: Maintains function signatures where possible
- **Database**: Existing data continues to work with new slug-based system

### API Integration Details
- **Authentication**: Bearer token authentication with API key
- **Rate Limiting**: Built-in request throttling
- **Error Handling**: Comprehensive WP_Error integration
- **Logging**: Detailed debug logging with sanitized API responses
- **Caching**: Transient caching to reduce API calls
- **Status Checking**: Real-time task status monitoring

### Enhanced API Parameters Support
- **Expiry Hours**: `expiry_hours` parameter for temporary site creation
- **Reservation Logic**: Automatic `is_reserved` parameter management:
  - If `expiry_hours` is set → `is_reserved = false` (temporary site)
  - If no `expiry_hours` → `is_reserved = true` (permanent site)
- **Shortcode Integration**: Full support for expiry parameters in shortcode forms
- **Backward Compatibility**: Existing functionality unchanged

### Customer Workflow

#### Standard Site Creation Flow
1. **Product Setup**: Admin selects snapshot and plan from dropdowns in product edit screen
2. **Order Processing**: Customer purchases product with selected snapshot
3. **Site Creation**: Plugin automatically creates InstaWP site using snapshot slug and plan
4. **Status Tracking**: Real-time monitoring with customer-visible progress updates
5. **Customer Access**: Site credentials and links available in My Account, emails, and order details
6. **Admin Tracking**: Order status and site creation results visible in admin with detailed meta boxes

#### Site Upgrade Flow (New Feature)
1. **URL Parameter**: Customer visits shop with `?site_id=123` parameter
2. **Upgrade Mode**: System detects site_id and enters upgrade mode with persistent notice
3. **Plan Purchase**: Customer purchases plan-enabled product
4. **Site Upgrade**: Instead of creating new site, upgrades existing site to new plan
5. **Completion**: Site plan upgraded, customer notified, upgrade mode cleared

## Magic Login Integration

### Overview
The plugin now includes magic login functionality that allows customers to access their WordPress admin panel without entering credentials manually. This uses InstaWP's auto-login system with the site's unique s_hash parameter.

### Key Components

#### S_Hash Storage and Management
- **Site Creation**: s_hash parameter is automatically captured and stored during site creation
- **Database Storage**: s_hash is saved in site information arrays for all contexts (created/upgraded sites)
- **Frontend Access**: s_hash is passed through to customer-facing displays

#### Magic Login Implementation (`class-iwp-woo-v2-frontend.php`)
- **Button Replacement**: "Admin Login" becomes "Magic Login" when s_hash is available
- **URL Construction**: `https://app.instawp.io/wordpress-auto-login?site=<s_hash>`
- **Graceful Fallback**: Uses regular wp-admin URL if s_hash is missing
- **Security**: Proper URL encoding of s_hash parameter

#### Email Integration
- **Plain Text Emails**: Shows "Magic Login: [URL]" instead of "Admin URL"
- **HTML Emails**: Button text changes from "Login to Admin" to "Magic Login"
- **Universal Coverage**: Works across all email contexts (completed, processing, invoice)

### Technical Implementation
```php
// Magic login URL construction
if (!empty($s_hash)) {
    $magic_login_url = 'https://app.instawp.io/wordpress-auto-login?site=' . urlencode($s_hash);
    echo '<a href="' . esc_url($magic_login_url) . '" target="_blank">' . __('Magic Login', 'iwp-woo-v2') . '</a>';
}
```

### User Experience
- **Seamless Access**: One-click WordPress admin login without credential entry
- **Universal Availability**: Works across order details, thank you pages, My Account, and emails
- **Consistent Experience**: Same magic login experience across all touchpoints

### Sites Management Row Actions

#### Overview
The InstaWP Sites management interface now includes WordPress-style row actions beneath each site URL, providing quick access to common site operations including Magic Login functionality.

#### Key Components

**Row Actions Implementation (`class-iwp-sites-list-table.php`)**
- **WordPress Core Style**: Uses native `$this->row_actions()` method for consistent WordPress admin experience
- **Magic Login Priority**: Shows "Magic Login" when s_hash is available, falls back to "Admin Login" for regular wp-admin access
- **Multiple Actions**: Visit Site, Magic Login/Admin Login, and Delete actions
- **Smart Detection**: Automatically detects available s_hash parameter for seamless magic login integration

**Action Handling (`class-iwp-woo-v2-admin.php`)**
- **URL-Based Actions**: Handles GET-based row action requests with proper nonce verification
- **Security**: Permission checks and CSRF protection via WordPress nonces
- **API Integration**: Delete actions call InstaWP API and update local database
- **User Feedback**: Success/error messages with WordPress admin notices

#### Available Row Actions

1. **Visit Site**: Direct link to the live WordPress site
2. **Magic Login**: One-click admin access via InstaWP auto-login (when s_hash available)
3. **Admin Login**: Fallback to regular wp-admin URL (when s_hash unavailable)  
4. **Delete**: Remove site from InstaWP and local database (with confirmation)

#### Technical Implementation

**Row Actions Display:**
```php
public function column_site_url($item) {
    $url = esc_url($item['site_url']);
    $site_link = sprintf('<a href="%s" target="_blank"><strong>%s</strong></a>', $url, esc_html($url));
    
    $actions = array();
    $actions['visit'] = sprintf('<a href="%s" target="_blank">%s</a>', $url, __('Visit Site', 'iwp-wp-integration'));
    
    // Magic Login with s_hash detection
    if (!empty($item['s_hash'])) {
        $magic_login_url = 'https://app.instawp.io/wordpress-auto-login?site=' . urlencode($item['s_hash']);
        $actions['magic_login'] = sprintf('<a href="%s" target="_blank">%s</a>', esc_url($magic_login_url), __('Magic Login', 'iwp-wp-integration'));
    } else {
        $admin_url = trailingslashit($url) . 'wp-admin';
        $actions['admin_login'] = sprintf('<a href="%s" target="_blank">%s</a>', esc_url($admin_url), __('Admin Login', 'iwp-wp-integration'));
    }
    
    return $site_link . $this->row_actions($actions);
}
```

**Security & Error Handling:**
```php
private function handle_sites_page_actions() {
    if ($action === 'delete') {
        // Nonce verification
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_site_' . $site_id)) {
            wp_die(__('Security check failed', 'iwp-wp-integration'));
        }
        
        // Permission check
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'iwp-wp-integration'));
        }
        
        // API deletion with error handling
        $api_client->delete_site($site_id);
        IWP_Woo_V2_Sites_Model::delete($site_id);
    }
}
```

#### Styling & User Experience
- **WordPress Native**: Uses core WordPress row actions CSS classes and styling
- **Hover Effects**: Standard WordPress hover states and visual feedback
- **Color Coding**: Delete actions use red color scheme for clear visual distinction
- **Responsive**: Mobile-friendly layout that adapts to smaller screens
- **Accessibility**: Proper ARIA labels and keyboard navigation support

#### Data Structure Enhancement
**Extended Site Data:**
- Added `s_hash` field to both database and order-sourced sites
- Supports both new format (from order processor) and legacy format (from stored order meta)
- Graceful fallback when s_hash is unavailable

#### Benefits
- **Administrative Efficiency**: Quick access to common site operations without leaving the Sites page
- **Seamless Integration**: Magic Login works directly from admin interface
- **WordPress Consistency**: Familiar row actions interface for WordPress administrators
- **Security**: Proper nonce protection and permission checks for all actions
- **User Feedback**: Clear success/error messaging with WordPress admin notices

## Custom Domain Mapping

### Overview
The plugin provides a comprehensive domain mapping interface that allows customers to map custom domains to their InstaWP sites directly from the order details page. The system includes DNS guidance, CNAME setup instructions, and support for multiple domain types.

### Key Components

#### API Integration (`class-iwp-woo-v2-api-client.php`)
- **V1 API Endpoint**: Uses `https://app.instawp.io/api/v1/site/add-domain/{site_id}`
- **Domain Types**: Supports both 'primary' and 'alias' domain configurations
- **Validation**: Server-side domain format validation and sanitization
- **Error Handling**: Comprehensive API error handling and user feedback

#### Frontend Interface (`class-iwp-woo-v2-frontend.php`)
- **Map Domain Button**: Green tertiary button on completed sites with site_id
- **Professional Modal**: Feature-rich domain mapping interface
- **DNS Instructions**: Dynamic CNAME setup guidance with actual site URL
- **Domain History**: Display of previously mapped domains with type badges

#### AJAX Handler (`class-iwp-woo-v2-admin.php`)
- **Security**: Nonce verification and user permission checks
- **Storage**: Saves mapped domains to `_iwp_mapped_domains` order meta
- **Logging**: Creates customer-visible order notes for domain mappings
- **Validation**: Domain format validation and duplicate prevention

### User Interface Features

#### Domain Mapping Modal
- **Clear Instructions**: Step-by-step CNAME record setup guidance
- **Dynamic Examples**: Shows actual site URL in CNAME examples
- **Domain Input**: Validated text field (no http/https required)
- **Type Selection**: Primary vs Alias dropdown with explanations
- **Usage Examples**: Common setups like domain.com + www.domain.com

#### Existing Domains Display
- **Domain List**: Shows all previously mapped domains
- **Type Badges**: Color-coded Primary/Alias indicators
- **Timestamps**: Shows when each domain was mapped
- **Clean Layout**: Professional card-based design

### Technical Implementation

#### Domain Mapping Process
1. **Customer clicks "Map Domain"** on site card
2. **Modal displays** with CNAME instructions using their site URL
3. **Customer sets up DNS** (e.g., `domain.com` CNAME → `their-site.instawp.cc`)
4. **Customer enters domain** and selects type (Primary/Alias)
5. **System validates** and calls InstaWP v1 API
6. **Domain is mapped** and stored in order history
7. **Success feedback** with automatic page reload to show new domain

#### Database Schema
```php
// Order meta: _iwp_mapped_domains
array(
    array(
        'site_id' => 123,
        'domain_name' => 'example.com',
        'domain_type' => 'primary',
        'mapped_at' => '2025-01-28 10:30:00',
        'api_response' => array(...)
    )
)
```

### Security & Validation
- **Input Sanitization**: All domain inputs are sanitized and validated
- **Format Validation**: Both client and server-side domain format checking
- **Permission Checks**: Users can only map domains to their own orders
- **Nonce Protection**: CSRF protection on all AJAX requests
- **Error Handling**: Graceful API failure handling with user feedback

### Common Use Cases
- **Single Domain**: Add `domain.com` as Primary
- **Domain + WWW**: Add `domain.com` as Primary, then `www.domain.com` as Alias
- **Multiple Domains**: Support for unlimited domain mappings per site
- **Subdomain Support**: Works with subdomains like `blog.domain.com`

## Site Creation Optimization (is_pool Parameter)

### Overview
The plugin now includes intelligent site creation handling based on the `is_pool` parameter from the InstaWP API. This optimization prevents unnecessary task tracking for pool-based sites that are provisioned instantly.

### Key Components

#### Pool Site Detection (`class-iwp-woo-v2-site-manager.php`)
- **Parameter Check**: Detects `is_pool` boolean in API response
- **Smart Status Logic**: Pool sites marked as 'completed' immediately
- **Task Tracking Skip**: No task monitoring for pool sites
- **Performance Optimization**: Reduces API calls and cron job processing

#### Status Determination Logic
```php
private function determine_site_status($site_data_response) {
    // Pool sites are ready immediately
    $is_pool = isset($site_data_response['is_pool']) ? (bool)$site_data_response['is_pool'] : false;
    
    if ($is_pool) {
        return 'completed';
    }
    
    // Regular sites use existing logic
    if (!empty($site_data_response['wp_url']) && !empty($site_data_response['wp_username'])) {
        return 'completed';
    }
    
    // Task tracking only for non-pool sites
    if (!empty($site_data_response['task_id'])) {
        return $this->map_status_code($site_data_response['status'] ?? 1);
    }
}
```

#### Task Tracking Optimization
- **Conditional Tracking**: Only tracks tasks when `is_pool` is false and `task_id` exists
- **Resource Efficiency**: Eliminates unnecessary cron job processing
- **Accurate Status**: Prevents false "in progress" states for instant sites

### Benefits
- **Faster Customer Experience**: Pool sites show as ready immediately
- **Reduced Server Load**: Less API polling and cron job execution
- **Better Accuracy**: Eliminates confusing "site is being created" messages for instant sites
- **Resource Optimization**: More efficient use of system resources

## Shortcode Functionality (iwp_site_creator)

### Overview
The plugin provides a powerful shortcode system that allows users to create standalone InstaWP site creation forms anywhere on their WordPress site. The `iwp_site_creator` shortcode generates a complete form with real-time status tracking, interactive features, and customizable styling.

### Key Features
- **Standalone Site Creation**: Independent of WooCommerce orders
- **Real-time Status Tracking**: Live progress updates during site creation
- **Interactive UI**: Copy-to-clipboard, password toggle, form validation
- **Customizable Styling**: Semantic CSS classes for theme integration
- **Mobile Responsive**: Optimized for all device sizes
- **Pool Site Support**: Handles both instant and task-based site creation
- **Expiry Management**: Support for temporary sites with expiration

### Shortcode Usage

#### Basic Syntax
```
[iwp_site_creator snapshot_slug="your-snapshot-slug"]
```

#### Parameters
- **snapshot_slug** (Required): The slug of the InstaWP snapshot to use
- **email** (Optional): Pre-fill the email field
- **name** (Optional): Pre-fill the site name field
- **expiry_hours** (Optional): Number of hours until site expires
- **sandbox** (Optional): Set to "true" to create a sandbox/shared site

#### Advanced Examples
```
// Pre-filled form
[iwp_site_creator snapshot_slug="ecommerce-store" email="customer@example.com" name="My Store"]

// Temporary site (24 hours)
[iwp_site_creator snapshot_slug="demo-site" expiry_hours="24"]

// Demo form with pre-filled data
[iwp_site_creator snapshot_slug="wordpress-blog" email="demo@test.com" name="Demo Site" expiry_hours="48"]

// Sandbox site for shared access
[iwp_site_creator snapshot_slug="sandbox-demo" sandbox="true"]
```

### Implementation Details

#### Core Components (`class-iwp-woo-v2-shortcode.php`)
- **Shortcode Registration**: `add_shortcode('iwp_site_creator', ...)`
- **Form Rendering**: HTML form generation with proper field structure
- **AJAX Handlers**: Site creation and status checking endpoints
- **Validation**: Client and server-side form validation
- **Script Enqueuing**: Conditional JavaScript loading

#### API Integration Enhancements
- **Expiry Logic**: If `expiry_hours` is set, `is_reserved` becomes `false`
- **Reservation Logic**: If no `expiry_hours`, `is_reserved` defaults to `true`
- **Sandbox Logic**: If `sandbox="true"`, `is_shared` becomes `true`
- **Parameter Support**: Enhanced API client with expiry/reservation/sandbox parameters

#### JavaScript Features (`assets/js/shortcode.js`)
- **Form Submission**: AJAX form processing with error handling
- **Status Polling**: Automatic task status checking every 3 seconds
- **Progress Animation**: Visual progress indicators during creation
- **Copy Functionality**: Modern Clipboard API with fallback support
- **Password Toggle**: Show/hide password functionality
- **Mobile Optimization**: Touch-friendly interface elements

### CSS Classes for Styling

#### Form Structure
- `.iwp-site-creator-container` - Main form container
- `.iwp-site-creator-form` - Form element
- `.iwp-site-creator-field-group` - Individual field wrapper
- `.iwp-site-creator-label` - Field labels
- `.iwp-site-creator-input` - Input fields (text, email)
- `.iwp-site-creator-button` - Action buttons

#### Status and Results
- `.iwp-site-creator-status` - Status message container
- `.iwp-site-creator-message` - Status text display
- `.iwp-site-creator-progress` - Progress bar container
- `.iwp-site-creator-results` - Success results display
- `.iwp-site-creator-site-info` - Site information wrapper

#### Interactive Elements
- `.iwp-site-creator-copy-btn` - Copy to clipboard buttons
- `.iwp-site-creator-toggle-password` - Password visibility toggle
- `.iwp-site-creator-password` - Password text (hidden by default)
- `.iwp-site-creator-password-hidden` - Password dots display

### Site Creation Workflow

#### Standard Flow
1. **User Input**: Customer fills name and email fields
2. **Form Validation**: Client-side validation before submission
3. **AJAX Submission**: Form data sent to `iwp_create_site_shortcode` endpoint
4. **API Call**: Site creation request to InstaWP API
5. **Status Handling**: Immediate response for pool sites, polling for others
6. **Result Display**: Site credentials and access links shown

#### Pool Site Handling
- **Instant Creation**: Pool sites marked as completed immediately
- **No Polling**: Skip task status checking for performance
- **Direct Display**: Show results without delay

#### Task-Based Creation
- **Progress Tracking**: Real-time status updates via polling
- **Status Mapping**: Convert numeric API codes to user-friendly messages
- **Timeout Protection**: 5-minute maximum polling duration
- **Error Recovery**: Graceful handling of API failures

### Security Implementation
- **Nonce Verification**: CSRF protection on all AJAX requests
- **Input Sanitization**: All form data sanitized and validated
- **Rate Limiting**: Built-in protection against form abuse
- **Permission Checks**: Proper capability verification
- **SQL Injection Prevention**: Parameterized queries and prepared statements

### Admin Integration

#### Settings Documentation
- **Location**: WordPress Admin → Tools → InstaWP Integration
- **Documentation Section**: Complete shortcode reference with examples
- **Parameter Guide**: Detailed explanation of all available options
- **Styling Guide**: CSS class reference for theme developers

#### Usage Examples in Admin
```
// Basic form
[iwp_site_creator snapshot_slug="wordpress-blog"]

// Marketing landing page
[iwp_site_creator snapshot_slug="marketing-template" name="Try Our Platform"]

// Demo environment
[iwp_site_creator snapshot_slug="demo-app" expiry_hours="2"]
```

### Use Cases

#### Marketing Pages
- **Lead Generation**: Capture emails while providing instant demos
- **Product Trials**: Temporary sites for software evaluation
- **Event Demos**: Conference booth demonstrations

#### Educational Platforms
- **Student Sandboxes**: Individual learning environments
- **Course Materials**: Pre-configured learning sites
- **Assignment Submissions**: Temporary project environments

#### Agency Workflows
- **Client Previews**: Quick prototype creation
- **Proposal Demonstrations**: Live examples during pitches
- **Development Environments**: Rapid testing setups

### Performance Considerations
- **Conditional Loading**: JavaScript only loads on pages with shortcode
- **Efficient Polling**: Smart status checking with automatic cleanup
- **Cache Integration**: Leverage existing snapshot caching system
- **Mobile Optimization**: Touch-friendly interface with minimal overhead

### Error Handling
- **Form Validation**: Real-time field validation with visual feedback
- **API Failures**: Graceful degradation with user-friendly messages
- **Network Issues**: Retry logic and timeout protection
- **Browser Compatibility**: Clipboard API fallback for older browsers

## Site ID Parameter Functionality

### Overview
The plugin now supports upgrading existing InstaWP sites through URL parameters instead of always creating new sites. When customers visit the shop with `?site_id=123`, the system enters "upgrade mode" and uses plan upgrades instead of site creation.

### Key Components

#### Frontend Parameter Handling (`class-iwp-woo-v2-frontend.php`)
- **URL Detection**: Automatically detects `?site_id=123` parameter on shop pages
- **Validation**: Ensures site_id is numeric and positive
- **Clean URLs**: Redirects to clean URL after storing parameter
- **Session Storage**: Stores site_id in PHP session for persistence
- **Cookie Backup**: 24-hour cookie backup for cross-session persistence
- **Visual Notice**: Prominent upgrade mode notification on shop/product pages
- **Cancel Option**: One-click link to exit upgrade mode

#### API Client Enhancement (`class-iwp-woo-v2-api-client.php`)
- **Upgrade Method**: New `upgrade_site_plan($site_id, $plan_id)` method
- **API Endpoint**: POST to `/sites/{site_id}/upgrade-plan`
- **JSON Payload**: `{"plan_id": "plan_id_value"}`
- **Error Handling**: Comprehensive validation and error reporting
- **Logging**: Detailed debug logging for upgrade operations

#### Order Processing Integration (`class-iwp-woo-v2-order-processor.php`)
- **Upgrade Detection**: Checks for stored site_id during order processing
- **Plan Requirement**: Only processes upgrades for products with plan_id
- **API Integration**: Calls upgrade API instead of site creation
- **Cleanup**: Clears stored site_id after successful upgrade
- **Order Notes**: Enhanced notes distinguishing upgrades from creations
- **Meta Storage**: Stores upgrade details in `_iwp_site_upgrades` order meta

#### Admin Settings Integration (`class-iwp-woo-v2-admin.php`)
- **Global Setting**: "Use site_id when provided to change plan instead of creating a new site"
- **Feature Toggle**: Admins can enable/disable upgrade functionality
- **Settings Location**: General Settings section with descriptive help text

### Technical Implementation

#### URL Parameter Processing
```php
// Detect and store site_id parameter
public function handle_site_id_parameter() {
    if (isset($_GET['site_id']) && is_numeric($_GET['site_id'])) {
        $_SESSION['iwp_site_id_for_upgrade'] = intval($_GET['site_id']);
        setcookie('iwp_site_id_for_upgrade', intval($_GET['site_id']), time() + (24 * 60 * 60), '/');
        wp_safe_redirect(remove_query_arg('site_id'));
    }
}
```

#### Site Upgrade API Call
```php
// Upgrade existing site plan
$api_client = new IWP_Woo_V2_API_Client();
$result = $api_client->upgrade_site_plan($site_id, $plan_id);
// API calls: POST /sites/123/upgrade-plan with {"plan_id": "new-plan"}
```

#### Order Processing Logic
```php
// Check for upgrade mode during order processing
$frontend = new IWP_Woo_V2_Frontend();
$upgrade_site_id = $frontend->get_stored_site_id();

if ($upgrade_site_id && !empty($plan_id)) {
    // Upgrade existing site instead of creating new one
    $result = $this->upgrade_site_plan($order, $product, $upgrade_site_id, $plan_id, $item);
}
```

### User Experience Flow

#### Step-by-Step Usage
1. **Admin Configuration**: Enable "Use site_id parameter" setting in admin
2. **Customer Access**: Customer visits `shop.example.com/?site_id=123`
3. **Upgrade Mode Activation**: System stores site_id and shows notice
4. **Shopping Experience**: Blue notice persists: "Site Upgrade Mode Active - You are upgrading site ID: 123"
5. **Plan Selection**: Customer adds plan-enabled product to cart
6. **Checkout Process**: Normal WooCommerce checkout flow
7. **Order Completion**: System upgrades site instead of creating new one
8. **Customer Notification**: Order notes show "Site ID 123 upgraded to plan XYZ"
9. **Mode Cleanup**: Upgrade mode automatically cleared after success

#### Visual Indicators
- **Upgrade Notice**: Blue informational banner on shop pages
- **Persistent Display**: Notice shows on shop, category, and product pages
- **Cancel Option**: "Cancel upgrade mode" link to exit at any time
- **Order Confirmation**: Clear distinction between created and upgraded sites

### Security & Validation

#### Input Validation
- **Numeric Check**: site_id must be positive integer
- **Setting Check**: Feature must be enabled in admin
- **Plan Requirement**: Only processes if product has plan_id
- **API Validation**: InstaWP API validates site ownership

#### Session Management  
- **Secure Storage**: PHP sessions with cookie backup
- **Time Limits**: 24-hour cookie expiration
- **Clean URLs**: Removes parameter from URL for security
- **Automatic Cleanup**: Clears storage after successful upgrade

### Database Schema

#### New Order Meta Keys
- `_iwp_site_upgrades` - Array of site upgrade details per order
- Enhanced `_iwp_sites_created` - Now includes 'action' field ('created' or 'upgraded')

#### Session/Cookie Storage
- `iwp_site_id_for_upgrade` - Stored site_id for upgrade operations
- Temporary storage, cleared after use

### Error Handling

#### Common Scenarios
- **Invalid site_id**: Non-numeric or missing values ignored
- **Feature Disabled**: Silently bypassed when setting disabled  
- **API Failures**: Comprehensive error logging and customer notification
- **Missing Plan**: Falls back to standard site creation flow
- **Timeout Issues**: Cookie backup ensures persistence across sessions

#### Debug Support
- **Detailed Logging**: All upgrade operations logged with debug info
- **Error Messages**: Clear customer-facing error descriptions
- **Admin Visibility**: Upgrade status visible in order admin interface

### API Integration Details

#### Upgrade Endpoint
- **Method**: POST
- **URL**: `{api_url}/sites/{site_id}/upgrade-plan`
- **Headers**: `Authorization: Bearer {api_key}`, `Content-Type: application/json`
- **Payload**: `{"plan_id": "target_plan_id"}`
- **Response**: Site upgrade confirmation with updated plan details

#### Error Responses
- **401**: Invalid API key
- **404**: Site not found or no access
- **400**: Invalid plan_id or upgrade not possible
- **500**: Server error during upgrade process

## HPOS (High Performance Order Storage) Compatibility

### Features
- **Automatic Detection**: Plugin automatically detects and adapts to HPOS
- **Dual Compatibility**: Works with both HPOS and legacy post-based storage
- **Performance Benefits**: Optimized for large order volumes
- **Future-Proof**: Ready for WooCommerce's direction toward HPOS

### Implementation Details
- **Order Management**: HPOS-compatible order CRUD operations
- **Meta Data**: Proper order meta handling for both storage types
- **Event Hooks**: Comprehensive order lifecycle event handling
- **Query Methods**: Advanced order querying capabilities

## Development Guidelines

### Code Standards
- Follow WordPress Coding Standards
- Use proper escaping for all output (`esc_html`, `esc_attr`, `esc_url`)
- Sanitize all input data
- Use WordPress hooks and filters appropriately
- Maintain backward compatibility

### Testing
- Test with both HPOS enabled and disabled
- Verify compatibility with various WooCommerce versions
- Test all admin and frontend functionality
- Validate security measures
- Test customer-facing features across different themes
- Verify email integration with various email clients

### Common Tasks

#### Adding New Features
1. Create new class file in appropriate directory
2. Update autoloader paths if needed
3. Initialize class in main plugin file
4. Add necessary hooks and filters
5. Test thoroughly on both admin and frontend

#### Database Operations
```php
// Get orders (HPOS compatible)
$orders = IWP_Woo_V2_HPOS::get_orders(array(
    'status' => 'completed',
    'limit' => 10
));

// Update order meta (HPOS compatible)
IWP_Woo_V2_HPOS::update_order_meta($order_id, 'custom_key', 'value');
```

#### Security Best Practices
```php
// Sanitize input
$clean_data = IWP_Woo_V2_Security::sanitize_input($_POST['data'], 'text');

// Verify nonce
if (!wp_verify_nonce($_POST['nonce'], 'iwp_woo_v2_action')) {
    wp_die('Security check failed');
}

// Check capabilities
if (!current_user_can('manage_woocommerce')) {
    wp_die('Insufficient permissions');
}
```

#### Snapshot API Usage
```php
// Initialize API client
$api_client = new IWP_Woo_V2_API_Client();
$api_client->set_api_key('your-api-key');

// Get all snapshots
$snapshots = $api_client->get_snapshots();
if (!is_wp_error($snapshots)) {
    foreach ($snapshots['data'] as $snapshot) {
        echo $snapshot['name'] . ' (' . $snapshot['slug'] . ')';
    }
}

// Get specific snapshot by slug
$snapshot = $api_client->get_snapshot('my-snapshot-slug');
if (!is_wp_error($snapshot)) {
    echo 'Snapshot: ' . $snapshot['data']['name'];
}

// Create site from snapshot with plan
$site_data = array(
    'name' => 'my-new-site',
    'title' => 'My New Site',
    'admin_email' => 'admin@example.com',
    'admin_username' => 'admin',
    'admin_password' => wp_generate_password(12)
);

$result = $api_client->create_site_from_snapshot('my-snapshot-slug', $site_data, 'plan-id');
if (!is_wp_error($result)) {
    echo 'Site created: ' . $result['data']['wp_url'];
    
    // Check task status if applicable
    if (!empty($result['data']['task_id'])) {
        $status = $api_client->get_task_status($result['data']['task_id']);
        echo 'Status: ' . $status['data']['status'];
    }
}

// Upgrade existing site plan
$upgrade_result = $api_client->upgrade_site_plan(123, 'new-plan-id');
if (!is_wp_error($upgrade_result)) {
    echo 'Site upgraded successfully';
} else {
    echo 'Upgrade failed: ' . $upgrade_result->get_error_message();
}

// Map custom domain to site
$domain_result = $api_client->add_domain_to_site(123, 'example.com', 'primary');
if (!is_wp_error($domain_result)) {
    echo 'Domain mapped successfully';
} else {
    echo 'Domain mapping failed: ' . $domain_result->get_error_message();
}
```

#### Site Manager Usage
```php
// Create site with tracking
$site_manager = new IWP_Woo_V2_Site_Manager();
$result = $site_manager->create_site_with_tracking(
    'snapshot-slug', 
    $site_data, 
    $order_id, 
    $product_id,
    'plan-id'
);

// Get sites for an order
$sites = $site_manager->get_order_sites($order_id);
foreach ($sites as $site) {
    echo 'Site: ' . $site['wp_url'] . ' - Status: ' . $site['status'];
}
```

#### Frontend Integration
```php
// Display customer sites (automatically integrated)
// Sites appear in:
// - My Account → Orders → View Order
// - My Account Dashboard
// - Thank You Pages
// - Email Notifications

// Get customer's sites
$frontend = new IWP_Woo_V2_Frontend();
// Sites are automatically displayed via hooks
```

#### Product Integration Usage
```php
// Get selected snapshot and plan for a product
$product_integration = new IWP_Woo_V2_Product_Integration();
$snapshot_slug = $product_integration->get_product_snapshot($product_id);
$plan_id = $product_integration->get_product_plan($product_id);

// Check if auto-create is enabled
$auto_create = get_post_meta($product_id, '_iwp_auto_create_site', true);
if ($auto_create === 'yes' && !empty($snapshot_slug)) {
    // Process order for site creation
}
```

## Database Schema

### Custom Tables
- `wp_iwp_woo_v2_logs` - Plugin activity logs
- `wp_iwp_woo_v2_settings` - Plugin settings storage
- `wp_iwp_woo_v2_order_data` - HPOS-compatible order data

### Order Meta Keys
- `_iwp_selected_snapshot` - Snapshot slug for product
- `_iwp_selected_plan` - Plan ID for product
- `_iwp_auto_create_site` - Auto-create setting (yes/no)
- `_iwp_created_sites` - Array of created sites with full details
- `_iwp_sites_created` - Enhanced array with created/upgraded sites and action types
- `_iwp_mapped_domains` - Array of custom domains mapped to sites
- `_iwp_processed` - Order processing flag
- `_iwp_processed_date` - Processing timestamp

### Options
- `iwp_woo_v2_pending_sites` - Sites awaiting creation completion
- `iwp_woo_v2_snapshots` - Cached snapshots (5 minute TTL)
- `iwp_woo_v2_plans` - Cached plans (1 hour TTL)

### Indexes
- Proper indexing on frequently queried columns
- Composite indexes for complex queries
- Foreign key relationships where appropriate

## Configuration

### Plugin Settings
**Location**: WordPress Admin → Tools → InstaWP Integration

- **General Settings**: Basic plugin configuration including site_id parameter functionality
- **API Settings**: InstaWP API key and endpoint configuration
- **Debug Mode**: Development and troubleshooting options
- **HPOS Status**: Display current HPOS status
- **Site Upgrade Settings**: Enable/disable site_id parameter upgrades
- **Shortcode Documentation**: Complete shortcode reference with examples and styling guide

### WordPress Hooks
- `iwp_woo_v2_init` - Plugin initialization
- `iwp_woo_v2_new_order` - New order created
- `iwp_woo_v2_order_updated` - Order updated
- `iwp_woo_v2_order_status_changed` - Order status changed
- `iwp_woo_v2_snapshot_selected` - Snapshot selected for product
- `iwp_woo_v2_site_created` - Site created from snapshot
- `iwp_woo_v2_site_creation_failed` - Site creation failed
- `iwp_woo_v2_snapshots_refreshed` - Snapshots cache refreshed

### Frontend Hooks
- `woocommerce_view_order` - Order details page integration
- `woocommerce_order_details_after_order_table` - Order table integration
- `woocommerce_thankyou` - Thank you page integration
- `woocommerce_email_order_details` - Email integration
- `woocommerce_account_dashboard` - My Account dashboard integration

## Dependencies

### Required
- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+

### Optional
- HPOS feature in WooCommerce 7.1+
- REST API support
- Modern browser for enhanced frontend features (Clipboard API)

## Troubleshooting

### Common Issues
1. **HPOS Compatibility**: Check WooCommerce version and HPOS status
2. **Permission Errors**: Verify user capabilities
3. **Database Issues**: Check table creation and indexes
4. **JavaScript Errors**: Verify script loading and dependencies
5. **API Connection**: Verify InstaWP API key and connectivity
6. **Snapshot Loading**: Check if snapshots are loading in product dropdown
7. **Site Creation**: Verify snapshot slug format and API response
8. **Cache Issues**: Clear snapshot cache if data appears stale
9. **Customer Access**: Ensure order notes are customer-visible
10. **Status Mapping**: Verify numeric status codes are properly converted
11. **Email Integration**: Check email template compatibility
12. **Frontend Display**: Verify theme compatibility and CSS loading
13. **Site Upgrades**: Verify site_id parameter setting enabled and valid site_id format
14. **Upgrade API**: Check InstaWP API upgrade endpoint connectivity
15. **Session Storage**: Verify PHP sessions working and cookie support
16. **Magic Login**: Verify s_hash parameter is being captured and stored correctly
17. **Domain Mapping**: Check v1 API connectivity and domain validation logic
18. **Pool Site Status**: Verify is_pool parameter handling for instant sites
19. **Task Tracking**: Ensure pool sites skip unnecessary task monitoring
20. **Shortcode Display**: Verify shortcode renders properly and scripts load
21. **Shortcode AJAX**: Check AJAX endpoints and nonce verification
22. **Expiry Parameters**: Verify expiry_hours logic and is_reserved behavior

### Debug Mode
Enable debug mode in plugin settings (Tools → InstaWP Integration) to:
- View detailed error logs
- Monitor database queries
- Track plugin performance
- Analyze HPOS operations
- Monitor API calls and responses
- Track customer access patterns

### Log Files
- WordPress debug log: `wp-content/debug.log`
- WooCommerce logs: WooCommerce > Status > Logs
- Plugin-specific logs in WooCommerce log system
- Frontend JavaScript console for interactive features
- Email delivery logs for notification issues

### Testing Customer Features
1. **Test Order Creation**: Use admin test order functionality
2. **Customer Login**: Login as test customer to verify My Account access
3. **Email Testing**: Check HTML and plain text email formats
4. **Mobile Testing**: Verify responsive design on various devices
5. **Browser Compatibility**: Test interactive features across browsers
6. **Theme Compatibility**: Verify frontend display with different themes
7. **Shortcode Testing**: Test iwp_site_creator shortcode with various parameters
8. **Form Validation**: Verify client and server-side form validation
9. **AJAX Functionality**: Test real-time status updates and copy features

## Performance Monitoring

### Key Metrics
- Order query performance
- Database query count
- Memory usage
- Page load times
- HPOS vs legacy performance comparison
- Frontend rendering performance
- Email generation time
- API response times

### Optimization Tips
- Use HPOS when available for better performance
- Implement proper caching strategies
- Optimize database queries
- Monitor plugin impact on site performance
- Optimize frontend CSS and JavaScript
- Use efficient email templates
- Monitor API usage and caching effectiveness

## Deployment

### Production Checklist
- [ ] Disable debug mode
- [ ] Verify HPOS compatibility
- [ ] Test all functionality (admin and customer-facing)
- [ ] Check security measures
- [ ] Validate performance impact
- [ ] Backup database before deployment
- [ ] Test email delivery
- [ ] Verify frontend responsive design
- [ ] Test customer workflows
- [ ] Verify API connectivity and rate limits

### Version Control
- Follow semantic versioning
- Document all changes
- Test thoroughly before release
- Maintain backward compatibility
- Include frontend and customer feature testing

## Support and Documentation

### Resources
- Plugin documentation: Internal documentation
- WooCommerce documentation: https://woocommerce.com/documentation/
- WordPress Codex: https://codex.wordpress.org/
- HPOS documentation: WooCommerce developer resources
- InstaWP API documentation: Contact InstaWP support

### Getting Help
- Check plugin logs for errors
- Review WooCommerce system status
- Verify plugin compatibility
- Test customer-facing features
- Check email delivery and formatting
- Verify theme compatibility
- Contact support with detailed information including:
  - Customer workflow details
  - Frontend display issues
  - Email notification problems
  - API connectivity issues

## Contributing

### Development Setup
1. Install WordPress and WooCommerce
2. Clone plugin to `wp-content/plugins/iwp-wp-integration`
3. Enable debug mode
4. Create test users for customer testing
5. Configure test email delivery
6. Follow coding standards
7. Test with both HPOS enabled and disabled
8. Test customer-facing features thoroughly

### Code Review
- All code must pass security review
- Performance impact must be assessed
- Compatibility testing required (admin and frontend)
- Customer experience validation required
- Email template testing required
- Mobile responsiveness verification required
- Documentation must be updated

### Testing Requirements
- Admin functionality testing
- Customer workflow testing
- Email integration testing
- Mobile device testing
- Cross-browser compatibility testing
- Theme compatibility testing
- Performance impact assessment

## Site Expiry Settings

### Overview
The plugin now includes product-level site expiry settings that allow administrators to configure whether sites created from specific products are permanent or temporary. This feature provides fine-grained control over site lifecycle management.

### Key Components

#### Product Integration (`class-iwp-woo-v2-product-integration.php`)
- **Meta Keys**:
  - `_iwp_site_expiry_type` - Stores 'permanent' or 'temporary'
  - `_iwp_site_expiry_hours` - Stores expiry hours for temporary sites (1-8760)
- **Default Values**: 
  - Type defaults to 'permanent' if not set
  - Hours defaults to 28 for temporary sites
- **Helper Methods**:
  - `get_product_expiry_type($product_id)` - Returns 'permanent' or 'temporary'
  - `get_product_expiry_hours($product_id)` - Returns hours if temporary, null if permanent
  - `is_product_permanent($product_id)` - Boolean check for permanent sites

#### Order Processor (`class-iwp-woo-v2-order-processor.php`)
- **Site Creation Enhancement**: Automatically applies expiry settings during site creation
- **API Parameters**:
  - Permanent sites: `is_reserved: true` (no expiry_hours)
  - Temporary sites: `is_reserved: false` + `expiry_hours: [value]`
- **Backward Compatibility**: Existing products default to permanent behavior

#### Admin Interface
- **InstaWP Product Tab**: New "Site Expiry Settings" section
- **Radio Buttons**: Permanent/Temporary selection
- **Expiry Hours Field**: Number input (1-8760 hours), shown only for temporary sites
- **JavaScript**: Dynamic field visibility with smooth transitions
- **CSS Styling**: Professional layout matching WooCommerce admin standards

### Implementation Details

#### Site Creation Logic
```php
// In create_site_for_product method
if ($expiry_type === 'temporary' && !empty($expiry_hours)) {
    $site_data['expiry_hours'] = intval($expiry_hours);
    $site_data['is_reserved'] = false; // Temporary sites
} else {
    $site_data['is_reserved'] = true; // Permanent sites
}
```

#### JavaScript Enhancement (`admin-product.js`)
- Real-time toggle of expiry hours field based on selection
- State preservation on page load
- Smooth transitions for better UX

#### CSS Styling (`admin-product.css`)
- Flexbox layout for radio buttons
- Proper spacing and alignment
- Transition effects for field visibility
- Consistent with WooCommerce styling

### Use Cases

#### Permanent Sites
- **Regular Hosting Products**: Standard WordPress hosting
- **Client Sites**: Long-term production environments
- **Premium Plans**: Unlimited duration sites
- **Default Behavior**: Matches existing functionality

#### Temporary Sites
- **Demo Products**: 2-hour quick demos
- **Trial Hosting**: 7-day trial periods (168 hours)
- **Workshop Environments**: 24-hour training sites
- **Event Sites**: Conference or webinar environments
- **Testing Platforms**: Short-term development sites

### Configuration Examples

1. **Quick Demo Product**
   - Type: Temporary
   - Hours: 2
   - Use: Quick product demonstrations

2. **7-Day Trial**
   - Type: Temporary
   - Hours: 168
   - Use: Extended trial periods

3. **Workshop Environment**
   - Type: Temporary
   - Hours: 24
   - Use: Training sessions

4. **Production Hosting**
   - Type: Permanent
   - Use: Regular hosting products

### Technical Specifications

#### Validation Rules
- **Expiry Type**: Must be 'permanent' or 'temporary'
- **Expiry Hours**: 
  - Minimum: 1 hour
  - Maximum: 8760 hours (1 year)
  - Default: 28 hours
  - Only stored for temporary sites

#### Database Operations
- Efficient meta operations using centralized helpers
- Automatic cleanup when switching from temporary to permanent
- Structured logging for debugging

#### API Integration
- Seamless integration with InstaWP API
- Correct parameter mapping based on product configuration
- No changes required to existing API client

### Migration Notes
- **Existing Products**: Default to permanent (no action required)
- **New Products**: Default to permanent unless configured
- **Database**: New meta keys added, no schema changes required
- **API**: Uses existing parameters, fully backward compatible

## Code Deduplication and Helper Classes

### Overview
The plugin has been refactored to eliminate code duplication through the implementation of centralized helper classes. This refactoring improved maintainability, consistency, and reduced the codebase by ~500+ lines.

### Helper Classes Implemented

#### Security Helper (`class-iwp-woo-v2-security.php`)
- **Purpose**: Centralized security validation and sanitization
- **Key Method**: `validate_ajax_request($nonce_action, $capability, $nonce_field)`
- **Impact**: Eliminated 15+ duplicate nonce/permission check blocks
- **Usage**: Replaced repetitive security checks with single method calls

#### Database Helper (`class-iwp-woo-v2-database.php`)
- **Purpose**: Centralized database operations
- **Key Method**: `append_order_meta($order_id, $meta_key, $data)`
- **Impact**: Eliminated 20+ duplicate meta operations
- **Usage**: Simplified array append operations for order meta

#### Logger Helper (`class-iwp-woo-v2-logger.php`)
- **Purpose**: Structured logging with levels and context
- **Methods**: `debug()`, `info()`, `warning()`, `error()`
- **Impact**: Replaced 50+ error_log statements
- **Features**: Contextual logging, data arrays, configurable levels

#### Form Helper (`class-iwp-woo-v2-form-helper.php`)
- **Purpose**: Centralized HTML generation and form rendering
- **Impact**: Reduced HTML duplication across admin interfaces
- **Features**: Consistent form field generation, validation helpers

### Refactoring Results

#### Before and After Examples

**Security Validation - Before:**
```php
if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iwp_woo_v2_admin_nonce')) {
    wp_send_json_error(array('message' => __('Security check failed.', 'iwp-wp-integration')));
}
if (!current_user_can('manage_woocommerce')) {
    wp_send_json_error(array('message' => __('Insufficient permissions.', 'iwp-wp-integration')));
}
```

**Security Validation - After:**
```php
IWP_Woo_V2_Security::validate_ajax_request('iwp_woo_v2_admin_nonce', 'manage_woocommerce', 'nonce');
```

**Logging - Before:**
```php
error_log('IWP WooCommerce V2: Site creation failed - ' . $response->get_error_message());
```

**Logging - After:**
```php
IWP_Woo_V2_Logger::error('Site creation failed', 'site-manager', array('error' => $response->get_error_message()));
```

**Database Operations - Before:**
```php
$existing_domains = get_post_meta($order_id, '_iwp_mapped_domains', true);
if (!is_array($existing_domains)) {
    $existing_domains = array();
}
$existing_domains[] = $domain_info;
update_post_meta($order_id, '_iwp_mapped_domains', $existing_domains);
```

**Database Operations - After:**
```php
IWP_Woo_V2_Database::append_order_meta($order_id, '_iwp_mapped_domains', $domain_info);
```

### Files Refactored
1. **`class-iwp-woo-v2-admin.php`** - All AJAX handlers now use security helper
2. **`class-iwp-woo-v2-api-client.php`** - All logging converted to structured format
3. **`class-iwp-woo-v2-service.php`** - Service logging standardized
4. **`class-iwp-woo-v2-site-manager.php`** - Site tracking logging improved
5. **`class-iwp-woo-v2-product-integration.php`** - Product configuration logging

### Benefits Achieved
- **Code Reduction**: ~500+ lines of duplicated code eliminated
- **Consistency**: Uniform patterns across entire codebase
- **Maintainability**: Changes to patterns happen in one place
- **Debugging**: Enhanced logging with context and structured data
- **Security**: Centralized validation reduces security risks
- **Performance**: More efficient helper methods with caching

## Latest Session Accomplishments (January 2025)

### Major Fixes and Enhancements Completed

#### 1. Fixed Task Status Polling for Non-Pool Site Creation
**Problem**: Sites created without `is_pool: true` would get stuck showing "Your site is being created..." indefinitely because the WordPress cron job wasn't reliably checking task status.

**Solutions Implemented**:
- **Cron Hook Cleanup**: Added missing `iwp_woo_v2_check_pending_sites` hook cleanup in installer deactivation
- **Immediate Status Checking**: Added `maybe_check_pending_sites_immediately()` method that runs on `admin_init` for real-time updates
- **Manual Refresh Button**: Added "Refresh Site Status" button in admin UI with AJAX handler
- **Throttled Checking**: 30-second cooldown to prevent excessive API calls
- **Multiple Fallbacks**: Admin page checks, cron jobs, and manual triggers ensure reliability

#### 2. Synchronized Frontend and Admin Status Displays
**Problem**: Admin Sites table showed updated status, but frontend (My Account, order details) still showed "Your site is being created" because it used stale post meta data.

**Solution**: Modified `transform_site_data_for_frontend()` method to:
- Check database for latest status using `IWP_Woo_V2_Sites_Model::get_by_site_id()`
- Override post meta status with database status when available
- Merge updated site details (URLs, credentials) from completed API responses
- Maintain backward compatibility with existing data structure

#### 3. Enhanced Admin Interface
**Removed API Toggle**: Eliminated "Enable InstaWP Integration" checkbox and related logic
- Simplified interface by assuming activation upon installation
- Removed unnecessary complexity from admin UI
- Updated all related PHP and JavaScript code

**Enhanced API Key Field**:
- Added direct helper link to `https://app.instawp.io/user/api-tokens`
- Fixed HTML rendering using `wp_kses()` for safe anchor tag display
- Improved user experience with direct access to API token generation

#### 4. Improved Admin UI Organization
**Settings Sections Reordered**:
- Moved "InstaWP API Settings" above "General Settings" for logical flow
- Better user experience with configuration order

**WooCommerce Dependency Management**:
- Test order creation UI only shows when WooCommerce is installed
- Clear messaging when WooCommerce needs to be installed
- Prevents non-functional UI from appearing

#### 5. Enhanced Default Settings
**Auto-Enable Key Features**:
- "Auto-Create Sites on Purchase" defaults to checked
- "Use site_id Parameter" defaults to checked
- Better out-of-the-box experience for new installations

#### 6. Eliminated Duplicate Site Displays
**Problem**: Order details pages showed two InstaWP site sections - "InstaWP Sites" and "Your InstaWP Sites Are Ready!"

**Solution**: Removed `woocommerce_thankyou` hook to prevent duplication
- Kept only `woocommerce_order_details_after_order_table` hook
- Clean, single "InstaWP Sites" section display
- Eliminated user confusion from duplicate information

#### 7. JavaScript Event Handler Fixes
**Admin Script Enqueuing**: Fixed "Create Test Order" button not working
- Updated hook checking to include `toplevel_page_instawp-integration`
- Added fallback hooks for different menu configurations
- Ensured admin JavaScript loads on correct pages

**Manual Refresh Functionality**: Added complete AJAX workflow
- New `handleRefreshSiteStatus()` JavaScript method
- Proper loading states and user feedback
- Page reload after successful status refresh

### Technical Improvements

#### Enhanced Error Handling
- Comprehensive logging throughout status checking process
- Clear error messages for users
- Graceful fallbacks when API calls fail

#### Performance Optimizations
- Throttled status checks to prevent API rate limiting
- Efficient database queries with proper indexing
- Conditional loading of admin scripts

#### Security Enhancements
- Proper nonce verification on all AJAX handlers
- Capability checks for sensitive operations
- Input sanitization using WordPress functions

### User Experience Improvements

#### Frontend Auto-Refresh
- Existing 10-second auto-refresh for pending sites
- Real-time status updates without manual intervention
- Visual feedback with loading states and progress indicators

#### Admin Interface
- Prominent "Refresh Site Status" button for immediate updates
- Clear feedback messages showing pending site counts
- Simplified configuration with logical section ordering

#### Status Synchronization
- Seamless data flow from admin cron jobs to frontend display
- Consistent status across all user touchpoints
- No more indefinite "creating" messages

### Files Modified in This Session

1. **`includes/class-iwp-woo-v2-installer.php`**
   - Added cron hook cleanup for `iwp_woo_v2_check_pending_sites`
   - Updated default options for auto-create and site_id settings

2. **`includes/class-iwp-woo-v2-site-manager.php`**
   - Added immediate status checking methods
   - Enhanced frontend data transformation with database sync
   - Added manual refresh AJAX handler

3. **`includes/admin/class-iwp-woo-v2-admin.php`**
   - Fixed admin script enqueuing hooks
   - Added "Refresh Site Status" button
   - Enhanced API key field with helper link
   - Reordered settings sections

4. **`assets/js/admin.js`**
   - Added `handleRefreshSiteStatus()` method
   - Enhanced event binding for new button
   - Improved error handling and user feedback

5. **`includes/frontend/class-iwp-woo-v2-frontend.php`**
   - Removed duplicate `woocommerce_thankyou` hook
   - Cleaned up frontend display logic

### Testing and Validation

#### Status Polling Verification
- ✅ Non-pool sites now update status correctly
- ✅ Admin cron job processes pending sites
- ✅ Manual refresh button works immediately
- ✅ Frontend shows updated status within 10 seconds

#### UI/UX Validation
- ✅ Single "InstaWP Sites" section on order pages
- ✅ "Create Test Order" button functions properly
- ✅ API key field shows helper link correctly
- ✅ Settings sections in logical order

#### Cross-Platform Testing
- ✅ Admin interface works on all supported admin page hooks
- ✅ Frontend status sync works across My Account, order details, thank you pages
- ✅ Auto-refresh functionality maintains user experience

### Future Maintenance Notes

#### Monitoring Points
- Watch for any new cron job issues after plugin updates
- Monitor API rate limiting with increased status checking
- Verify frontend/admin sync continues working with WooCommerce updates

#### Potential Enhancements
- Real-time WebSocket status updates (future consideration)
- Bulk status refresh for multiple pending sites
- Enhanced admin dashboard with status overview widgets

## Recent Updates (August 2025)

### Site Expiry Settings Enhancement

#### Fixed Product-Level Site Expiry Configuration
**Problem**: The Site Expiry Settings in WooCommerce product edit pages had UI issues and incorrect default values.

**Solutions Applied**:
1. **Fixed Radio Button Overlap**: Updated CSS with proper flexbox spacing (30px gap) and `box-sizing: border-box`
2. **Corrected Default Hours**: Changed default expiry from 28 hours to 24 hours across all code references
3. **Mobile Responsive**: Added column layout for mobile devices to prevent radio button overlap

**Files Modified**:
- `includes/integrations/woocommerce/class-iwp-woo-product-integration.php` - Updated default values and help text
- `assets/css/integrations/woocommerce/woo-product.css` - Fixed radio button spacing and mobile layout

### Map Domain Functionality Restoration

#### Restored Missing Map Domain Button
**Problem**: Map Domain button was missing from order details and thank you pages after refactoring.

**Solutions Applied**:
1. **Fixed Context Checking**: Updated context checks to include all order page contexts (`'order-details'`, `'order-view'`, `'thank-you'`)
2. **Modal Rendering**: Ensured domain mapping modal renders on all relevant pages
3. **Fixed CSS Layout**: Added `box-sizing: border-box` to prevent input field overflow in the domain mapping modal

**Files Modified**:
- `includes/frontend/class-iwp-frontend.php` - Fixed context checks for button display and modal rendering
- `assets/css/frontend.css` - Fixed modal form field sizing issues

### Admin Menu Structure Improvement

#### Fixed Menu Hierarchy
**Problem**: Sites submenu was showing as "InstaWP" instead of "Sites".

**Solution Applied**:
- Added explicit submenu item to override WordPress's default first submenu behavior
- Now shows proper hierarchy: **InstaWP** → **Sites** → **Settings**

**Files Modified**:
- `includes/admin/class-iwp-admin-simple.php` - Added explicit "Sites" submenu entry

### Enhanced Sites Table Display

#### Plan Names Instead of Plan IDs
**Problem**: Sites table showed cryptic plan IDs instead of human-readable plan names.

**Solutions Applied**:
1. **Added Helper Method**: Created `IWP_Service::get_plan_name_by_id()` to lookup plan names from cached plan data
2. **Enhanced Table Display**: Updated sites table to show plan names with plan ID as tooltip
3. **Graceful Fallback**: Shows plan ID if plan name lookup fails

**Benefits**:
- ✅ User-friendly display: "Basic Plan" instead of "plan-abc123"
- ✅ Tooltip shows technical details on hover
- ✅ Maintains backward compatibility and error handling
- ✅ Preserves upgrade indicators and sorting functionality

**Files Modified**:
- `includes/core/class-iwp-service.php` - Added plan name lookup method
- `includes/admin/class-iwp-sites-list-table.php` - Enhanced plan column display

### Technical Improvements

#### CSS and Layout Fixes
- **Modal Forms**: Fixed input field overflow issues with proper `box-sizing`
- **Radio Buttons**: Improved spacing and mobile responsiveness
- **Form Validation**: Enhanced user experience with proper field sizing

#### Admin Interface Enhancements
- **Menu Structure**: Logical hierarchy with proper submenu naming
- **Table Display**: More informative data with human-readable plan names
- **Context Handling**: Consistent behavior across all order page types

### Testing and Validation

#### Feature Verification
- ✅ Site Expiry Settings display correctly without overlap
- ✅ Default expiry hours set to 24 across all contexts
- ✅ Map Domain button appears on all order pages (thank you, order view, My Account)
- ✅ Domain mapping modal displays without layout issues
- ✅ Admin menu shows "InstaWP" → "Sites" → "Settings" hierarchy
- ✅ Sites table displays plan names with ID tooltips
- ✅ Mobile responsive design maintained throughout

#### Error Handling
- ✅ Graceful fallback for plan name lookup failures
- ✅ Proper context checking across different page types
- ✅ CSS layout fixes prevent overflow issues
- ✅ Backward compatibility maintained for existing data

## Demo Helper Plugin Auto-Disable

### Overview
The plugin automatically disables the `iwp-demo-helper` plugin on sites after plan changes or status upgrades. This ensures that when customers upgrade from demo/trial plans to paid plans, any demo limitations are automatically removed.

### Key Components

#### API Client Enhancement (`class-iwp-api-client.php`)
- **New Method**: `disable_demo_helper($site_id, $site_url = '')`
- **Endpoint**: Calls `{site_url}/wp-json/iwp-demo-helper/v1/disable` on the target site
- **Auto URL Detection**: Automatically fetches site URL if not provided
- **Silent Failure Handling**: 404 responses are treated as success (plugin not installed)
- **Comprehensive Logging**: Both success and failure scenarios are logged

#### Integration Points

**1. Plan Upgrades (`class-iwp-woo-order-processor.php`)**
- Triggers when site plan is upgraded via WooCommerce order processing
- Called immediately after successful `upgrade_site_plan()` API call
- Does not fail the upgrade if demo helper disable fails

**2. Status Changes (`class-iwp-service.php`)**
- Triggers when site changes from temporary to permanent status
- Called in `set_permanent()` method when `$permanent = true` and site was previously temporary
- Handles both subscription-based and manual status changes

#### Technical Implementation

**API Call Structure:**
```php
// Endpoint
POST {site_url}/wp-json/iwp-demo-helper/v1/disable

// Headers
Content-Type: application/json
User-Agent: InstaWP-Integration/{version}

// Body
{
    "source": "instawp-integration",
    "site_id": 123,
    "timestamp": 1692345678
}
```

**Response Handling:**
- **200 OK**: Demo helper successfully disabled
- **404 Not Found**: Plugin not installed (treated as success)
- **Other codes**: Logged as warnings but don't fail the parent operation

#### Error Handling and Logging

**Success Scenarios:**
- Plugin disabled successfully (200)
- Plugin not installed/found (404) - silently ignored

**Failure Scenarios:**
- Network errors during API call
- HTTP error responses (non-200, non-404)
- Site URL cannot be determined

**Logging Examples:**
```
[INFO] Disabling demo helper plugin | site_id: 123, endpoint: https://site.com/wp-json/iwp-demo-helper/v1/disable
[INFO] Successfully disabled demo helper plugin | response: "Plugin disabled"
[INFO] Demo helper plugin not found (expected) | response_code: 404
[WARNING] Demo helper disable returned non-success code | response_code: 500
```

### Use Cases

#### Plan Upgrade Scenarios
1. **Demo to Paid Plan**: Customer purchases upgrade from demo plan → demo helper disabled
2. **Trial to Premium**: Trial period expires, customer pays → demo limitations removed
3. **Subscription Activation**: Free trial converts to paid subscription → demo features disabled

#### Status Change Scenarios
1. **Temporary to Permanent**: Site reservation status changes → demo helper disabled
2. **Subscription Payment**: Failed payment resolved → site becomes permanent → demo disabled
3. **Manual Admin Action**: Admin manually changes site to permanent → demo disabled

### Configuration

No additional configuration required - the feature works automatically when:
- Site plan upgrades occur through WooCommerce orders
- Site status changes from temporary to permanent
- The target site has the `iwp-demo-helper` plugin installed with the REST API endpoint

### Backward Compatibility
- Sites without the demo helper plugin are unaffected
- Feature fails gracefully if target site is unreachable
- Parent operations (plan upgrades, status changes) continue even if demo disable fails
- No breaking changes to existing API or database structures

---

*This documentation is maintained by Claude and should be updated with any significant changes to the plugin, especially customer-facing features and test functionality.*