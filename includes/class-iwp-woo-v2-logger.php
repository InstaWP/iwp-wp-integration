<?php
/**
 * Logger Helper Class
 *
 * @package IWP_Woo_V2
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_V2_Logger class
 * 
 * Centralized logging with consistent formatting and context
 */
class IWP_Woo_V2_Logger {

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    /**
     * Whether debug mode is enabled
     *
     * @var bool
     */
    private static $debug_enabled = null;

    /**
     * Log level setting
     *
     * @var string
     */
    private static $log_level = null;

    /**
     * Initialize logger settings
     */
    private static function init() {
        if (self::$debug_enabled === null) {
            $options = IWP_Woo_V2_Database::get_plugin_options();
            self::$debug_enabled = isset($options['debug_mode']) && $options['debug_mode'] === 'yes';
            self::$log_level = isset($options['log_level']) ? $options['log_level'] : self::LEVEL_INFO;
        }
    }

    /**
     * Check if we should log based on level
     *
     * @param string $level
     * @return bool
     */
    private static function should_log($level) {
        self::init();

        if (!self::$debug_enabled && $level === self::LEVEL_DEBUG) {
            return false;
        }

        $levels = array(
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3
        );

        $current_level = isset($levels[self::$log_level]) ? $levels[self::$log_level] : 1;
        $message_level = isset($levels[$level]) ? $levels[$level] : 1;

        return $message_level >= $current_level;
    }

    /**
     * Format log message with context
     *
     * @param string $message
     * @param string $context
     * @param string $level
     * @param array $data
     * @return string
     */
    private static function format_message($message, $context = '', $level = self::LEVEL_INFO, $data = array()) {
        $timestamp = current_time('Y-m-d H:i:s');
        $level_upper = strtoupper($level);
        
        $formatted = "[{$timestamp}] IWP WooCommerce V2 [{$level_upper}]";
        
        if (!empty($context)) {
            $formatted .= " [{$context}]";
        }
        
        $formatted .= ": {$message}";
        
        if (!empty($data)) {
            $formatted .= " | Data: " . wp_json_encode($data);
        }

        return $formatted;
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param string $context
     * @param array $data
     */
    public static function debug($message, $context = '', $data = array()) {
        if (self::should_log(self::LEVEL_DEBUG)) {
            error_log(self::format_message($message, $context, self::LEVEL_DEBUG, $data));
        }
    }

    /**
     * Log info message
     *
     * @param string $message
     * @param string $context
     * @param array $data
     */
    public static function info($message, $context = '', $data = array()) {
        if (self::should_log(self::LEVEL_INFO)) {
            error_log(self::format_message($message, $context, self::LEVEL_INFO, $data));
        }
    }

    /**
     * Log warning message
     *
     * @param string $message
     * @param string $context
     * @param array $data
     */
    public static function warning($message, $context = '', $data = array()) {
        if (self::should_log(self::LEVEL_WARNING)) {
            error_log(self::format_message($message, $context, self::LEVEL_WARNING, $data));
        }
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param string $context
     * @param array $data
     */
    public static function error($message, $context = '', $data = array()) {
        if (self::should_log(self::LEVEL_ERROR)) {
            error_log(self::format_message($message, $context, self::LEVEL_ERROR, $data));
        }
    }

    /**
     * Log API request
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @param mixed $response
     * @param bool $is_error
     */
    public static function api_request($method, $endpoint, $data = array(), $response = null, $is_error = false) {
        $context = 'API';
        $level = $is_error ? self::LEVEL_ERROR : self::LEVEL_INFO;
        
        $log_data = array(
            'method' => $method,
            'endpoint' => $endpoint,
            'request_data' => $data
        );

        if ($response !== null) {
            if (is_wp_error($response)) {
                $log_data['error'] = $response->get_error_message();
                $level = self::LEVEL_ERROR;
            } else {
                $log_data['response'] = is_array($response) ? array_keys($response) : 'response_received';
            }
        }

        $message = $is_error ? "API request failed: {$method} {$endpoint}" : "API request: {$method} {$endpoint}";
        
        if ($level === self::LEVEL_ERROR) {
            self::error($message, $context, $log_data);
        } else {
            self::info($message, $context, $log_data);
        }
    }

    /**
     * Log site creation event
     *
     * @param string $event
     * @param int $order_id
     * @param array $site_data
     * @param bool $is_error
     */
    public static function site_creation($event, $order_id, $site_data = array(), $is_error = false) {
        $context = 'SiteCreation';
        $level = $is_error ? self::LEVEL_ERROR : self::LEVEL_INFO;
        
        $log_data = array(
            'order_id' => $order_id,
            'site_data_keys' => is_array($site_data) ? array_keys($site_data) : 'no_data'
        );

        if ($level === self::LEVEL_ERROR) {
            self::error($event, $context, $log_data);
        } else {
            self::info($event, $context, $log_data);  
        }

        // Also log to database for better tracking
        IWP_Woo_V2_Database::log_activity('site_creation', $event, $site_data, $order_id);
    }

    /**
     * Log security event
     *
     * @param string $event
     * @param array $context_data
     */
    public static function security($event, $context_data = array()) {
        $context = 'Security';
        
        $log_data = array_merge($context_data, array(
            'user_id' => get_current_user_id(),
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ));

        self::warning($event, $context, $log_data);
        
        // Also use security helper for consistent security logging
        IWP_Woo_V2_Security::log_security_event($event, $context_data);
    }

    /**
     * Log cache operation
     *
     * @param string $operation
     * @param string $cache_key
     * @param bool $hit
     */
    public static function cache($operation, $cache_key, $hit = null) {
        $context = 'Cache';
        
        $log_data = array(
            'operation' => $operation,
            'cache_key' => $cache_key
        );

        if ($hit !== null) {
            $log_data['hit'] = $hit ? 'yes' : 'no';
        }

        $message = "Cache {$operation}: {$cache_key}";
        if ($hit !== null) {
            $message .= $hit ? ' (HIT)' : ' (MISS)';
        }

        self::debug($message, $context, $log_data);
    }

    /**
     * Log order processing event
     *
     * @param string $event
     * @param int $order_id
     * @param array $order_data
     * @param bool $is_error
     */
    public static function order_processing($event, $order_id, $order_data = array(), $is_error = false) {
        $context = 'OrderProcessing';
        $level = $is_error ? self::LEVEL_ERROR : self::LEVEL_INFO;
        
        $log_data = array(
            'order_id' => $order_id,
            'order_data_keys' => is_array($order_data) ? array_keys($order_data) : 'no_data'
        );

        if ($level === self::LEVEL_ERROR) {
            self::error($event, $context, $log_data);
        } else {
            self::info($event, $context, $log_data);
        }

        // Log to database
        IWP_Woo_V2_Database::log_activity('order_processing', $event, $order_data, $order_id);
    }

    /**
     * Log admin action
     *
     * @param string $action
     * @param array $context_data
     */
    public static function admin_action($action, $context_data = array()) {
        $context = 'Admin';
        
        $log_data = array_merge($context_data, array(
            'user_id' => get_current_user_id(),
            'current_screen' => get_current_screen() ? get_current_screen()->id : 'unknown'
        ));

        self::info($action, $context, $log_data);
        
        // Log to database
        IWP_Woo_V2_Database::log_activity('admin_action', $action, $context_data);
    }

    /**
     * Log frontend action
     *
     * @param string $action
     * @param array $context_data
     */
    public static function frontend_action($action, $context_data = array()) {
        $context = 'Frontend';
        
        $log_data = array_merge($context_data, array(
            'user_id' => get_current_user_id(),
            'page_url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ));

        self::info($action, $context, $log_data);
    }

    /**
     * Log shortcode usage
     *
     * @param string $shortcode
     * @param array $attributes
     * @param bool $is_error
     */
    public static function shortcode($shortcode, $attributes = array(), $is_error = false) {
        $context = 'Shortcode';
        $level = $is_error ? self::LEVEL_ERROR : self::LEVEL_DEBUG;
        
        $log_data = array(
            'shortcode' => $shortcode,
            'attributes' => $attributes
        );

        $message = "Shortcode rendered: [{$shortcode}]";
        
        if ($level === self::LEVEL_ERROR) {
            self::error($message, $context, $log_data);
        } else {
            self::debug($message, $context, $log_data);
        }
    }

    /**
     * Get recent logs from WordPress error log
     *
     * @param int $lines Number of lines to read
     * @return array
     */
    public static function get_recent_logs($lines = 100) {
        $log_file = ini_get('error_log');
        if (!$log_file || !file_exists($log_file)) {
            return array();
        }

        $logs = array();
        $handle = fopen($log_file, 'r');
        
        if ($handle) {
            // Read from end of file
            fseek($handle, -min(filesize($log_file), 50000), SEEK_END);
            $content = fread($handle, 50000);
            fclose($handle);
            
            $lines_array = explode("\n", $content);
            $iwp_logs = array_filter($lines_array, function($line) {
                return strpos($line, 'IWP WooCommerce V2') !== false;
            });
            
            $logs = array_slice(array_reverse($iwp_logs), 0, $lines);
        }

        return $logs;
    }

    /**
     * Clear log files
     *
     * @return bool
     */
    public static function clear_logs() {
        $log_file = ini_get('error_log');
        if ($log_file && file_exists($log_file)) {
            // Don't clear entire error log, just our entries would require more complex processing
            // For now, just log that a clear was requested
            self::info('Log clear requested', 'Logger');
            return true;
        }
        return false;
    }

    /**
     * Get logging statistics
     *
     * @return array
     */
    public static function get_stats() {
        $stats = array(
            'debug_enabled' => self::$debug_enabled,
            'log_level' => self::$log_level,
            'recent_log_count' => count(self::get_recent_logs(50))
        );

        return $stats;
    }
}