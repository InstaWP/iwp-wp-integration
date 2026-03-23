# Setting Up WooCommerce Products

Any WooCommerce product can be linked to an InstaWP snapshot. When a customer buys the product, a site is automatically created from that snapshot.

## Configuring a Product

### Step 1: Open the Product Editor

Go to **Products > All Products** in your WordPress admin. Edit an existing product or create a new one.

### Step 2: Open the InstaWP Tab

In the Product Data section (below the product description), click the **InstaWP** tab on the left sidebar.

### Step 3: Select a Snapshot

Choose a snapshot from the dropdown. This determines which template is used when creating sites for this product.

If your snapshot does not appear, go to **InstaWP > Settings > InstaWP Data** and click **Refresh Snapshots**.

### Step 4: Select a Plan (Optional)

Choose a hosting plan to assign to the created site. This is optional. If you do not select a plan, the site will be created without a specific plan assignment.

If your plan does not appear, click **Refresh Plans** on the InstaWP Data settings tab.

### Step 5: Configure Site Expiry

Choose how long created sites should remain active:

- **Permanent**: Sites stay active indefinitely. This is the default.
- **Temporary**: Sites expire after a set number of hours (1 to 8,760 hours, which is one year).

Temporary sites are useful for trials or demos that you want to automatically clean up.

### Step 6: Enable Custom Checkout Fields (Optional)

Check **Show custom fields** to let customers choose their own WP admin username and site subdomain on the product page.

When this is unchecked, usernames and subdomains are generated automatically.

### Step 7: Save the Product

Click **Update** or **Publish**. All InstaWP settings are saved as product metadata.

## Custom Checkout Fields

When enabled, two optional fields appear on the product page:

### Username Field

- Customers can choose their WordPress admin username
- Rules: 3 to 20 characters, letters, numbers, and underscores only
- If left blank, the username is generated from the customer's billing name

### Subdomain Field

- Customers can choose the subdomain for their site URL
- Rules: 3 to 30 characters, letters, numbers, and hyphens only (no leading or trailing hyphens)
- Automatically converted to lowercase
- If left blank, the subdomain is generated from the product name and order number

Both fields have real-time validation on the product page. They show green when valid and red when invalid. The values carry through the cart and checkout and are used during site creation.

## Variable and Subscription Products

- For variable subscription products, each variation can have a different InstaWP plan
- The snapshot is set at the parent product level and applies to all variations
- This is useful when selling different tiers (e.g., Starter, Pro, Business) that all use the same base site but with different hosting plans

## Tips

- You can assign the same snapshot to multiple products
- A product without a snapshot selected will not trigger any InstaWP site creation
- Changes to the snapshot or plan assignment affect only future orders, not existing sites
