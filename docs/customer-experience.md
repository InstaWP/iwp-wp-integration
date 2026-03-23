# Customer Experience

This page describes what your customers see after purchasing a product that creates an InstaWP site. Understanding this helps you set expectations and provide support.

## Thank You Page

Immediately after checkout, a **Your Site Details** section appears below the order table on the thank you page.

If the site is ready:

- Site URL (clickable, opens in a new tab)
- Admin username with a copy button
- Admin password (hidden by default, with a show/hide toggle and copy button)
- **Magic Login** button for one-click admin access
- **Admin Login** button as a fallback

If the site is still being created:

- A "Creating..." status message
- A **Refresh Status** button to check for updates

If credentials are delayed (the **Delay Customer Credentials** setting is enabled):

- The site URL is visible
- A yellow notice says "Your site is being prepared"
- Username and password are hidden until you release them from the admin Sites table

## My Account - Order View

When a customer views a specific order in **My Account > Orders**, a **Sites** section appears below the order details table. It shows the same information as the thank you page.

Additional features on this page:

- **Map Domain** button for adding a custom domain (see [Advanced Features](advanced-features.md))
- A list of any domains already mapped to the site
- Status badges (see below)

## My Account - Dashboard

The main My Account dashboard shows a **Your Sites** section listing all sites across all orders. Each site card includes:

- Site name and URL
- Status indicator
- **Visit Site** button
- **Magic Login** button (when available)
- A link to the associated order for full details

## Email Notifications

Site details are included in these WooCommerce order emails:

- Customer Completed Order
- Customer Invoice
- Customer Processing Order

### HTML Emails

Emails include a styled card with the site URL, username, password, and a Magic Login button.

### Plain Text Emails

The same information is included as labeled text lines.

### When Credentials Are Delayed

If the **Delay Customer Credentials** setting is enabled, emails show "Your site is being prepared" instead of login credentials. Once you release credentials via the Sites table, the customer receives a separate email with the full details.

## Status Indicators

Customers see these status indicators on their site cards:

- **Ready** (green): The site is live and accessible
- **Creating** (spinner/loading): The site is being provisioned. The customer can refresh to check progress.
- **Failed** (red): An error occurred. A message asks the customer to contact support.

## Badges

- **Converted from Demo** (orange): The site was originally a demo and was converted to paid when the customer purchased
- **Plan Upgraded** (blue): The site's plan was upgraded rather than creating a new site

## Credential Security

- Passwords are hidden by default and require clicking "Show" to reveal
- Both username and password have copy-to-clipboard buttons
- Magic Login tokens provide access without exposing credentials directly
- If you use the delay credentials feature, credentials are not shown until you explicitly release them
