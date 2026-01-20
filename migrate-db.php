<?php
/**
 * Database Migration Script
 * Run this to add the site_type column
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

global $wpdb;

$table_name = $wpdb->prefix . 'iwp_sites';

echo "Starting database migration...\n";

// Check if column exists
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'site_type'");

if (empty($column_exists)) {
    echo "Adding site_type column...\n";

    // Add the column
    $result = $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN site_type VARCHAR(50) DEFAULT 'paid' AFTER status");

    if ($result !== false) {
        echo "✓ Column added successfully!\n";

        // Add index
        echo "Adding index for site_type...\n";
        $result2 = $wpdb->query("CREATE INDEX idx_site_type ON {$table_name}(site_type)");

        if ($result2 !== false) {
            echo "✓ Index added successfully!\n";
        } else {
            echo "✗ Failed to add index: " . $wpdb->last_error . "\n";
        }
    } else {
        echo "✗ Failed to add column: " . $wpdb->last_error . "\n";
    }
} else {
    echo "✓ Column already exists!\n";
}

// Verify
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
echo "\nCurrent table structure:\n";
foreach ($columns as $column) {
    echo "  - {$column->Field} ({$column->Type})\n";
}

echo "\n✓ Migration complete!\n";
