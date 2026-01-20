# Changelog

All notable changes to the InstaWP Integration plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
- **Frontend Display**
  - Demo badge styling (orange with white text, uppercase)
  - Better order details display for reconciled sites
  - Improved mobile responsiveness for site cards

- **Error Handling**
  - Added try-catch block in `store_demo_site_in_database()` to prevent site creation failures
  - Added isset() checks for optional API response fields
  - Graceful handling when database column doesn't exist yet

### Fixed
- Parameter passing in `store_demo_site_in_database()` - now passes `$snapshot_slug` as parameter instead of accessing `$_POST` directly

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
