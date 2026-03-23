# Troubleshooting

This page covers how to enable debug logging and solutions to common issues.

## Enabling Debug Mode

1. Go to **InstaWP > Settings > Testing & Development** tab
2. Check **Enable debug mode**
3. Set the Log Level to **Debug** for maximum detail
4. Click **Save Debug Settings**

You also need WordPress debugging enabled in your `wp-config.php` file:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Logs appear in `wp-content/debug.log`. Look for entries containing "IWP" to find plugin-related messages.

**Important**: Disable debug mode on production sites when you are done troubleshooting. Verbose logging fills up disk space.

## Common Issues

### "No snapshots available" in product dropdown

1. Go to **InstaWP > Settings > InstaWP Data** tab
2. Click **Refresh Snapshots**
3. Verify your API key is correct on the General Settings tab
4. Check that you have snapshots in your InstaWP account
5. If using teams, verify the correct team is selected

### Site not created after order

1. Confirm **Auto-Create Sites** is enabled in settings
2. Confirm the product has a snapshot assigned in its InstaWP tab
3. Check the order status. Sites are created when the order reaches "completed" or "processing" status.
4. Check the order notes (order edit screen) for error messages
5. Check `wp-content/debug.log` for API errors
6. Verify your InstaWP API key has not expired

### Customer cannot see credentials

If **Delay Customer Credentials** is enabled, this is expected. Go to **InstaWP > Sites** and click **Send Credentials** for the site.

If the setting is not enabled and credentials are still missing, enable debug mode and check the log for any errors during credential storage.

### Magic Login not working

- The auto-login token may not be available yet if the site is still being created
- Refresh the Sites table and check if the site status is "Active"
- The auto-login URL requires the customer's browser to be able to reach InstaWP's servers

### Subscription plan change failed

- Enable debug mode and check the log for API errors during the plan switch
- The plugin retries automatically up to 3 times
- Verify the InstaWP plans are correctly configured in the product variations
- Make sure the InstaWP API key is valid

### Demo site not converted to paid

Reconciliation matches by email address. Verify:

- The email the customer used during demo creation matches their billing email on the order
- The demo site exists in **InstaWP > Sites** with the correct email in its source data
- The demo site was not already converted (check if it shows "Converted from Demo")

### "API key is not configured" on InstaWP Data tab

Switch to the **General Settings** tab and enter your InstaWP API key. Then return to the InstaWP Data tab.

### Plugin update not appearing

The GitHub auto-updater caches results for 15 minutes. Either wait for the cache to expire or try reloading the Plugins page after 15 minutes.

Verify your server can reach GitHub's API at `api.github.com`.

## Using the Test Order Feature

The test order tool creates a real WooCommerce order without payment processing. This is the fastest way to verify your integration.

1. Go to **InstaWP > Settings > Testing & Development** tab
2. Select an InstaWP-enabled product from the dropdown
3. Click **Create Test Order**
4. A completed order is created and assigned to your admin account
5. Use the provided links to view the order in admin or test the customer experience in My Account

## When to Contact Support

Contact InstaWP support if you experience:

- Consistent API errors that persist after verifying your API key
- Sites stuck in "Creating" status for more than 15 minutes
- Plan changes failing repeatedly despite valid configuration
- Any issues with the InstaWP platform itself

When reporting issues, include:

- Plugin version (shown on the Plugins page)
- WordPress and WooCommerce versions
- Relevant entries from `wp-content/debug.log` (remove any API keys before sharing)
- Steps to reproduce the issue

## See Also

- [Plugin Settings](settings.md) for the debug mode and log level reference
- [Managing Sites](managing-sites.md) for the Sites table and actions
- [Orders & Site Creation](orders-and-site-creation.md) for the site creation process
