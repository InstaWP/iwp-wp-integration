# Demo Site Storage & Reconciliation - Migration Guide

## Version 0.0.3 Release Notes

### Overview
This release implements automatic demo site storage and reconciliation to WooCommerce orders upon payment.

### What's New

#### 1. Demo Site Storage
- All sites created via `[iwp_site_creator]` shortcode are now stored in the database with `site_type='demo'`
- Stores customer email for future reconciliation
- Supports both logged-in users and guests

#### 2. Automatic Reconciliation
- When a customer makes a purchase, the system automatically finds demo sites matching their email
- Converts demo sites to paid sites by updating `site_type='demo'` → `site_type='paid'`
- Associates the site with the order and customer account
- Automatically disables demo helper plugin

#### 3. Frontend Enhancements
- "Converted from Demo" badge displays in order details
- Go Live page automatically redirects users with paid sites to My Account

#### 4. Database Changes
- New column: `site_type` VARCHAR(50) DEFAULT 'paid'
- New index: `idx_site_type` for query performance

---

## Automatic Database Migration

### For Users Updating from 0.0.2 → 0.0.3

The database migration happens **automatically** when users update the plugin:

#### Migration Process
1. Plugin detects version change (stored `iwp_version` option < current `IWP_VERSION`)
2. Automatically runs `IWP_Installer::add_site_type_column()`
3. Adds `site_type` column to `wp_iwp_sites` table
4. Creates performance index
5. Updates version in database

#### What Happens
- **Existing sites**: Default to `site_type='paid'` (backward compatible)
- **New demo sites**: Get `site_type='demo'` automatically
- **No data loss**: All existing site data remains intact

#### Verification
Check the debug log (`/wp-content/debug.log`):
```
InstaWP Integration: Adding site_type column to database
InstaWP Integration: Successfully added site_type column
InstaWP Integration: Successfully added site_type index
```

#### Fallback Options
If automatic migration doesn't run for some reason:

**Option 1: Admin Notice**
- Users will see: "InstaWP Integration: Database update required"
- Click "Update Database" button

**Option 2: Manual URL**
- Navigate to: `/wp-admin/admin.php?page=iwp-migrate-db`
- Click "Run Migration Now"

---

## Database Schema Changes

### Before (v0.0.2)
```sql
CREATE TABLE wp_iwp_sites (
    id bigint(20) unsigned NOT NULL auto_increment,
    site_id varchar(100) NOT NULL,
    ...
    status varchar(20) NOT NULL DEFAULT 'creating',
    task_id varchar(100) NULL,
    ...
);
```

### After (v0.0.3)
```sql
CREATE TABLE wp_iwp_sites (
    id bigint(20) unsigned NOT NULL auto_increment,
    site_id varchar(100) NOT NULL,
    ...
    status varchar(20) NOT NULL DEFAULT 'creating',
    site_type varchar(50) DEFAULT 'paid',          -- NEW
    task_id varchar(100) NULL,
    ...
    KEY idx_site_type (site_type)                  -- NEW INDEX
);
```

---

## Files Modified

### Core Changes
1. `includes/core/class-iwp-installer.php`
   - Added `site_type` column to table schema
   - Added migration method: `add_site_type_column()`
   - Added version `0.0.3` to `$db_updates` array

2. `includes/core/class-iwp-shortcode.php`
   - Added `store_demo_site_in_database()` method
   - Stores demo sites with `site_type='demo'`

3. `includes/core/class-iwp-sites-model.php`
   - Added `get_demo_sites_by_email()` - finds demos by email
   - Added `get_demo_sites_by_user()` - finds demos by user_id
   - Added `mark_expired_demos()` - marks expired demo sites
   - Updated `create()` to handle `site_type` field

### Integration Changes
4. `includes/integrations/woocommerce/class-iwp-woo-order-processor.php`
   - Added `reconcile_demo_sites_to_order()` method
   - Added `add_reconciled_site_to_order_meta()` method
   - Added `disable_demo_helper_for_site()` method
   - Calls reconciliation before processing new orders

### Frontend Changes
5. `includes/frontend/class-iwp-frontend.php`
   - Added demo badge display for reconciled sites

6. `assets/css/frontend.css`
   - Added `.iwp-demo-badge` styling

### New Files
7. `includes/frontend/class-iwp-golive-page.php` (NEW)
   - Redirects users with paid sites away from Go Live pages

8. `admin-migrate.php` (NEW)
   - Admin page for manual database migration

9. `includes/admin/class-iwp-db-update-notice.php` (NEW)
   - Shows admin notice when database needs updating

### Main Plugin File
10. `iwp-wp-integration.php`
    - Updated version to 0.0.3
    - Added automatic database update check
    - Loads new migration and notice classes

---

## Testing Checklist

### ✅ Fresh Installation (New Users)
- [x] Plugin activates without errors
- [x] Database tables created with `site_type` column
- [x] Demo sites can be created via shortcode
- [x] Sites stored with `site_type='demo'`

### ✅ Update from 0.0.2 (Existing Users)
- [x] Plugin updates without errors
- [x] Database migration runs automatically
- [x] Existing sites default to `site_type='paid'`
- [x] No data loss or corruption

### ✅ Demo Site Creation
- [x] Shortcode creates site successfully
- [x] Site stored in database with `site_type='demo'`
- [x] Email stored in `source_data` JSON field
- [x] Works for both logged-in and guest users

### ✅ Reconciliation
- [x] Demo site found by email on purchase
- [x] `site_type` updated from 'demo' to 'paid'
- [x] `order_id` and `user_id` populated
- [x] Demo helper plugin disabled
- [x] Order note added documenting conversion
- [x] Site appears in order details

### ✅ Frontend Display
- [x] "Converted from Demo" badge shows
- [x] Badge styled correctly (orange background)
- [x] Site credentials accessible

### ✅ Go Live Redirect
- [x] Users with demo sites can access Go Live page
- [x] Users with paid sites redirected to My Account
- [x] Guests can access Go Live page

---

## Rollback Plan

If issues occur, you can rollback:

### Option 1: Revert Plugin Version
```bash
# Switch back to version 0.0.2
git checkout v0.0.2
```

### Option 2: Manual Database Cleanup (if needed)
```sql
-- Remove the column (only if necessary)
ALTER TABLE wp_iwp_sites DROP COLUMN site_type;
DROP INDEX idx_site_type ON wp_iwp_sites;

-- Update version back
UPDATE wp_options SET option_value = '0.0.2' WHERE option_name = 'iwp_version';
```

### Option 3: Keep Column, Disable Features
The column won't hurt anything if you disable the features. Just revert the code changes but keep the database schema.

---

## Support & Troubleshooting

### Common Issues

**Issue 1: "Unknown column 'site_type'"**
- **Cause**: Migration didn't run
- **Fix**: Visit `/wp-admin/admin.php?page=iwp-migrate-db` and click "Run Migration Now"

**Issue 2: Demo sites not reconciling**
- **Check**: Email must match exactly (case-sensitive)
- **Check**: Site has `site_type='demo'` and `order_id IS NULL`
- **Debug**: Check error log for reconciliation messages

**Issue 3: Admin notice won't dismiss**
- **Cause**: Database not actually updated
- **Fix**: Run migration manually via admin page

### Debug Logging

Enable WordPress debug mode in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Look for these log entries:
```
[INFO] [shortcode]: Demo site stored in database
[INFO] [order-processor]: Demo site reconciled to order
[INFO] [order-processor]: Demo helper disabled for converted site
```

---

## Future Enhancements

Potential features for future versions:

1. **Bulk Demo Cleanup**: Admin tool to delete expired demo sites
2. **Demo Site Dashboard**: Separate admin page showing all demo sites
3. **Email Notifications**: Notify users when demo converts to paid
4. **Analytics**: Track demo → paid conversion rates
5. **Trial Periods**: Support for time-limited demo sites
6. **Demo Limitations**: Different features for demo vs paid sites

---

## Version History

### 0.0.3 (Current)
- Added demo site storage and reconciliation
- Added `site_type` column and automatic migration
- Added Go Live page redirect logic
- Added demo badge display

### 0.0.2
- Previous stable version
- No demo site tracking

### 0.0.1
- Initial release

---

## Contact & Support

For questions or issues with this migration:
- Check debug logs first: `/wp-content/debug.log`
- Visit manual migration page: `/wp-admin/admin.php?page=iwp-migrate-db`
- Review this guide: `/wp-content/plugins/iwp-wp-integration/MIGRATION-GUIDE.md`
