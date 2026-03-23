# Advanced Features

This page covers power-user features that extend the core site creation workflow.

## Site Plan Upgrades via URL Parameter

### What It Does

Lets you send customers a link that upgrades their existing site instead of creating a new one.

### Setup

1. Enable **Auto-Convert Demo or Trial Sites** in **InstaWP > Settings > General Settings**
2. Create a WooCommerce product with a plan assigned in the InstaWP tab

### How to Use

Give the customer a link to your store with the site ID appended:

```
https://your-store.com/shop/?site_id=123
```

The `site_id` value is stored in the customer's session. When they complete a purchase, the plugin upgrades the existing site's plan instead of creating a new site.

### What the Customer Sees

- A blue notice appears on the shop and product pages: "You are upgrading an existing site"
- After purchase, the order shows the upgrade details instead of new site creation
- The site URL and credentials remain the same

## Custom Domain Mapping

### What It Does

Lets customers map their own domain name to their InstaWP site.

### Where It Appears

A **Map Domain** button appears on site cards in the customer's **My Account > Orders** view.

### How It Works

1. The customer clicks **Map Domain**
2. A popup opens with DNS setup instructions and a form
3. The customer enters their domain name (without `http://` or `https://`)
4. They select the domain type: **Primary** (main domain) or **Alias** (redirect)
5. They click **Map Domain** to submit
6. The plugin registers the domain with InstaWP
7. Mapped domains appear below the site card

### DNS Setup

The customer needs to create a CNAME record with their domain registrar pointing their domain to the InstaWP site URL. Step-by-step instructions are shown in the popup.

## Demo Site Reconciliation

### What It Does

Automatically converts demo sites (created via the shortcode) into paid sites when the customer makes a purchase.

### How It Works

1. A customer creates a demo site using the `[iwp_site_creator]` shortcode and enters their email address
2. The site is stored in the database as a "demo" site with the email recorded
3. Later, the customer decides to purchase and completes checkout on your WooCommerce store
4. The plugin matches the billing email on the order to the stored demo site
5. The demo site is converted to a paid site and linked to the order and customer account
6. The demo helper plugin on the site is automatically disabled
7. An order note documents the conversion

### What the Customer Gets

- They keep the exact same site URL and all the content they set up during the demo
- No data is lost and no migration is needed
- The "Converted from Demo" badge appears on their order page

### Important Notes

- Email matching is exact: the email used during demo creation must match the billing email on the order
- If a customer creates multiple demo sites with the same email, all of them are converted
- This feature is fully automatic and requires no configuration

## Go Live Page Redirect

### What It Does

Prevents customers who already have a paid site from seeing the demo creation page. This avoids confusion.

### How It Works

If a logged-in customer visits a page with the slug `go-live` or `launch-your-demo-site` and they already have at least one paid site, they are automatically redirected to their **My Account** page.

Guest visitors are not affected and can still access the page normally.

### Configuration

This works automatically with the page slugs `go-live` and `launch-your-demo-site`. No settings are needed.

## Delay Customer Credentials

### Full Workflow

This feature gives you control over when customers receive their site login details.

1. Enable **Delay Customer Credentials** in **InstaWP > Settings > General Settings**
2. When a site is created from an order, credentials are hidden from the customer
3. The customer sees their site URL but the username and password show "Your site is being prepared"
4. Order confirmation emails also show the "being prepared" message
5. You review the site, set up content, or make any needed customizations
6. Go to **InstaWP > Sites**, find the site, and click **Send Credentials**
7. The customer receives an email with their full credentials and a Magic Login link
8. Their My Account order page now shows the full credentials

See [Managing Sites](managing-sites.md) for details on the Send Credentials action.

## Magic Login

### How It Works

InstaWP provides a secure auto-login token for each site. The plugin uses this token to create one-click login links that take customers directly into their WordPress admin dashboard without entering a username and password.

### Where It Appears

- **Admin Sites table**: "Magic Login" row action
- **Customer order pages**: "Magic Login" button on site cards
- **Customer emails**: "Magic Login" button in HTML emails
- **My Account dashboard**: "Magic Login" button on site cards

### Fallback

When the auto-login token is not available (which can happen if the site is still being created), an **Admin Login** button links to the site's standard `/wp-admin` login page instead.

## GitHub Auto-Updater

The plugin checks for new versions on GitHub automatically. When a new version is available, a standard WordPress update notification appears on the **Plugins** page.

This works out of the box with no API tokens or settings needed. The check runs at most once every 15 minutes.
