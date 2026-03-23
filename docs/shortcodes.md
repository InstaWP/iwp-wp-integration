# Shortcodes

The `[iwp_site_creator]` shortcode adds a standalone site creation form to any page or post. No WooCommerce is needed for this feature.

## Basic Usage

```
[iwp_site_creator snapshot_slug="your-snapshot-slug"]
```

Replace `your-snapshot-slug` with the slug of your InstaWP snapshot. You can find snapshot slugs on the **InstaWP > Settings > InstaWP Data** tab.

## Parameters

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `snapshot_slug` | Yes | -- | The InstaWP snapshot slug to create sites from |
| `email` | No | empty | Pre-fill the email field |
| `name` | No | empty | Pre-fill the site name field |
| `expiry_hours` | No | empty | Hours until the site expires. Leave empty for permanent sites. |
| `sandbox` | No | empty | Set to `"false"` to disable shared/sandbox mode |

## What the Form Looks Like

The form shows two fields:

- **Name** (required): A name for the site
- **Email** (required): The customer's email address

After clicking **Create Site**, the form shows:

1. A progress indicator while the site is being created
2. Once complete: the site URL, admin username (with copy button), admin password (hidden by default with show/hide toggle and copy button), and a login link

The form validates input on both the client side and server side.

## Demo Site Storage

Every site created through the shortcode is automatically saved in the database with a "demo" label and the customer's email address.

If the customer later makes a purchase on your WooCommerce store, the plugin matches their billing email to the stored demo site and converts it to a paid site. The customer keeps the same site URL and all their content.

This happens automatically and requires no configuration. See [Advanced Features](advanced-features.md) for the full reconciliation process.

## Examples

### Simple Demo Creator

```
[iwp_site_creator snapshot_slug="starter-blog"]
```

A basic form that creates permanent demo sites from the "starter-blog" snapshot.

### 24-Hour Trial

```
[iwp_site_creator snapshot_slug="ecommerce-store" expiry_hours="24"]
```

Sites expire after 24 hours, useful for limited-time trials.

### Pre-Filled Form

```
[iwp_site_creator snapshot_slug="agency-theme" email="client@example.com" name="Client Demo"]
```

The form loads with the email and name already filled in. Useful for personalized demo links.

## Tips

- Place the shortcode on a page with a clear call to action explaining what the customer will get
- The form is mobile-responsive and works on all screen sizes
- Multiple shortcodes on the same page are supported (each gets a unique ID)
- The **Delay Customer Credentials** setting does not affect shortcode-created sites. Shortcode sites always show credentials immediately after creation.
- You can use the shortcode on any page, not just WooCommerce pages
