# WooCommerce Subscriptions

The plugin integrates with the WooCommerce Subscriptions plugin to manage site status based on subscription payment status and support plan switching.

## Prerequisites

- WooCommerce Subscriptions plugin installed and active
- Your products set up as subscription products in WooCommerce
- An InstaWP snapshot and plan assigned to the subscription product (see [WooCommerce Products](woocommerce-products.md))

The plugin detects WooCommerce Subscriptions automatically. You can confirm this on the **InstaWP > Settings > General Settings** tab, where a status indicator shows whether the integration is active.

## How Sites Are Created

When a customer subscribes (completes the initial subscription order), a site is created the same way as a regular order. The site record stores the subscription ID for ongoing management.

See [Orders & Site Creation](orders-and-site-creation.md) for details on the creation process.

## Subscription Lifecycle

The plugin automatically responds to subscription status changes:

### Subscription Active (Payment Success)

The site remains active and fully accessible. No changes are made.

### Subscription On-Hold (Payment Failed)

The site is flagged with a grace period. If the subscription is not restored within the grace period, the site may be affected.

### Subscription Cancelled or Expired

The site is marked for cleanup. A grace period gives the customer time to reactivate if the cancellation was unintentional.

## Plan Switching (Upgrade / Downgrade)

Customers can switch between subscription tiers directly from their account.

### What It Does

When a customer switches from one subscription tier to another (e.g., Starter to Pro), the plugin upgrades or downgrades the InstaWP hosting plan on their existing site. No new site is created.

### How Customers Switch

1. The customer goes to **My Account > Subscriptions**
2. They click on their active subscription
3. They click **Upgrade or Downgrade** (the exact label depends on your WooCommerce Subscriptions settings)
4. A plan selector appears showing the available options with their current plan marked
5. They select the new plan and complete checkout for the price difference

### What Happens Behind the Scenes

1. WooCommerce creates a switch order for the price difference
2. The plugin intercepts the switch and calls the InstaWP API to change the site's plan
3. If the API call fails, the plugin retries up to 3 times
4. Order and subscription notes document the plan change

### Variable Subscription Products

Each variation of a variable subscription product can have a different InstaWP plan. When customers switch between variations, the plugin automatically maps to the correct plan.

## Admin View

On the subscription edit page in the admin, you can see which site is linked to the subscription and its current status.

## Troubleshooting

- **"Subscriptions Not Detected" message**: Install and activate the WooCommerce Subscriptions plugin
- **Plan change failed**: Check the debug log for API errors and verify your InstaWP API key is valid
- **Duplicate site on plan switch**: This was a known issue in earlier versions. Update to the latest plugin version to fix it.

For more troubleshooting help, see [Troubleshooting](troubleshooting.md).
