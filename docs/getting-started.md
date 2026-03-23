# Getting Started

This guide walks you through installing the plugin and completing the initial setup.

## Prerequisites

- WordPress 5.0 or higher
- PHP 7.4 or higher
- An InstaWP account with at least one snapshot created
- WooCommerce 5.0 or higher (optional, but required for product-based site creation)

## Installation

### From a ZIP File

1. Download the plugin ZIP file
2. In your WordPress admin, go to **Plugins > Add New > Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Manual Upload

1. Extract the ZIP file on your computer
2. Upload the `iwp-wp-integration` folder to `/wp-content/plugins/`
3. Go to **Plugins** in your WordPress admin and click **Activate**

### Automatic Updates

The plugin checks for new versions automatically. When an update is available, you will see a notification on the **Plugins** page just like any other WordPress plugin. No configuration is needed for this to work.

## Initial Configuration

### Step 1: Get Your API Key

1. Go to [InstaWP API Tokens](https://app.instawp.io/user/api-tokens)
2. Click **Create New Token**
3. Give it a descriptive name (e.g., "My Store Integration")
4. Copy the generated token

Keep this key safe. It provides full access to your InstaWP account.

### Step 2: Enter Your API Key

1. In WordPress admin, go to **InstaWP > Settings**
2. On the **General Settings** tab, paste your API key into the API Key field
3. Click **Save Settings**

### Step 3: Load Your Snapshots and Plans

1. Switch to the **InstaWP Data** tab
2. Click **Refresh Snapshots** to load your available snapshots
3. Click **Refresh Plans** to load your hosting plans
4. Confirm that your snapshots and plans appear in the lists

### Step 4: Select a Team (Optional)

If your InstaWP account belongs to multiple teams:

1. On the **InstaWP Data** tab, select the team you want to use from the dropdown
2. Snapshots and plans will be filtered to show only those from the selected team
3. Leave it as "User's Logged In Team" to use your default team

## Verify Your Setup

After completing the steps above, you should see:

- Your API key saved on the General Settings tab
- Snapshots listed on the InstaWP Data tab
- Plans listed on the InstaWP Data tab (if you have any)

If you use WooCommerce Subscriptions, you will also see a green notice confirming the integration is active. If you do not use subscriptions, an informational message will appear instead -- this is normal.

## What Next?

- **Sell sites via WooCommerce**: See [WooCommerce Products](woocommerce-products.md) to configure products
- **Create standalone site forms**: See [Shortcodes](shortcodes.md) to add site creation to any page
- **Explore all settings**: See [Plugin Settings](settings.md) for the full reference
- **Test your setup**: See the test order feature in [Plugin Settings](settings.md)
