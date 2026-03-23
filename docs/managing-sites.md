# Managing Sites

The Sites page gives you a complete overview of all sites created through the plugin. Access it at **InstaWP > Sites** in your WordPress admin sidebar.

## Table Columns

| Column | Description |
|--------|-------------|
| Site URL | The live URL of the created site |
| Username | WordPress admin username for the site |
| Password | WordPress admin password (partially masked) |
| Plan | The InstaWP hosting plan assigned to the site |
| User | The WordPress user or guest who owns the site |
| Source | Where the site came from (WooCommerce, Shortcode, Demo to Paid) |
| Status | Current status: Active, Creating, Failed, or Expired |
| Created | Date and time the site was created |

## Filtering Sites

### Status Filters

Click the status links above the table to filter:

- **All**: Every site in the system
- **Active**: Sites that are live and accessible
- **Creating**: Sites still being provisioned by InstaWP
- **Failed**: Sites where an error occurred during creation
- **Expired**: Temporary sites that have passed their expiry time

Each filter shows a count in parentheses.

### Source Filter

Use the dropdown above the table to filter by source: WooCommerce, Shortcode, or other sources.

### Search

Type in the search box to find sites by URL, username, user name, site ID, or order ID.

### Sorting

Click any column header to sort ascending or descending. The default sort order is newest first.

## Row Actions

Hover over a site row to reveal these actions:

### Visit Site

Opens the site URL in a new browser tab.

### Magic Login

One-click login to the site's WordPress admin dashboard. This uses InstaWP's auto-login feature and does not require entering a username or password. Only appears when the auto-login token is available.

### Admin Login

Direct link to the site's `/wp-admin` page. This is the fallback when Magic Login is not available.

### Send Credentials

Only visible when **Delay Customer Credentials** is enabled in settings and the site's credentials have not yet been released.

Clicking this action:

1. Sends an email to the customer with their username, password, site URL, and a Magic Login link
2. Marks the site as "credentials released"
3. Adds a note to the associated order
4. The customer can now see full credentials on their order page

After credentials are sent, this action is replaced with a green **Credentials Sent** label.

### Delete

Removes the site record from your database. You will be asked to confirm before the deletion proceeds. This also sends a request to the InstaWP API to delete the remote site.

## Badges

Some sites display colored badges:

- **Converted from Demo** (orange): This site was originally created as a demo via the shortcode and was later converted to a paid site when the customer purchased
- **Credentials Sent** (green): Login credentials have been released to the customer

## Pagination

The table shows 20 sites per page. Use the pagination controls at the bottom to navigate between pages.

## Tips

- Use the test order feature in **InstaWP > Settings > Testing & Development** to create sites for testing without processing a real payment
- Sites with "Creating" status will automatically update when the InstaWP task completes
- If a site is stuck in "Creating" status for more than 10 minutes, check the debug log for errors
