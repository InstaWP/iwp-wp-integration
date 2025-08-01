=== InstaWP Integration ===
Contributors: instawp
Tags: instawp, woocommerce, staging, development, site creation, snapshots
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Seamlessly integrate InstaWP with WooCommerce to automatically create staging and development sites from your product snapshots.

== Description ==

**InstaWP Integration** is a powerful WordPress plugin that bridges the gap between your WooCommerce store and InstaWP's site creation platform. Create stunning staging and development sites automatically when customers purchase your products, all powered by InstaWP's snapshot technology.

= Key Features =

* **Automatic Site Creation**: Sites are created automatically when customers complete purchases
* **Snapshot Integration**: Use InstaWP snapshots as product templates for instant site deployment
* **Customer Dashboard**: Full customer access to their sites through WooCommerce My Account
* **Magic Login**: One-click access to created sites without manual login
* **Real-time Status Tracking**: Live updates on site creation progress with automatic notifications
* **Email Integration**: Beautiful HTML and plain text emails with site details
* **Admin Management**: Comprehensive admin interface for managing all created sites
* **Test Order Creation**: Advanced testing tools with multiple customer options
* **Shortcode Support**: Standalone site creation forms for any page or post
* **Mobile Responsive**: Fully optimized for mobile devices with modern UI

= Perfect For =

* **Web Agencies**: Sell staging sites and development environments
* **Theme Developers**: Provide instant demos of your themes
* **Plugin Developers**: Offer test environments for your plugins
* **Freelancers**: Create client sites automatically upon payment
* **SaaS Providers**: Instant trial environments for your applications

= How It Works =

1. **Connect Your API**: Add your InstaWP API key in the plugin settings
2. **Configure Products**: Set up WooCommerce products with InstaWP snapshots
3. **Customer Purchase**: When customers buy, sites are created automatically
4. **Instant Access**: Customers receive site details via email and My Account dashboard
5. **Site Management**: Full admin control over all created sites with bulk actions

= Customer Experience =

Your customers will love the seamless experience:

* **Order Confirmation**: Site details appear immediately on the thank you page
* **Email Notifications**: Professional emails with login credentials and site access
* **My Account Integration**: Easy access to all their sites from WooCommerce dashboard
* **One-Click Access**: Magic login buttons for instant site access
* **Mobile Friendly**: Perfect experience across all devices

= Advanced Features =

* **Site Expiry Settings**: Configure automatic site expiration dates
* **Plan Integration**: Support for InstaWP hosting plans and upgrades  
* **Domain Mapping**: Custom domain setup with DNS guidance
* **Bulk Operations**: Manage multiple sites efficiently
* **Status Synchronization**: Real-time updates between admin and customer views
* **Security**: Comprehensive nonce verification and capability checks
* **Performance**: Optimized queries and caching for fast load times

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin dashboard
2. Go to Plugins → Add New
3. Search for "InstaWP Integration"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Upload to your `/wp-content/plugins/` directory
3. Extract the files
4. Activate the plugin through the WordPress admin

= Configuration =

1. Go to **Tools → InstaWP Integration** in your WordPress admin
2. Add your InstaWP API key (get it from https://app.instawp.io/user/api-tokens)
3. Configure your settings:
   - Enable auto-creation of sites on purchase
   - Set default site expiry (optional)
   - Configure email notifications
4. Set up your WooCommerce products with InstaWP snapshots
5. Test with the built-in test order creation tool

== Frequently Asked Questions ==

= Do I need an InstaWP account? =

Yes, you need an active InstaWP account and API key. Sign up at https://instawp.com if you don't have one.

= Is WooCommerce required? =

Yes, this plugin requires WooCommerce to be installed and activated for automatic site creation features. However, the shortcode functionality works independently.

= Can customers manage their sites? =

Absolutely! Customers can access all their sites through the WooCommerce My Account dashboard, including login credentials and direct access links.

= How are sites created? =

Sites are created using InstaWP's snapshot technology. You configure which snapshot to use for each product, and sites are deployed automatically when customers purchase.

= Can I set site expiration dates? =

Yes, you can configure automatic site expiration dates either globally or per product. Sites can be set to expire after a specific number of days.

= Does it work with any theme? =

Yes, the plugin is designed to work with any properly coded WordPress theme. The customer-facing elements use standard WooCommerce hooks and styling.

= Can I customize the emails? =

The plugin integrates with WooCommerce's email system and can be customized using standard WooCommerce email customization methods.

= Is it translation ready? =

Yes, the plugin is fully internationalized and ready for translation into any language.

== Screenshots ==

1. **Admin Settings** - Clean and intuitive settings panel with API configuration
2. **Sites Management** - Comprehensive table showing all created sites with bulk actions
3. **Product Configuration** - Easy snapshot selection and site settings per product
4. **Customer Dashboard** - Beautiful My Account integration showing customer sites
5. **Email Notifications** - Professional HTML emails with site access details
6. **Order Details** - Site information displayed on order confirmation pages
7. **Test Order Creation** - Advanced testing tools for development and debugging

== Changelog ==

= 2.0.0 =
* **Major Release** - Complete rewrite with enhanced features
* Added real-time site creation status tracking
* Implemented Magic Login functionality for one-click site access
* Enhanced customer dashboard with comprehensive site management
* Added email integration with HTML and plain text support
* Introduced shortcode functionality for standalone site creation
* Added site expiry settings and automatic cleanup
* Implemented domain mapping with DNS guidance
* Enhanced admin interface with bulk operations
* Added comprehensive test order creation tools
* Improved mobile responsiveness and modern UI
* Fixed task status polling for non-pool site creation
* Synchronized frontend and admin status displays
* Added manual site status refresh functionality
* Eliminated duplicate site displays on order pages
* Simplified admin interface and enhanced API key management
* Optimized performance with efficient database queries
* Enhanced security with comprehensive input validation

= 1.0.0 =
* Initial release
* Basic InstaWP integration
* Simple site creation functionality

== Upgrade Notice ==

= 2.0.0 =
This is a major update with significant new features and improvements. Please backup your site before upgrading. The new version includes enhanced customer experience, real-time status tracking, and comprehensive admin management tools.

== Support ==

For support, documentation, and feature requests, please visit:

* **Documentation**: https://docs.instawp.com/integrations/woocommerce
* **Support Forum**: https://wordpress.org/support/plugin/instawp-integration/
* **InstaWP Support**: https://instawp.com/support/

== Privacy Policy ==

This plugin connects to InstaWP's external service to create and manage sites. When sites are created:

* Site data is sent to InstaWP's API (https://api.instawp.com)
* Customer email addresses may be shared for site creation purposes
* No personal data is stored on InstaWP servers beyond what's necessary for site creation
* All data transmission is encrypted via HTTPS

Please review InstaWP's privacy policy at https://instawp.com/privacy-policy/

== Third-Party Services ==

This plugin integrates with the following external services:

* **InstaWP API** (https://api.instawp.com) - For site creation and management
* **InstaWP Dashboard** (https://app.instawp.io) - For Magic Login functionality

By using this plugin, you agree to the terms of service of these external providers.
