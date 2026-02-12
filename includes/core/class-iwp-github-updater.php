<?php
/**
 * GitHub Plugin Updater
 *
 * Checks GitHub releases for new versions and integrates with
 * WordPress's built-in plugin update system.
 *
 * @package IWP
 * @since 0.0.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class IWP_GitHub_Updater {

    /**
     * GitHub repository owner/name
     *
     * @var string
     */
    private $repo = 'InstaWP/iwp-wp-integration';

    /**
     * Plugin slug (directory/file)
     *
     * @var string
     */
    private $plugin_slug;

    /**
     * Current plugin version
     *
     * @var string
     */
    private $current_version;

    /**
     * Plugin basename
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Cached GitHub release data
     *
     * @var object|null
     */
    private $github_response = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin_slug    = 'iwp-wp-integration';
        $this->current_version = IWP_VERSION;
        $this->plugin_basename = IWP_PLUGIN_BASENAME;

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'post_install'), 10, 3);
    }

    /**
     * Fetch latest release data from GitHub API
     *
     * @return object|null
     */
    private function get_github_release() {
        if ($this->github_response !== null) {
            return $this->github_response;
        }

        // Check transient cache first (15 min)
        $cached = get_transient('iwp_github_release');
        if ($cached !== false) {
            $this->github_response = $cached;
            return $this->github_response;
        }

        $url = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
            'timeout' => 10,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->github_response = null;
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response));

        if (empty($body) || !isset($body->tag_name)) {
            $this->github_response = null;
            return null;
        }

        $this->github_response = $body;
        set_transient('iwp_github_release', $body, 15 * MINUTE_IN_SECONDS);

        return $this->github_response;
    }

    /**
     * Extract version number from tag (strip leading "v")
     *
     * @param string $tag
     * @return string
     */
    private function tag_to_version($tag) {
        return ltrim($tag, 'v');
    }

    /**
     * Find the plugin zip asset URL from release
     *
     * @param object $release
     * @return string
     */
    private function get_download_url($release) {
        // First look for a .zip asset (built by GitHub Actions)
        if (!empty($release->assets)) {
            foreach ($release->assets as $asset) {
                if (substr($asset->name, -4) === '.zip') {
                    return $asset->browser_download_url;
                }
            }
        }

        // Fall back to the source zipball
        return $release->zipball_url;
    }

    /**
     * Check for plugin updates
     *
     * @param object $transient
     * @return object
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release();

        if (!$release) {
            return $transient;
        }

        $remote_version = $this->tag_to_version($release->tag_name);

        if (version_compare($remote_version, $this->current_version, '>')) {
            $plugin_data = new stdClass();
            $plugin_data->slug        = $this->plugin_slug;
            $plugin_data->plugin      = $this->plugin_basename;
            $plugin_data->new_version = $remote_version;
            $plugin_data->url         = 'https://github.com/' . $this->repo;
            $plugin_data->package     = $this->get_download_url($release);
            $plugin_data->tested      = '6.7';
            $plugin_data->requires_php = '7.4';

            $transient->response[$this->plugin_basename] = $plugin_data;
        }

        return $transient;
    }

    /**
     * Provide plugin information for the update details modal
     *
     * @param false|object|array $result
     * @param string $action
     * @param object $args
     * @return false|object
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $release = $this->get_github_release();
        if (!$release) {
            return $result;
        }

        $info = new stdClass();
        $info->name          = 'InstaWP Integration';
        $info->slug          = $this->plugin_slug;
        $info->version       = $this->tag_to_version($release->tag_name);
        $info->author        = '<a href="https://instawp.com">InstaWP</a>';
        $info->homepage      = 'https://github.com/' . $this->repo;
        $info->requires      = '5.0';
        $info->requires_php  = '7.4';
        $info->tested        = '6.7';
        $info->downloaded    = 0;
        $info->last_updated  = $release->published_at;
        $info->download_link = $this->get_download_url($release);

        // Use release body as changelog (markdown)
        $info->sections = array(
            'description' => 'InstaWP Integration plugin for WordPress and WooCommerce.',
            'changelog'   => nl2br(esc_html($release->body)),
        );

        return $info;
    }

    /**
     * Rename the extracted folder to match the plugin slug after upgrade
     *
     * @param bool $response
     * @param array $hook_extra
     * @param array $result
     * @return array
     */
    public function post_install($response, $hook_extra, $result) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $result;
        }

        global $wp_filesystem;

        $install_dir = plugin_dir_path(IWP_PLUGIN_FILE);
        $wp_filesystem->move($result['destination'], $install_dir);
        $result['destination'] = $install_dir;

        // Clear update transient so WP re-checks
        delete_transient('iwp_github_release');

        return $result;
    }
}
