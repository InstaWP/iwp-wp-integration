# InstaWP Integration Plugin

A comprehensive WordPress plugin that seamlessly integrates InstaWP's site creation and management capabilities with your WordPress website and WooCommerce store.

## Overview

The InstaWP Integration plugin enables you to:
- Create WordPress sites instantly using InstaWP snapshots
- Integrate site creation with WooCommerce products and orders
- Manage InstaWP sites directly from your WordPress admin
- Provide customers with easy access to their created sites
- Use shortcodes for standalone site creation forms

## Features

### 🛍️ **WooCommerce Integration**
- Add InstaWP snapshots and plans to WooCommerce products
- Automatically create sites when orders are completed
- Customer access to sites via My Account dashboard
- Email notifications with site details and login credentials
- Support for both permanent and temporary sites

### 🎛️ **Admin Management**
- Centralized settings with tabbed interface
- View and manage all InstaWP snapshots and plans
- Test order creation for development and testing
- Debug mode with comprehensive logging
- Sites management with quick actions

### 🎨 **Shortcode Support**
- `[iwp_site_creator]` shortcode for standalone site creation
- Customizable parameters for pre-filled forms
- Real-time status tracking during site creation
- Mobile-responsive design

### 🔧 **Advanced Features**
- Site plan upgrades via URL parameters
- Custom domain mapping for created sites
- Magic login integration for seamless admin access
- HPOS (High Performance Order Storage) compatibility
- Comprehensive error handling and logging

## Installation

1. Download the plugin files
2. Upload to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **InstaWP → Settings** to configure your API key

## Configuration

### Basic Setup

1. **Get Your API Key**
   - Visit [InstaWP API Tokens](https://app.instawp.io/user/api-tokens)
   - Generate a new API token
   - Copy the token

2. **Configure Plugin**
   - Navigate to **InstaWP → Settings** in WordPress admin
   - Enter your API key in the General Settings tab
   - Enable desired integration options
   - Click "Refresh Snapshots" and "Refresh Plans" to load your data

3. **Configure Products (WooCommerce)**
   - Edit any WooCommerce product
   - Go to the **InstaWP** tab
   - Select a snapshot and plan for the product
   - Choose site expiry settings (permanent/temporary)
   - Save the product

## Usage

### WooCommerce Integration

#### Product Configuration
1. Edit a WooCommerce product
2. Click the **InstaWP** tab
3. Select an InstaWP snapshot from the dropdown
4. Choose a plan (optional)
5. Configure site expiry:
   - **Permanent**: Sites remain active indefinitely
   - **Temporary**: Sites expire after specified hours (1-8760)
6. Save the product

#### Customer Experience
- Customers purchase products with InstaWP integration
- Sites are automatically created when orders are completed
- Customers receive email notifications with:
  - Site URL and admin login credentials
  - Magic login links (when available)
  - Direct access to their WordPress dashboard
- Customers can view all their sites in **My Account → Orders**

### Shortcode Usage

#### Basic Shortcode
```
[iwp_site_creator snapshot_slug="your-snapshot-slug"]
```

#### Advanced Examples
```
// Pre-filled form
[iwp_site_creator snapshot_slug="ecommerce-store" email="customer@example.com" name="My Store"]

// Temporary site (24 hours)
[iwp_site_creator snapshot_slug="demo-site" expiry_hours="24"]

// Demo environment
[iwp_site_creator snapshot_slug="wordpress-blog" email="demo@test.com" name="Demo Site" expiry_hours="48"]
```

#### Shortcode Parameters
- **`snapshot_slug`** (required): The InstaWP snapshot to use
- **`email`** (optional): Pre-fill the email field
- **`name`** (optional): Pre-fill the site name field
- **`expiry_hours`** (optional): Hours until site expires
- **`sandbox`** (optional): Set to "true" for shared/sandbox sites

### Site Plan Upgrades

Enable customers to upgrade existing sites instead of creating new ones:

1. **Enable Feature**: Check "Use site_id Parameter" in plugin settings
2. **Customer Workflow**:
   - Customer visits: `yoursite.com/shop/?site_id=123`
   - Plugin enters "upgrade mode" 
   - Customer purchases plan-enabled product
   - Existing site is upgraded instead of creating new site

### Team Management

The plugin supports InstaWP team functionality, allowing you to work with multiple teams and filter data accordingly.

#### Team Selection
1. Navigate to **InstaWP → Settings → InstaWP Data**
2. Use the team dropdown to select a specific team
3. By default, "User's Logged In Team" is selected (no team filter)
4. When you select a team, all snapshots and plans will be filtered to show only that team's data

#### Team Features
- **Automatic Filtering**: Snapshots and plans automatically filtered by selected team
- **Team-Specific Caching**: Each team's data is cached separately for better performance
- **API Parameter**: Selected team ID is automatically added to API calls as `?team_id={id}`
- **Persistent Selection**: Your team choice is saved and remembered across sessions

### Admin Features

#### Sites Management
- View all created sites in **InstaWP → Sites**
- Quick actions: Visit Site, Magic Login, Delete
- Real-time status updates
- Order references and customer information

#### Debug and Testing
- Enable debug mode for detailed logging
- Create test orders with real users
- Monitor API calls and responses
- Cache management for snapshots and plans

## API Reference

The plugin integrates with the following InstaWP API endpoints:

### Core APIs

| Endpoint | Method | Purpose | Timeout |
|----------|--------|---------|---------|
| `/teams` | GET | Fetch user teams | 60s |
| `/snapshots` | GET | Fetch available snapshots | 60s |
| `/get-plans?product_type=sites` | GET | Fetch hosting plans | 60s |
| `/sites/template` | POST | Create site from snapshot | 60s |
| `/tasks/{task_id}/status` | GET | Check site creation progress | 60s |
| `/sites/{site_id}` | GET | Get site details | 60s |
| `/sites/{site_id}/upgrade-plan` | POST | Upgrade site plan | 60s |
| `/sites/{site_id}` | PATCH | Update site configuration | 60s |
| `/sites/{site_id}` | DELETE | Delete site | 60s |

### Domain Management

| Endpoint | Method | Purpose | API Version |
|----------|--------|---------|-------------|
| `/site/add-domain/{site_id}` | POST | Map custom domain to site | v1 |

### API Features

- **Authentication**: Bearer token with API key
- **Content-Type**: JSON requests and responses
- **Timeout**: 60 seconds for all requests
- **Caching**: Snapshots (15 min), Plans (1 hour)
- **Error Handling**: Comprehensive WordPress error integration
- **Logging**: Detailed debug logging with sanitized responses

### Rate Limiting
The plugin includes built-in rate limiting and request throttling to prevent API abuse and ensure optimal performance.

## Settings Reference

### General Settings
- **Enable Integration**: Master toggle for the plugin
- **Auto-Create Sites**: Automatically create sites when orders complete
- **Use site_id Parameter**: Enable site upgrade functionality

### InstaWP Data
- **Team Selection**: Choose which team's data to view and manage
- **Snapshots**: View and refresh available snapshots (filtered by selected team)
- **Plans**: View and refresh available hosting plans (filtered by selected team)
- **API Status**: Connection status and cache information

### Testing & Development
- **Debug Mode**: Enable detailed logging
- **Log Level**: Set logging verbosity (Debug, Info, Warning, Error)
- **Test Orders**: Create test orders for development

## Troubleshooting

### Common Issues

#### Plugin Settings Not Saving
- Check user permissions (administrator required)
- Verify WordPress nonce functionality
- Check for plugin conflicts

#### Snapshots/Plans Not Loading
- Verify API key is correct and active
- Check internet connectivity
- Clear plugin cache (refresh buttons)
- Enable debug mode to view detailed error logs

#### Sites Not Creating
- Verify selected snapshot exists in your InstaWP account
- Check API key permissions
- Review WooCommerce order status (must be completed/processing)
- Check debug logs for specific error messages

#### Product Tab Missing
- Ensure WooCommerce is active
- Verify user has `edit_products` capability
- Check for theme/plugin conflicts
- Clear WordPress cache

### Debug Information

Enable debug mode to troubleshoot issues:

1. Go to **InstaWP → Settings → Testing & Development**
2. Enable "Debug Mode"
3. Set log level to "Debug"
4. Reproduce the issue
5. Check WordPress debug logs or contact support with log details

### Performance Tips

- **Use Caching**: Plugin caches API responses automatically
- **Optimize Settings**: Disable unnecessary features for better performance
- **Monitor Logs**: Regular log review helps identify issues early
- **API Limits**: Respect InstaWP API rate limits

## Support

### Getting Help

1. **Check Documentation**: Review this README and plugin settings
2. **Debug Logs**: Enable debug mode and check WordPress error logs
3. **Test Environment**: Use test order functionality to isolate issues
4. **Community Support**: WordPress community forums
5. **InstaWP Support**: For API-related issues, contact InstaWP support

### Reporting Issues

When reporting issues, please include:
- WordPress version
- WooCommerce version (if applicable)
- Plugin version
- Debug logs (with sensitive information removed)
- Steps to reproduce the issue
- Expected vs actual behavior

## Requirements

### WordPress
- **Minimum Version**: WordPress 5.0+
- **Recommended**: WordPress 6.0+
- **PHP Version**: 7.4+
- **Memory Limit**: 128MB+ recommended

### WooCommerce (Optional)
- **Minimum Version**: WooCommerce 5.0+
- **HPOS Support**: Compatible with High Performance Order Storage
- **Tested Up To**: WooCommerce 8.0+

### Server Requirements
- **cURL**: For API communications
- **JSON**: For API request/response handling
- **Sessions**: For site upgrade functionality
- **WordPress Cron**: For background processing

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 0.0.1
- Complete refactor from WooCommerce-specific to generic WordPress integration
- New simplified admin interface with tabbed settings
- Enhanced shortcode functionality with real-time status tracking
- Site upgrade functionality via URL parameters
- Magic login integration
- Custom domain mapping support
- HPOS compatibility
- Comprehensive error handling and logging
- Mobile-responsive design
- API timeout increased to 60 seconds

---

**Need Help?** Check the plugin settings page for more detailed documentation and examples, or refer to the troubleshooting section above.