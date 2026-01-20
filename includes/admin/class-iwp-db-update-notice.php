<?php
/**
 * Database Update Notice
 * Shows admin notice when database needs updating
 *
 * @package IWP
 * @since 0.0.3
 */

defined('ABSPATH') || exit;

class IWP_DB_Update_Notice {

    public function __construct() {
        add_action('admin_notices', array($this, 'show_update_notice'));
    }

    /**
     * Show admin notice if database needs updating
     */
    public function show_update_notice() {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if update is needed
        if (!class_exists('IWP_Installer')) {
            return;
        }

        if (!IWP_Installer::needs_database_update()) {
            return;
        }

        // Show notice
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>InstaWP Integration:</strong> Database update required.
                <a href="<?php echo esc_url(admin_url('admin.php?page=iwp-migrate-db')); ?>" class="button button-primary" style="margin-left: 10px;">
                    Update Database
                </a>
            </p>
        </div>
        <?php
    }
}

new IWP_DB_Update_Notice();
