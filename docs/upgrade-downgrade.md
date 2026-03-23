# Upgrade & Downgrade Products

This page explains how to set up products that let customers upgrade or downgrade their subscription plan, changing the hosting tier on their existing InstaWP site without creating a new one.

## Overview

When a customer switches between subscription tiers (e.g., Starter to Pro), the plugin automatically upgrades or downgrades the InstaWP hosting plan on their existing site. The customer keeps the same site URL, content, and credentials.

This requires WooCommerce Subscriptions and a variable subscription product with per-variation plan assignments.

## Prerequisites

- WooCommerce and WooCommerce Subscriptions plugins installed and active
- A variable subscription product in WooCommerce
- InstaWP snapshots and plans loaded in **InstaWP > Settings > InstaWP Data**

## Setting Up the Product

### Step 1: Create a Variable Subscription Product

1. Go to **Products > Add New**
2. In the Product Data dropdown, select **Variable subscription**
3. Add attributes (e.g., "Plan" with values: Starter, Plus, Pro)
4. Go to the **Variations** tab and create variations for each attribute

### Step 2: Configure the InstaWP Snapshot

1. Click the **InstaWP** tab in the Product Data section
2. Select the snapshot that will be used when creating sites
3. The snapshot applies to all variations -- every tier uses the same base site template

### Step 3: Assign Plans to Each Variation

1. Expand each variation in the **Variations** tab
2. In the InstaWP section of each variation, select the appropriate hosting plan:
   - Starter variation: select the Starter plan
   - Plus variation: select the Plus plan
   - Pro variation: select the Pro plan
3. If a variation does not have a plan selected, it inherits the plan from the parent product

### Step 4: Configure Pricing

Set subscription prices for each variation as you normally would in WooCommerce. The price difference between tiers is what the customer pays when switching.

### Step 5: Enable Switching in WooCommerce

1. Go to **WooCommerce > Settings > Subscriptions**
2. Set **Switching** to allow customers to switch between variations
3. Configure proration settings (how the price difference is calculated)

### Step 6: Save the Product

Click **Publish** or **Update**. The product is now ready for subscription switching.

## Plan Hierarchy

The plugin recognizes these InstaWP plan tiers in order from lowest to highest:

| Rank | Plan |
|------|------|
| 1 | Free |
| 2 | Sandbox |
| 3 | Starter |
| 4 | Plus |
| 5 | Pro |
| 6 | Turbo |
| 7 | Elite |

Moving to a higher-ranked plan is an **upgrade**. Moving to a lower-ranked plan is a **downgrade**. The plugin uses this hierarchy to label the switch correctly in order notes and customer-facing UI.

## What the Customer Sees

### Starting a Switch

1. The customer goes to **My Account > Subscriptions**
2. They click on their active subscription
3. They click the **Upgrade or Downgrade** link next to the subscription item

### The Plan Selector

Instead of the default WooCommerce grouped product table, the customer sees a clean radio-button interface:

- Each plan option shows the plan name and price
- The customer's current plan is labeled **"Current plan"**
- Higher plans are labeled **"Upgrade"**
- Lower plans are labeled **"Downgrade"**
- The selected plan is highlighted with a blue border

The customer selects their desired plan and clicks the add-to-cart button to proceed to checkout.

### After Checkout

- WooCommerce creates a switch order for the price difference (prorated based on your settings)
- The customer sees the updated plan details on their order page
- An "Upgraded" or "Downgraded" badge appears on the site card

## What Happens Behind the Scenes

1. WooCommerce Subscriptions creates a switch order and updates the subscription to the new variation
2. The plugin detects the switch and identifies the old and new plan IDs from the variation metadata
3. The plugin calls the InstaWP API to change the hosting plan on the existing site
4. The site record in the database is updated with the new plan
5. Order notes are added to both the switch order and the subscription documenting the change

**Important**: Switch orders do not create new sites. The plugin specifically skips site creation for switch orders and only processes the plan change.

## Automatic Retry

If the InstaWP API call fails during a plan change, the plugin retries automatically:

- **Attempt 1**: Retries after 5 minutes
- **Attempt 2**: Retries after 15 minutes
- **Attempt 3**: Retries after 45 minutes

If all 3 retries fail, the plugin logs a critical error and adds an order note indicating that manual intervention is needed. You would then need to change the plan directly in the InstaWP dashboard.

## Admin View

### Order Notes

Both the switch order and the subscription show notes documenting:

- Successful changes: "InstaWP: Site ABC upgraded from Plus to Pro."
- Failed changes: "InstaWP: Failed to upgrade site ABC. Error: [reason]. Manual intervention may be required."
- Retry successes: "InstaWP: Plan change for site ABC completed on retry attempt 2."

### Sites Table

In **InstaWP > Sites**, the site's plan column reflects the current plan after a successful switch.

## Troubleshooting

### Plan change not happening after switch

1. Enable debug mode in **InstaWP > Settings > Testing & Development**
2. Check `wp-content/debug.log` for entries containing "switch-handler"
3. Verify that each variation has the correct InstaWP plan assigned
4. Confirm the InstaWP API key is valid

### Duplicate site created on switch

This was a bug in earlier versions where the order processor did not properly skip switch orders. Update to the latest plugin version to fix this.

### Customer sees default grouped product table instead of plan selector

The plan selector UI only appears when the customer is switching (arrives via the "Upgrade or Downgrade" link). If they visit the product page normally, they see the standard WooCommerce product form.

### Wrong plan applied after switch

Verify the plan assignments on each variation:

1. Edit the product
2. Expand each variation in the **Variations** tab
3. Confirm the InstaWP plan dropdown shows the correct plan for each tier

## See Also

- [WooCommerce Products](woocommerce-products.md) for general product configuration
- [Subscriptions](subscriptions.md) for subscription lifecycle management
- [Managing Sites](managing-sites.md) for viewing site plan status
- [Troubleshooting](troubleshooting.md) for debug mode and common issues
