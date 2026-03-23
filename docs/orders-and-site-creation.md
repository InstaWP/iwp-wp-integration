# Orders & Site Creation

When a customer purchases a product linked to an InstaWP snapshot, a site is created automatically. This page explains the process and what to expect.

## How It Works

1. Customer completes checkout and the order reaches "completed" or "processing" status
2. The plugin checks each line item for an InstaWP snapshot assignment
3. If a matching demo site exists for the customer's email, it is converted to a paid site (see [Advanced Features](advanced-features.md))
4. If no demo site is found, the plugin calls the InstaWP API to create a new site
5. Site credentials and status are stored and linked to the order
6. The customer sees their site details on the order page and receives them by email

## Requirements

For automatic site creation to work:

- The product must have a snapshot selected in its **InstaWP** tab
- **Auto-Create Sites** must be enabled in **InstaWP > Settings**
- The **Enable Integration** master switch must be on
- The order must not have been processed already (the plugin prevents duplicates)

## What Happens During Creation

### Instant Sites (Pool Sites)

Some InstaWP snapshots use a pre-provisioned pool. Sites from the pool are available instantly. The status jumps directly to "completed" and credentials are available right away.

### Task-Based Sites

When a pool is not available, the site is created as a background task. The status starts as "creating" and the plugin checks the InstaWP API periodically until the site is ready. This usually takes 1 to 3 minutes.

### Custom Values

If the product has custom checkout fields enabled and the customer filled them in:

- The **username** they entered is used as the WordPress admin username
- The **subdomain** they entered is used for the site URL

If the customer left these fields blank, values are generated automatically.

## Order Notes

The plugin adds notes to the order documenting:

- Which product triggered site creation
- The snapshot and plan used
- Whether the site was created instantly (pool) or via a task
- The resulting site ID and URL
- Any errors that occurred

Check the order notes (on the order edit screen) for a full history of what happened.

## Demo Site Conversion

Before creating a new site, the plugin checks whether the customer already has a demo site (created via the shortcode) with a matching email address.

If a match is found:

- The demo site is converted to a paid site
- No new site is created
- The customer keeps the same site URL and content they already set up
- An order note documents the conversion

See [Advanced Features](advanced-features.md) for the full reconciliation process.

## Viewing Created Sites

After a site is created, it can be viewed in several places:

- **Admin**: Go to **InstaWP > Sites** to see all created sites. See [Managing Sites](managing-sites.md).
- **Customer**: The customer sees their site on the thank you page, in My Account under the order, and in email notifications. See [Customer Experience](customer-experience.md).

## Subscription Orders

Initial subscription orders create sites the same way as regular orders. Subscription switches (upgrades or downgrades) do not create new sites -- they upgrade the existing site's plan instead. See [Subscriptions](subscriptions.md) for details.
