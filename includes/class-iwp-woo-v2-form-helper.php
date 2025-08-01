<?php
/**
 * Form Helper Class
 *
 * @package IWP_Woo_V2
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * IWP_Woo_V2_Form_Helper class
 * 
 * Centralized form rendering and HTML generation
 */
class IWP_Woo_V2_Form_Helper {

    /**
     * Render form field
     *
     * @param string $type Field type
     * @param array $args Field arguments
     * @return string
     */
    public static function render_field($type, $args = array()) {
        $defaults = array(
            'id' => '',
            'name' => '',
            'value' => '',
            'label' => '',
            'description' => '',
            'placeholder' => '',
            'class' => '',
            'required' => false,
            'options' => array()
        );

        $args = wp_parse_args($args, $defaults);
        
        $output = '';
        
        // Start field wrapper
        $output .= '<div class="iwp-form-field iwp-form-field-' . esc_attr($type) . '">';
        
        // Add label
        if (!empty($args['label'])) {
            $required_star = $args['required'] ? ' <span class="required">*</span>' : '';
            $output .= '<label for="' . esc_attr($args['id']) . '" class="iwp-form-label">';
            $output .= esc_html($args['label']) . $required_star;
            $output .= '</label>';
        }
        
        // Render field based on type
        switch ($type) {
            case 'text':
            case 'email':
            case 'password':
            case 'url':
                $output .= self::render_input_field($type, $args);
                break;
            case 'textarea':
                $output .= self::render_textarea_field($args);
                break;
            case 'select':
                $output .= self::render_select_field($args);
                break;
            case 'checkbox':
                $output .= self::render_checkbox_field($args);
                break;
            case 'radio':
                $output .= self::render_radio_field($args);
                break;
            case 'hidden':
                $output .= self::render_hidden_field($args);
                break;
            default:
                $output .= self::render_input_field('text', $args);
        }
        
        // Add description
        if (!empty($args['description'])) {
            $output .= '<p class="iwp-form-description">' . wp_kses_post($args['description']) . '</p>';
        }
        
        // End field wrapper
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Render input field
     *
     * @param string $type
     * @param array $args
     * @return string
     */
    private static function render_input_field($type, $args) {
        $attributes = array(
            'type' => $type,
            'id' => $args['id'],
            'name' => $args['name'],
            'value' => $args['value'],
            'class' => 'iwp-form-input ' . $args['class'],
            'placeholder' => $args['placeholder']
        );

        if ($args['required']) {
            $attributes['required'] = 'required';
        }

        return '<input ' . self::build_attributes($attributes) . ' />';
    }

    /**
     * Render textarea field
     *
     * @param array $args
     * @return string
     */
    private static function render_textarea_field($args) {
        $attributes = array(
            'id' => $args['id'],
            'name' => $args['name'],
            'class' => 'iwp-form-textarea ' . $args['class'],
            'placeholder' => $args['placeholder'],
            'rows' => isset($args['rows']) ? $args['rows'] : 4,
            'cols' => isset($args['cols']) ? $args['cols'] : 50
        );

        if ($args['required']) {
            $attributes['required'] = 'required';
        }

        return '<textarea ' . self::build_attributes($attributes) . '>' . esc_textarea($args['value']) . '</textarea>';
    }

    /**
     * Render select field
     *
     * @param array $args
     * @return string
     */
    private static function render_select_field($args) {
        $attributes = array(
            'id' => $args['id'],
            'name' => $args['name'],
            'class' => 'iwp-form-select ' . $args['class']
        );

        if ($args['required']) {
            $attributes['required'] = 'required';
        }

        $output = '<select ' . self::build_attributes($attributes) . '>';
        
        foreach ($args['options'] as $value => $label) {
            $selected = selected($args['value'], $value, false);
            $output .= '<option value="' . esc_attr($value) . '"' . $selected . '>';
            $output .= esc_html($label);
            $output .= '</option>';
        }
        
        $output .= '</select>';
        
        return $output;
    }

    /**
     * Render checkbox field
     *
     * @param array $args
     * @return string
     */
    private static function render_checkbox_field($args) {
        $attributes = array(
            'type' => 'checkbox',
            'id' => $args['id'],
            'name' => $args['name'],
            'value' => isset($args['checkbox_value']) ? $args['checkbox_value'] : '1',
            'class' => 'iwp-form-checkbox ' . $args['class']
        );

        if ($args['value']) {
            $attributes['checked'] = 'checked';
        }

        return '<input ' . self::build_attributes($attributes) . ' />';
    }

    /**
     * Render radio field
     *
     * @param array $args
     * @return string
     */
    private static function render_radio_field($args) {
        $output = '';
        
        foreach ($args['options'] as $value => $label) {
            $attributes = array(
                'type' => 'radio',
                'id' => $args['id'] . '_' . $value,
                'name' => $args['name'],
                'value' => $value,
                'class' => 'iwp-form-radio ' . $args['class']
            );

            if ($args['value'] == $value) {
                $attributes['checked'] = 'checked';
            }

            $output .= '<label class="iwp-radio-label">';
            $output .= '<input ' . self::build_attributes($attributes) . ' />';
            $output .= ' ' . esc_html($label);
            $output .= '</label>';
        }
        
        return $output;
    }

    /**
     * Render hidden field
     *
     * @param array $args
     * @return string
     */
    private static function render_hidden_field($args) {
        $attributes = array(
            'type' => 'hidden',
            'id' => $args['id'],
            'name' => $args['name'],
            'value' => $args['value']
        );

        return '<input ' . self::build_attributes($attributes) . ' />';
    }

    /**
     * Build HTML attributes string
     *
     * @param array $attributes
     * @return string
     */
    private static function build_attributes($attributes) {
        $output = '';
        
        foreach ($attributes as $key => $value) {
            if ($value !== '' && $value !== null) {
                $output .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
            }
        }
        
        return $output;
    }

    /**
     * Render site card
     *
     * @param array $site_data
     * @param string $context
     * @param array $args
     * @return string
     */
    public static function render_site_card($site_data, $context = 'default', $args = array()) {
        $defaults = array(
            'show_actions' => true,
            'show_details' => true,
            'card_class' => '',
            'order_id' => null
        );

        $args = wp_parse_args($args, $defaults);
        
        $output = '';
        
        // Start card
        $card_classes = 'iwp-site-card iwp-site-card-' . esc_attr($context) . ' ' . $args['card_class'];
        $output .= '<div class="' . esc_attr($card_classes) . '">';
        
        // Card header
        $output .= '<div class="iwp-site-card-header">';
        $output .= '<h4 class="iwp-site-title">';
        $output .= isset($site_data['name']) ? esc_html($site_data['name']) : __('InstaWP Site', 'instawp-integration');
        $output .= '</h4>';
        
        // Status badge
        if (isset($site_data['status'])) {
            $status_class = 'iwp-status-' . esc_attr($site_data['status']);
            $status_text = self::get_status_text($site_data['status']);
            $output .= '<span class="iwp-status-badge ' . $status_class . '">' . esc_html($status_text) . '</span>';
        }
        
        $output .= '</div>';
        
        // Card content
        if ($args['show_details']) {
            $output .= '<div class="iwp-site-card-content">';
            
            // Site URL
            if (!empty($site_data['wp_url']) || !empty($site_data['site_url'])) {
                $site_url = !empty($site_data['wp_url']) ? $site_data['wp_url'] : $site_data['site_url'];
                $output .= '<div class="iwp-site-detail">';
                $output .= '<strong>' . __('Site URL:', 'instawp-integration') . '</strong> ';
                $output .= '<a href="' . esc_url($site_url) . '" target="_blank" rel="noopener noreferrer">';
                $output .= esc_html($site_url);
                $output .= '</a>';
                $output .= '</div>';
            }
            
            // Admin credentials
            if (!empty($site_data['wp_username'])) {
                $output .= '<div class="iwp-site-detail">';
                $output .= '<strong>' . __('Admin Username:', 'instawp-integration') . '</strong> ';
                $output .= '<code class="iwp-copyable" data-copy="' . esc_attr($site_data['wp_username']) . '">';
                $output .= esc_html($site_data['wp_username']);
                $output .= '</code>';
                $output .= ' <button type="button" class="iwp-copy-btn" data-copy="' . esc_attr($site_data['wp_username']) . '">';
                $output .= __('Copy', 'instawp-integration');
                $output .= '</button>';
                $output .= '</div>';
            }
            
            if (!empty($site_data['wp_password'])) {
                $output .= '<div class="iwp-site-detail">';
                $output .= '<strong>' . __('Admin Password:', 'instawp-integration') . '</strong> ';
                $output .= '<span class="iwp-password-field">';
                $output .= '<code class="iwp-password iwp-password-hidden">••••••••</code>';
                $output .= '<code class="iwp-password iwp-password-visible" style="display:none;">';
                $output .= esc_html($site_data['wp_password']);
                $output .= '</code>';
                $output .= '</span>';
                $output .= ' <button type="button" class="iwp-toggle-password">' . __('Show', 'instawp-integration') . '</button>';
                $output .= ' <button type="button" class="iwp-copy-btn" data-copy="' . esc_attr($site_data['wp_password']) . '">';
                $output .= __('Copy', 'instawp-integration');
                $output .= '</button>';
                $output .= '</div>';
            }
            
            // Creation date
            if (!empty($site_data['created_at'])) {
                $output .= '<div class="iwp-site-detail">';
                $output .= '<strong>' . __('Created:', 'instawp-integration') . '</strong> ';
                $output .= esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($site_data['created_at'])));
                $output .= '</div>';
            }
            
            $output .= '</div>';
        }
        
        // Card actions
        if ($args['show_actions']) {
            $output .= '<div class="iwp-site-card-actions">';
            
            // Visit site button
            if (!empty($site_data['wp_url']) || !empty($site_data['site_url'])) {
                $site_url = !empty($site_data['wp_url']) ? $site_data['wp_url'] : $site_data['site_url'];
                $output .= '<a href="' . esc_url($site_url) . '" target="_blank" class="iwp-btn iwp-btn-primary">';
                $output .= __('Visit Site', 'instawp-integration');
                $output .= '</a>';
            }
            
            // Admin login button
            if (!empty($site_data['s_hash'])) {
                $magic_login_url = 'https://app.instawp.io/wordpress-auto-login?site=' . urlencode($site_data['s_hash']);
                $output .= '<a href="' . esc_url($magic_login_url) . '" target="_blank" class="iwp-btn iwp-btn-secondary">';
                $output .= __('Magic Login', 'instawp-integration');
                $output .= '</a>';
            } elseif (!empty($site_data['wp_url']) || !empty($site_data['site_url'])) {
                $site_url = !empty($site_data['wp_url']) ? $site_data['wp_url'] : $site_data['site_url'];
                $admin_url = rtrim($site_url, '/') . '/wp-admin';
                $output .= '<a href="' . esc_url($admin_url) . '" target="_blank" class="iwp-btn iwp-btn-secondary">';
                $output .= __('Admin Login', 'instawp-integration');
                $output .= '</a>';
            }
            
            // Domain mapping button (for completed sites with site_id)
            if (isset($site_data['status']) && $site_data['status'] === 'completed' && !empty($site_data['site_id'])) {
                $output .= '<button type="button" class="iwp-btn iwp-btn-tertiary iwp-map-domain-btn" ';
                $output .= 'data-site-id="' . esc_attr($site_data['site_id']) . '" ';
                $output .= 'data-order-id="' . esc_attr($args['order_id']) . '">';
                $output .= __('Map Domain', 'instawp-integration');
                $output .= '</button>';
            }
            
            $output .= '</div>';
        }
        
        // End card
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Get status text for display
     *
     * @param string $status
     * @return string
     */
    private static function get_status_text($status) {
        $status_texts = array(
            'completed' => __('Ready', 'instawp-integration'),
            'progress' => __('Creating...', 'instawp-integration'),
            'failed' => __('Failed', 'instawp-integration'),
            'pending' => __('Pending', 'instawp-integration')
        );

        return isset($status_texts[$status]) ? $status_texts[$status] : ucfirst($status);
    }

    /**
     * Render admin notice
     *
     * @param string $message
     * @param string $type
     * @param bool $dismissible
     * @return string
     */
    public static function render_admin_notice($message, $type = 'info', $dismissible = true) {
        $classes = array(
            'notice',
            'notice-' . $type
        );

        if ($dismissible) {
            $classes[] = 'is-dismissible';
        }

        $output = '<div class="' . implode(' ', $classes) . '">';
        $output .= '<p>' . wp_kses_post($message) . '</p>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render loading spinner
     *
     * @param string $text
     * @return string
     */
    public static function render_spinner($text = '') {
        $output = '<div class="iwp-spinner-container">';
        $output .= '<div class="iwp-spinner"></div>';
        if (!empty($text)) {
            $output .= '<span class="iwp-spinner-text">' . esc_html($text) . '</span>';
        }
        $output .= '</div>';

        return $output;
    }

    /**
     * Render modal
     *
     * @param string $id
     * @param string $title
     * @param string $content
     * @param array $args
     * @return string
     */
    public static function render_modal($id, $title, $content, $args = array()) {
        $defaults = array(
            'size' => 'medium',
            'close_button' => true,
            'backdrop_close' => true
        );

        $args = wp_parse_args($args, $defaults);

        $output = '<div id="' . esc_attr($id) . '" class="iwp-modal iwp-modal-' . esc_attr($args['size']) . '" style="display:none;">';
        $output .= '<div class="iwp-modal-backdrop"></div>';
        $output .= '<div class="iwp-modal-content">';
        
        // Modal header
        $output .= '<div class="iwp-modal-header">';
        $output .= '<h3 class="iwp-modal-title">' . esc_html($title) . '</h3>';
        if ($args['close_button']) {
            $output .= '<button type="button" class="iwp-modal-close">&times;</button>';
        }
        $output .= '</div>';
        
        // Modal body
        $output .= '<div class="iwp-modal-body">';
        $output .= $content;
        $output .= '</div>';
        
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render progress bar
     *
     * @param int $percentage
     * @param string $text
     * @return string
     */
    public static function render_progress_bar($percentage, $text = '') {
        $percentage = max(0, min(100, $percentage));
        
        $output = '<div class="iwp-progress-container">';
        $output .= '<div class="iwp-progress-bar">';
        $output .= '<div class="iwp-progress-fill" style="width: ' . esc_attr($percentage) . '%"></div>';
        $output .= '</div>';
        if (!empty($text)) {
            $output .= '<div class="iwp-progress-text">' . esc_html($text) . '</div>';
        }
        $output .= '</div>';

        return $output;
    }
}