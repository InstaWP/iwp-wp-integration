<?php
/**
 * Admin page to run database migration
 * Visit: /wp-admin/admin.php?page=iwp-migrate-db
 */

// Add admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        null, // No parent menu - hidden page
        'IWP Database Migration',
        'IWP DB Migrate',
        'manage_options',
        'iwp-migrate-db',
        'iwp_run_migration_page'
    );
});

function iwp_run_migration_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'iwp_sites';

    echo '<div class="wrap">';
    echo '<h1>IWP Database Migration</h1>';

    if (isset($_GET['run']) && $_GET['run'] === '1' && check_admin_referer('iwp-migrate')) {
        echo '<div class="notice notice-info"><p>Running migration...</p></div>';

        // Check if column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'site_type'");

        if (empty($column_exists)) {
            echo '<p>Adding site_type column...</p>';

            // Add the column
            $result = $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN site_type VARCHAR(50) DEFAULT 'paid' AFTER status");

            if ($result !== false) {
                echo '<div class="notice notice-success"><p>✓ Column added successfully!</p></div>';

                // Add index
                echo '<p>Adding index for site_type...</p>';
                $result2 = $wpdb->query("CREATE INDEX idx_site_type ON {$table_name}(site_type)");

                if ($result2 !== false) {
                    echo '<div class="notice notice-success"><p>✓ Index added successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>✗ Failed to add index: ' . $wpdb->last_error . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>✗ Failed to add column: ' . $wpdb->last_error . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-success"><p>✓ Column already exists!</p></div>';
        }

        // Verify
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
        echo '<h2>Current Table Structure:</h2>';
        echo '<ul>';
        foreach ($columns as $column) {
            if ($column->Field === 'site_type') {
                echo '<li style="color: green; font-weight: bold;">✓ ' . $column->Field . ' (' . $column->Type . ')</li>';
            } else {
                echo '<li>' . $column->Field . ' (' . $column->Type . ')</li>';
            }
        }
        echo '</ul>';

        echo '<p><a href="' . admin_url('admin.php?page=instawp-integration') . '" class="button button-primary">Go to InstaWP Settings</a></p>';
    } else {
        // Check current status
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'site_type'");

        if (empty($column_exists)) {
            echo '<div class="notice notice-warning"><p><strong>Migration Required:</strong> The site_type column needs to be added to the database.</p></div>';
            echo '<p><a href="' . wp_nonce_url(admin_url('admin.php?page=iwp-migrate-db&run=1'), 'iwp-migrate') . '" class="button button-primary">Run Migration Now</a></p>';
        } else {
            echo '<div class="notice notice-success"><p>✓ Database is up to date! The site_type column exists.</p></div>';
            echo '<p><a href="' . admin_url('admin.php?page=instawp-integration') . '" class="button">Go to InstaWP Settings</a></p>';
        }
    }

    echo '</div>';
}
