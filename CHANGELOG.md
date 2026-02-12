# Changelog

All notable changes to the InstaWP Integration plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.0.4] - 2025-02-10

### Added
- **Custom Checkout Fields**
  - Customers can choose their WP admin username on the product page
  - Customers can choose their site subdomain on the product page
  - Both fields are optional — left blank falls back to auto-generation
  - Client-side real-time validation with submit prevention
  - Values carry through cart → checkout review → order item meta (`_iwp_admin_username`, `_iwp_subdomain`)
  - New `IWP_Woo_Product_Fields` class handles the full WooCommerce field chain

- **Enhanced My Account Dashboard Cards**
  - Site URL now displayed as a clickable link directly on dashboard cards
  - Magic Login button shown on dashboard cards when `s_hash` is available
  - Better site name display (site name → snapshot slug → fallback)

- **GitHub Auto-Updater**
  - Plugin now checks GitHub releases for new versions automatically
  - Update notifications appear in WordPress admin like any other plugin
  - Downloads release zip assets built by GitHub Actions
  - New `IWP_GitHub_Updater` class with 15-minute cache

### Fixed
- **API Parameter Names**
  - Fixed site creation API call to use correct parameter names (`site_name`, `user_name`, `email`)
  - Previously sent `name`, `admin_username`, `admin_email` which were ignored by the API

- **Nonce Validation on Cached Pages**
  - Fixed "Security check failed" error for logged-out users on cached pages
  - Shortcode form now fetches a fresh nonce via AJAX on page load
  - New `iwp_refresh_nonce` AJAX endpoint for cache-safe nonce generation

## [0.0.3] - 2025-01-20

### Added
- **Demo Site Storage & Reconciliation System**
  - Automatic database storage for all demo sites created via `[iwp_site_creator]` shortcode
  - Email-based reconciliation: converts demo sites to paid when customer purchases
  - Supports multiple demo sites per customer (all converted on first purchase)
  - Works for both logged-in users and guests
  - "Converted from Demo" badge displays in order details
  - Added `get_demo_sites_by_email()` method for email-based site lookup
  - Added `get_demo_sites_by_user()` method for user-specific demo site queries
  - Added `mark_expired_demos()` method for demo site cleanup

- **Go Live Page Smart Redirect**
  - Automatically redirects logged-in users with paid sites to My Account dashboard
  - Prevents confusion for customers who already purchased
  - Configurable page slugs (`go-live`, `launch-your-demo-site`)
  - New `IWP_GoLive_Page` class handles redirect logic

- **Automatic Database Migration System**
  - Version-aware database updates with `$db_updates` array
  - Automatic migration on plugin update via `iwp_init()`
  - Manual migration page: `/wp-admin/admin.php?page=iwp-migrate-db`
  - Admin notices when database updates are available via `IWP_DB_Update_Notice`
  - Safe and idempotent migrations (can run multiple times without issues)
  - `needs_database_update()` method checks if migration needed
  - `add_site_type_column()` migration method for v0.0.3 update

- **Admin Filters & Search**
  - WordPress-standard status view links (All, Active, Creating, Failed, Expired)
  - Source filter dropdown with all unique sources (WooCommerce, Shortcode, etc.)
  - Search box for filtering by URL, username, user, site_id, order_id
  - All filters work together and maintain state across pagination
  - Dynamic counts update based on filter selection
  - `get_views()` method for status tabs with counts
  - `extra_tablenav()` method for filter dropdown
  - `apply_search_filter()`, `apply_status_filter()`, `apply_source_filter()` methods

- **Expiry Status Tracking**
  - Real-time expiry calculation without requiring API calls
  - Display "Expired" status badge for sites past their expiry time
  - Show time remaining for active temporary sites (hours/minutes)
  - Visual warnings when sites have <1 hour remaining (red text)
  - Added "Expired" filter tab in admin Sites table
  - Expiry calculation based on `expiry_hours` and `created_at` timestamp
  - Supports both permanent sites (no expiry) and temporary sites

### Changed
- **Database Schema Updates**
  - Added `site_type` column (VARCHAR 50, DEFAULT 'paid') to `wp_iwp_sites` table
  - Added `idx_site_type` index for improved query performance
  - Existing sites automatically default to `site_type='paid'` for backward compatibility

- **Site Storage Behavior**
  - WooCommerce-created sites explicitly marked as `site_type='paid'`
  - Shortcode-created sites automatically marked as `site_type='demo'`
  - Store customer email in `source_data` JSON field for reconciliation
  - Store subscription IDs in `source_data` during reconciliation

### Improved
- **Admin Sites Table UI**
  - Added expiry time indicators in Status column (e.g., "Active (12 hrs)")
  - Color-coded status badges for better visual hierarchy
  - Enhanced badge system with consistent styling (CSS classes: `.iwp-status-expired`, `.iwp-expiry-warning`, `.iwp-expiry-info`)
  - Better mobile responsiveness for filters and search
  - Filter dropdown and search box positioned WordPress-standard way
  - View links with dynamic counts update based on filtering

- **Frontend Display**
  - Demo badge styling (orange with white text, uppercase)
  - Better order details display for reconciled sites
  - Improved mobile responsiveness for site cards

- **Code Quality**
  - Applied DRY principle to badge generation with helper methods
  - `determine_source_type()` - single source of truth for source display logic
  - `get_source_badge_html()` - centralized badge HTML generation
  - Moved inline CSS to stylesheet for better maintainability
  - Made badge system extensible for new badge types
  - Consistent data structure across database and order meta sites

- **Error Handling**
  - Added try-catch block in `store_demo_site_in_database()` to prevent site creation failures
  - Added isset() checks for optional API response fields
  - Graceful handling when database column doesn't exist yet

### Fixed
- **Demo Site Reconciliation**
  - Fixed reconciliation to use `site_id` for upgraded sites instead of relying only on email matching
  - Added priority-based reconciliation: 1) `upgrade_site_id` from session, 2) `site_id` from order meta, 3) email matching
  - Reconciliation now properly handles site upgrade flow when user visits `?site_id=123` and purchases
  - Fixed issue where upgraded demo sites weren't being converted to paid status

- **Shortcode Site Creation**
  - Fixed database not being updated after non-pool site task completion
  - Added `update_demo_site_details()` method to store credentials when AJAX polling completes
  - Sites now properly store full details (URLs, credentials, s_hash) after task finishes
  - Fixed issue where site appeared in frontend but credentials missing from database

- **Order Sites Display**
  - Fixed `get_order_sites()` to prioritize database records over stale order meta
  - Database sites loaded first for most up-to-date information
  - Removed adding reconciled sites to order meta (prevents displaying outdated information)
  - Fixed issue where plan upgrades showed old site data instead of updated values

- **Expiry Hours Management**
  - Fixed `expiry_hours` not being cleared on demo→paid conversion
  - Original expiry values now preserved in `source_data` for history tracking
  - Converted sites properly marked as permanent (`is_reserved=true`, `expiry_hours=null`)
  - Prevents accidentally expiring paid sites that were converted from demos

- **Parameter Passing**
  - Fixed parameter passing in `store_demo_site_in_database()` - now passes `$snapshot_slug` as parameter instead of accessing `$_POST` directly

## [0.0.2] - 2024-08

### Fixed
- **WooCommerce Subscriptions Integration**
  - Fixed argument count errors in subscription hooks by making parameters optional
  - Updated method signatures: `handle_subscription_active($subscription, $old_status = '')`
  - Prevents fatal errors when hooks called with varying argument counts

- **wpdb::prepare Array Error**
  - Fixed database error when updating site records with array values
  - Added data sanitization loop to JSON-encode array values before storage
  - Prevents "Unsupported value type (array)" notices

- **Frontend Credential Display**
  - Fixed username and password not displaying in customer-facing interfaces
  - Updated credential retrieval to prioritize direct database fields over JSON response
  - Ensures credentials always visible when stored

- **Site Information Positioning**
  - Changed hook from `woocommerce_view_order` to `woocommerce_order_details_after_order_table`
  - Site details now appear right after order table instead of at page bottom
  - Improved visibility and user experience

### Added
- **Demo Helper Auto-Disable**
  - Automatically disables `iwp-demo-helper` plugin when sites upgrade to paid plans
  - `disable_demo_helper()` method makes REST API call to site
  - Integration points: plan upgrades and status changes

- **Team Management Improvements**
  - Better team filtering and caching for multi-team environments
  - Team-specific cache keys: `iwp_snapshots_{team_id}`, `iwp_plans_{team_id}`
  - Selected team ID stored in plugin settings for persistence

### Improved
- **UI Enhancements**
  - Fixed radio button spacing in product settings
  - Improved mobile responsiveness in product configuration
  - Better visual hierarchy in settings tabs

- **Map Domain Functionality**
  - Restored missing domain mapping features on order pages
  - Modal interface with DNS instructions
  - Support for primary and alias domain types

## [0.0.1] - 2024-07

### Added
- **Complete Plugin Refactor**
  - Transformed from "IWP WooCommerce Integration v2" to generic WordPress integration
  - New simplified admin interface with tabbed settings
  - Support for standalone functionality beyond WooCommerce

- **Shortcode System**
  - `[iwp_site_creator]` shortcode for standalone site creation
  - Real-time status tracking during site creation
  - Mobile-responsive design with touch-friendly interface
  - Parameters: `snapshot_slug`, `email`, `name`, `expiry_hours`, `sandbox`

- **Site Upgrade Functionality**
  - URL parameter system (`?site_id=123`) for site upgrades
  - Session-based upgrade mode detection
  - Upgrades existing sites instead of creating new ones
  - Order meta tracking for upgrade history

- **Magic Login Integration**
  - Direct WordPress admin access via `s_hash` tokens
  - Magic login URLs: `https://app.instawp.io/wordpress-auto-login?site={s_hash}`
  - Seamless customer experience across all touchpoints
  - Fallback to regular wp-admin when s_hash unavailable

- **Custom Domain Mapping**
  - Customer interface for mapping custom domains
  - Support for primary and alias domain types
  - DNS configuration guidance (CNAME setup instructions)
  - Client and server-side domain validation
  - Domain history tracking per order

- **HPOS Compatibility**
  - Full support for WooCommerce High Performance Order Storage
  - Declared compatibility with `FeaturesUtil::declare_compatibility()`
  - Tested with WooCommerce 8.0+

- **Enhanced Error Handling**
  - Comprehensive WP_Error integration throughout
  - Structured logging system with context and levels
  - Debug mode with configurable log verbosity
  - User-friendly error messages for customers

- **API Enhancements**
  - Increased API timeout to 60 seconds (from default)
  - Better rate limiting and request throttling
  - Team management support with team filtering
  - Detailed debug logging with sanitized responses

### Changed
- **Architecture**
  - Simplified admin system replacing legacy complex interfaces
  - Service layer for centralized business logic
  - Repository pattern for database operations
  - PSR-4 autoloading with `IWP_Autoloader`

- **Settings Interface**
  - New tabbed configuration system
  - General Settings, InstaWP Data, Testing & Development tabs
  - Team selection dropdown for multi-team accounts
  - Cache status indicators and manual refresh buttons

- **Site Management**
  - WordPress-style sites table with row actions
  - Quick actions: Visit Site, Magic Login, Admin Login, Delete
  - Real-time status updates for pending sites
  - Order and customer reference columns

- **Test Order System**
  - Comprehensive testing functionality for development
  - Three customer options: existing user, guest checkout, create new user
  - Real user creation for authentic testing experience
  - Email notification testing

### Improved
- **Performance**
  - Intelligent caching: Snapshots (15 min), Plans (1 hour)
  - Conditional resource loading (admin/frontend separation)
  - Database query optimization with proper indexing
  - API request throttling to prevent rate limiting

- **Security**
  - Comprehensive input sanitization with WordPress functions
  - CSRF protection with nonce verification on all forms
  - Proper capability checks (`manage_options`, `edit_products`)
  - SQL injection prevention with prepared statements
  - Secure password handling with toggle visibility

- **Customer Experience**
  - Sites displayed after order table for better visibility
  - Email notifications include site details and credentials
  - My Account dashboard integration
  - Copy-to-clipboard functionality for credentials
  - Responsive design for mobile devices

### Database
- **Initial Schema**: `wp_iwp_sites` table
  - Comprehensive site tracking with 20+ fields
  - Indexes on `site_id`, `status`, `order_id`, `user_id`
  - JSON storage for `source_data` and `api_response`
  - Timestamp tracking with `created_at` and `updated_at`

## [Unreleased]

### Planned Features
- Bulk demo site cleanup tool in admin
- Separate demo site dashboard
- Email notifications when demo converts to paid
- Analytics tracking for demo → paid conversion rates
- Trial period support with time-limited demo sites
- Feature limitations for demo vs paid sites

---

## Version History Summary

| Version | Release Date | Key Features |
|---------|--------------|--------------|
| 0.0.4   | 2025-02-10   | Custom checkout fields, enhanced dashboard, GitHub auto-updater |
| 0.0.3   | 2025-01-20   | Demo site storage & reconciliation, automatic migrations |
| 0.0.2   | 2024-08      | Bug fixes, subscriptions integration, frontend improvements |
| 0.0.1   | 2024-07      | Initial release, complete refactor, shortcode system |

---

## Upgrade Notes

### Upgrading to 0.0.3 from 0.0.2
- **Database migration runs automatically** on plugin update
- No manual intervention required for most users
- Existing sites default to `site_type='paid'` (backward compatible)
- If automatic migration fails, visit `/wp-admin/admin.php?page=iwp-migrate-db`
- No data loss - all existing site data preserved

### Upgrading to 0.0.2 from 0.0.1
- No database changes required
- Settings preserved automatically
- WooCommerce Subscriptions support added (optional)

### Upgrading to 0.0.1 (Fresh Install)
- Requires WooCommerce 5.0+ for WooCommerce features (optional)
- PHP 7.4+ required
- Database tables created automatically on activation

---

## Support

For questions, issues, or contributions:
- Check plugin debug logs: `/wp-content/debug.log`
- Review documentation: `README.md` and `MIGRATION-GUIDE.md`
- Manual migration page: `/wp-admin/admin.php?page=iwp-migrate-db`
- Enable debug mode: InstaWP → Settings → Testing & Development

---

*This changelog is maintained to help users understand what has changed between versions. For detailed technical documentation, see `CLAUDE.md`.*
