# Plugin Settings

All plugin settings are found at **InstaWP > Settings** in your WordPress admin sidebar. The settings page has four tabs.

## General Settings

### API Key

Your InstaWP API token. This is required for the plugin to communicate with InstaWP.

- Get your key at [InstaWP API Tokens](https://app.instawp.io/user/api-tokens)
- If you regenerate your key on InstaWP, update it here as well

### Enable Integration

Master switch for the WooCommerce integration features. When disabled:

- No sites are created automatically from orders
- Product configuration still works (so you can set things up before going live)
- Shortcode functionality is not affected by this setting

### Auto-Create Sites

When enabled, sites are created automatically when WooCommerce orders reach "completed" or "processing" status.

When disabled, orders are processed normally but no InstaWP sites are created. You would need to create sites manually through the InstaWP dashboard.

### Auto-Convert Demo or Trial Sites

Enables the site upgrade URL parameter feature. When a customer visits your store with `?site_id=123` in the URL, their purchase upgrades the existing site instead of creating a new one.

Leave this enabled if you use the demo-to-paid conversion workflow. See [Advanced Features](advanced-features.md) for details.

### Delay Customer Credentials

When enabled, site login credentials (username and password) are hidden from customers after purchase. Instead, customers see a message saying their site is being prepared.

This gives you time to review or customize the site before granting access. When ready, release credentials from the Sites table using the **Send Credentials** action. See [Managing Sites](managing-sites.md) for how to do this.

### WooCommerce Subscriptions Status

This is not a setting -- it is a status indicator. It shows whether the WooCommerce Subscriptions plugin is detected and active. If you do not use subscriptions, this message is informational only.

## InstaWP Data

This tab shows your InstaWP account data and lets you manage caching.

### Team Selection

If your InstaWP account belongs to multiple teams, select which team to use. Snapshots and plans are filtered by the selected team.

- **User's Logged In Team** (default): Uses your account's default team
- Click **Refresh Teams** to fetch the latest team list from InstaWP

### Snapshots

Lists all snapshots available in your InstaWP account (filtered by team). Each entry shows the snapshot name and slug.

- The slug is what you use when configuring products or shortcodes
- Click **Refresh Snapshots** to fetch fresh data from InstaWP
- Snapshots are cached for 15 minutes to reduce API calls

### Plans

Lists all available hosting plans. Each entry shows the plan name, description, and ID.

- The plan ID is used when assigning plans to products
- Click **Refresh Plans** to fetch fresh data
- Plans are cached for 1 hour

## Testing & Development

### Debug Mode

Enable this to turn on verbose logging for troubleshooting. Logs are written to the standard WordPress debug log at `wp-content/debug.log`.

- Make sure `WP_DEBUG` and `WP_DEBUG_LOG` are set to `true` in your `wp-config.php` for logs to appear
- Disable debug mode in production to avoid filling up your log files

### Log Level

Controls how much detail is logged when debug mode is active:

- **Debug**: Most verbose. Logs everything including API request/response details.
- **Info**: General operational messages.
- **Warning**: Only warnings and errors.
- **Error**: Only errors. Recommended for production if you leave debug mode on.

### Test Order Creation

Available only when WooCommerce is active. Creates a test order to verify your integration works.

1. Select an InstaWP-enabled product from the dropdown
2. Click **Create Test Order**
3. A completed order is created and assigned to your admin account
4. Use the provided links to view the order in admin or test the customer experience

This is useful for verifying the full site creation flow without processing a real payment.

## Documentation

This is the tab you are currently reading. It provides access to the complete user guide for the plugin.
