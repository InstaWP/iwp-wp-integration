<?php
/**
 * Go Live Page Handler
 * Redirects to dashboard if site is already paid
 *
 * @package IWP
 * @since 0.0.1
 */

defined('ABSPATH') || exit;

class IWP_GoLive_Page {

    public function __construct() {
        // Hook into template_redirect to check site status
        add_action('template_redirect', array($this, 'check_golive_page_access'));
    }

    /**
     * Check if user is on Go Live page and redirect if site is paid
     */
    public function check_golive_page_access() {
        // Only run on the Go Live page (adjust page slug as needed)
        if (!is_page('go-live') && !is_page('launch-your-demo-site')) {
            return;
        }

        // Get current user
        $user_id = get_current_user_id();
        if (!$user_id) {
            return; // Let guests access the page
        }

        // Check if user has any paid sites
        IWP_Sites_Model::init();
        $user_sites = IWP_Sites_Model::get_by_user_id($user_id);

        $has_paid_site = false;
        foreach ($user_sites as $site) {
            if ($site->site_type === 'paid') {
                $has_paid_site = true;
                break;
            }
        }

        // If user has a paid site, redirect to dashboard
        if ($has_paid_site) {
            if (function_exists('wc_get_page_permalink')) {
                wp_safe_redirect(wc_get_page_permalink('myaccount'));
            } else {
                wp_safe_redirect(home_url('/my-account'));
            }
            exit;
        }
    }
}
