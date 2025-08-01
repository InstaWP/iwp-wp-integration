/**
 * Admin JavaScript for IWP WooCommerce Integration v2
 *
 * @package IWP_Woo_V2
 * @since 2.0.0
 */

(function($) {
    'use strict';

    var IWP_Woo_V2_Admin = {
        
        /**
         * Initialize the admin functionality
         */
        init: function() {
            console.log('IWP_Woo_V2_Admin.init() called');
            this.bindEvents();
            this.initComponents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Settings form submission
            $('#iwp-woo-v2-settings-form').on('submit', this.handleSettingsSubmit);
            
            // Reset settings confirmation
            $('.iwp-woo-v2-reset-settings').on('click', this.handleResetSettings);
            
            // Toggle dependent fields
            $('input[name="iwp_woo_v2_options[debug_mode]"]').on('change', this.toggleDebugFields);
            
            // AJAX actions
            $('.iwp-woo-v2-ajax-action').on('click', this.handleAjaxAction);
            
            // Templates refresh
            $('#iwp-refresh-templates').on('click', this.handleRefreshTemplates);
            
            // Plans refresh
            $('#iwp-refresh-plans').on('click', this.handleRefreshPlans);
            
            // Cache management
            $('#iwp-clear-transients').on('click', this.handleClearTransients);
            $('#iwp-warm-cache').on('click', this.handleWarmCache);
            
            // Site status refresh
            $('#iwp-refresh-site-status').on('click', this.handleRefreshSiteStatus);
            
            // Test order creation - bind events with validation
            var self = this;
            $('#iwp-test-product-select').on('change', function() { 
                self.handleProductSelectChange(); 
                self.validateTestOrderForm(); 
            });
            $('#iwp-create-test-order').on('click', function(e) { 
                console.log('Button clicked - basic handler');
                self.handleCreateTestOrder(e); 
            });
            
            // Fallback: Direct jQuery handler as backup
            $(document).on('click', '#iwp-create-test-order', function(e) {
                console.log('Button clicked - document handler');
                self.handleCreateTestOrder(e);
            });
            $('input[name="iwp-customer-type"]').on('change', function() { 
                self.handleCustomerTypeChange(); 
                self.validateTestOrderForm(); 
            });
            $('#iwp-test-customer-select').on('change', function() { 
                self.handleCustomerSelectChange(); 
                self.validateTestOrderForm(); 
            });
            
            // Add validation triggers for form fields that don't have handlers
            $('#iwp-test-customer-first-name, #iwp-test-customer-last-name, #iwp-test-customer-email').on('input change', function() { 
                self.validateTestOrderForm(); 
            });
            $('#iwp-new-user-username, #iwp-new-user-first-name, #iwp-new-user-last-name, #iwp-new-user-email').on('input change', function() { 
                self.validateTestOrderForm(); 
            });
            
            // Form validation
            $('#iwp-woo-v2-settings-form input, #iwp-woo-v2-settings-form select').on('change', this.validateField);
        },

        /**
         * Initialize components
         */
        initComponents: function() {
            // Initialize tooltips
            this.initTooltips();
            
            // Initialize tabs if present
            this.initTabs();
            
            // Set initial field states
            this.toggleAPIFields();
            this.toggleDebugFields();
            
            // Initialize test order form
            this.initTestOrderForm();
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('.iwp-woo-v2-tooltip').each(function() {
                var $tooltip = $(this);
                var title = $tooltip.attr('title');
                
                if (title) {
                    $tooltip.removeAttr('title');
                    $tooltip.on('mouseenter', function() {
                        $('<div class="iwp-woo-v2-tooltip-content">' + title + '</div>')
                            .appendTo('body')
                            .fadeIn(200);
                    }).on('mouseleave', function() {
                        $('.iwp-woo-v2-tooltip-content').fadeOut(200, function() {
                            $(this).remove();
                        });
                    });
                }
            });
        },

        /**
         * Initialize tabs
         */
        initTabs: function() {
            var $tabs = $('.iwp-woo-v2-tabs');
            
            if ($tabs.length) {
                $tabs.find('.nav-tab').on('click', function(e) {
                    e.preventDefault();
                    
                    var $tab = $(this);
                    var target = $tab.attr('href');
                    
                    // Update active tab
                    $tabs.find('.nav-tab').removeClass('nav-tab-active');
                    $tab.addClass('nav-tab-active');
                    
                    // Update active content
                    $('.iwp-woo-v2-tab-content').hide();
                    $(target).show();
                });
                
                // Show first tab by default
                $tabs.find('.nav-tab').first().click();
            }
        },

        /**
         * Handle settings form submission
         */
        handleSettingsSubmit: function(e) {
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            
            // Show loading state
            $submitButton.prop('disabled', true);
            $submitButton.val(iwp_woo_v2_admin.strings.saving || 'Saving...');
            
            // Add loading spinner
            $('<span class="iwp-woo-v2-loading"></span>').insertAfter($submitButton);
        },

        /**
         * Handle reset settings
         */
        handleResetSettings: function(e) {
            if (!confirm(iwp_woo_v2_admin.strings.confirm_reset)) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            var $button = $(this);
            $button.prop('disabled', true);
            $('<span class="iwp-woo-v2-loading"></span>').insertAfter($button);
        },

        /**
         * Toggle debug fields
         */
        toggleDebugFields: function() {
            var $debugMode = $('input[name="iwp_woo_v2_options[debug_mode]"]');
            var $debugFields = $('.iwp-woo-v2-debug-field');
            
            if ($debugMode.is(':checked')) {
                $debugFields.show();
            } else {
                $debugFields.hide();
            }
        },

        /**
         * Handle AJAX actions
         */
        handleAjaxAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var action = $button.data('action');
            
            if (!action) {
                return;
            }
            
            // Disable button and show loading
            $button.prop('disabled', true);
            var originalText = $button.text();
            $button.text(iwp_woo_v2_admin.strings.loading || 'Loading...');
            
            $.ajax({
                url: iwp_woo_v2_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_woo_v2_' + action,
                    nonce: iwp_woo_v2_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        IWP_Woo_V2_Admin.showNotice(response.data.message, 'success');
                    } else {
                        IWP_Woo_V2_Admin.showNotice(response.data.message || iwp_woo_v2_admin.strings.error_occurred, 'error');
                    }
                },
                error: function() {
                    IWP_Woo_V2_Admin.showNotice(iwp_woo_v2_admin.strings.error_occurred, 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Validate field
         */
        validateField: function() {
            var $field = $(this);
            var fieldName = $field.attr('name');
            var fieldValue = $field.val();
            
            // Remove existing validation messages
            $field.siblings('.iwp-woo-v2-validation-message').remove();
            
            // Basic validation
            if ($field.prop('required') && !fieldValue) {
                IWP_Woo_V2_Admin.showFieldError($field, 'This field is required.');
                return false;
            }
            
            // Email validation
            if ($field.attr('type') === 'email' && fieldValue) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(fieldValue)) {
                    IWP_Woo_V2_Admin.showFieldError($field, 'Please enter a valid email address.');
                    return false;
                }
            }
            
            // URL validation
            if ($field.attr('type') === 'url' && fieldValue) {
                try {
                    new URL(fieldValue);
                } catch (e) {
                    IWP_Woo_V2_Admin.showFieldError($field, 'Please enter a valid URL.');
                    return false;
                }
            }
            
            return true;
        },

        /**
         * Show field error
         */
        showFieldError: function($field, message) {
            $field.addClass('iwp-woo-v2-field-error');
            $('<div class="iwp-woo-v2-validation-message error">' + message + '</div>').insertAfter($field);
        },

        /**
         * Show notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible iwp-woo-v2-notice">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss"></button>' +
                '</div>');
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },

        /**
         * Utility: Get form data as object
         */
        getFormData: function($form) {
            var formData = {};
            $form.find('input, select, textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value = $field.val();
                
                if ($field.is(':checkbox')) {
                    value = $field.is(':checked') ? 'yes' : 'no';
                }
                
                if (name) {
                    formData[name] = value;
                }
            });
            
            return formData;
        },

        /**
         * Utility: Show loading overlay
         */
        showLoading: function() {
            if ($('.iwp-woo-v2-loading-overlay').length === 0) {
                $('<div class="iwp-woo-v2-loading-overlay"><div class="iwp-woo-v2-loading"></div></div>')
                    .appendTo('body');
            }
        },

        /**
         * Utility: Hide loading overlay
         */
        hideLoading: function() {
            $('.iwp-woo-v2-loading-overlay').remove();
        },

        /**
         * Handle refresh templates
         */
        handleRefreshTemplates: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true);
            $button.text('Refreshing...');
            
            $.ajax({
                url: iwp_woo_v2_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_woo_v2_refresh_templates',
                    nonce: iwp_woo_v2_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        IWP_Woo_V2_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated templates
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        IWP_Woo_V2_Admin.showNotice(response.data.message || 'An error occurred while refreshing templates.', 'error');
                    }
                },
                error: function() {
                    IWP_Woo_V2_Admin.showNotice('An error occurred while refreshing templates.', 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Handle refresh plans
         */
        handleRefreshPlans: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true);
            $button.text('Refreshing...');
            
            $.ajax({
                url: iwp_woo_v2_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_woo_v2_refresh_plans',
                    nonce: iwp_woo_v2_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        IWP_Woo_V2_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated plans
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        IWP_Woo_V2_Admin.showNotice(response.data.message || 'An error occurred while refreshing plans.', 'error');
                    }
                },
                error: function() {
                    IWP_Woo_V2_Admin.showNotice('An error occurred while refreshing plans.', 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Handle clear transients
         */
        handleClearTransients: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Confirm action
            if (!confirm('Are you sure you want to clear all cached data? This will force fresh API calls on the next page load.')) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true);
            $button.text('Clearing...');
            
            $.ajax({
                url: iwp_woo_v2_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_clear_transients',
                    nonce: iwp_woo_v2_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        IWP_Woo_V2_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated cache status
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        IWP_Woo_V2_Admin.showNotice(response.data.message || 'An error occurred while clearing transients.', 'error');
                    }
                },
                error: function() {
                    IWP_Woo_V2_Admin.showNotice('An error occurred while clearing transients.', 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Handle cache warmup
         */
        handleWarmCache: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true);
            $button.text('Warming up...');
            
            $.ajax({
                url: iwp_woo_v2_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_warm_cache',
                    nonce: iwp_woo_v2_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        IWP_Woo_V2_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated cache status
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        IWP_Woo_V2_Admin.showNotice(response.data.message || 'An error occurred while warming up cache.', 'error');
                    }
                },
                error: function() {
                    IWP_Woo_V2_Admin.showNotice('An error occurred while warming up cache.', 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Handle site status refresh
         */
        handleRefreshSiteStatus: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true);
            $button.text('Refreshing...');
            
            $.ajax({
                url: iwp_woo_v2_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_refresh_site_status',
                    nonce: iwp_woo_v2_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        IWP_Woo_V2_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated status
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        IWP_Woo_V2_Admin.showNotice(response.data.message || 'An error occurred while refreshing site status.', 'error');
                    }
                },
                error: function() {
                    IWP_Woo_V2_Admin.showNotice('An error occurred while refreshing site status.', 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Handle product selection change for test orders
         */
        handleProductSelectChange: function() {
            // Product selection changed - validation handled by main event handler
        },

        /**
         * Handle customer type change for test orders
         */
        handleCustomerTypeChange: function() {
            var customerType = $('input[name="iwp-customer-type"]:checked').val();
            
            // Hide all customer detail sections
            $('#iwp-guest-details, #iwp-new-user-details').hide();
            $('#iwp-test-customer-select').closest('div').hide();
            
            // Show relevant section based on selected type
            switch (customerType) {
                case 'existing':
                    $('#iwp-test-customer-select').closest('div').show();
                    break;
                case 'guest':
                    $('#iwp-guest-details').show();
                    break;
                case 'new':
                    $('#iwp-new-user-details').show();
                    break;
            }
            
            // Validation handled by main event handler
        },

        /**
         * Handle customer selection change for test orders
         */
        handleCustomerSelectChange: function() {
            // Customer selection changed - validation handled by main event handler
        },

        /**
         * Initialize test order form
         */
        initTestOrderForm: function() {
            var self = this;
            
            // Enable the button by default
            $('#iwp-create-test-order').prop('disabled', false);
            
            // Use a small delay to ensure DOM is fully ready
            setTimeout(function() {
                // Debug: Check if form elements exist
                if (typeof console !== 'undefined' && console.log) {
                    console.log('Initializing test order form...', {
                        productSelect: $('#iwp-test-product-select').length,
                        customerTypeRadios: $('input[name="iwp-customer-type"]').length,
                        customerSelect: $('#iwp-test-customer-select').length,
                        createButton: $('#iwp-create-test-order').length
                    });
                }
                
                // Trigger initial customer type change to set up form
                if ($('input[name="iwp-customer-type"]:checked').length) {
                    $('input[name="iwp-customer-type"]:checked').trigger('change');
                } else {
                    // If no customer type is selected, select the first one and trigger change
                    $('input[name="iwp-customer-type"]:first').prop('checked', true).trigger('change');
                }
                
                // Ensure button stays enabled
                $('#iwp-create-test-order').prop('disabled', false);
            }, 100);
        },

        /**
         * Validate test order form
         */
        validateTestOrderForm: function() {
            var $button = $('#iwp-create-test-order');
            var $results = $('#iwp-test-order-results');
            
            // Enable button by default - let the actual form submission handle validation
            $button.prop('disabled', false);
            
            // Clear previous results
            $results.empty();
            
            // Optional: Still log validation state for debugging
            if (typeof console !== 'undefined' && console.log) {
                var productId = $('#iwp-test-product-select').val();
                var customerType = $('input[name="iwp-customer-type"]:checked').val();
                
                console.log('Test Order Form State:', {
                    productId: productId,
                    customerType: customerType,
                    buttonEnabled: true
                });
            }
        },

        /**
         * Handle test order creation
         */
        handleCreateTestOrder: function(e) {
            e.preventDefault();
            
            // Debug logging
            if (typeof console !== 'undefined' && console.log) {
                console.log('Create Test Order button clicked!');
            }
            
            var $button = $('#iwp-create-test-order'); // Use direct selector instead of $(this)
            var $status = $('#iwp-test-order-status');
            var $results = $('#iwp-test-order-results');
            var originalText = $button.text();
            
            // Get form data
            var productId = $('#iwp-test-product-select').val();
            var customerType = $('input[name="iwp-customer-type"]:checked').val();
            
            if (!productId) {
                IWP_Woo_V2_Admin.showNotice('Please select a product first.', 'error');
                return;
            }
            
            // Prepare data based on customer type
            var requestData = {
                action: 'iwp_create_test_order',
                nonce: iwp_woo_v2_admin.nonce,
                product_id: productId,
                customer_type: customerType
            };
            
            switch (customerType) {
                case 'existing':
                    var customerId = $('#iwp-test-customer-select').val();
                    if (!customerId) {
                        IWP_Woo_V2_Admin.showNotice('Please select a user.', 'error');
                        return;
                    }
                    requestData.customer_id = customerId;
                    break;
                    
                case 'guest':
                    var guestFirstName = $('#iwp-test-customer-first-name').val();
                    var guestLastName = $('#iwp-test-customer-last-name').val();
                    var guestEmail = $('#iwp-test-customer-email').val();
                    
                    if (!guestEmail || !guestFirstName || !guestLastName) {
                        IWP_Woo_V2_Admin.showNotice('Please fill in all guest customer details.', 'error');
                        return;
                    }
                    
                    requestData.customer_first_name = guestFirstName;
                    requestData.customer_last_name = guestLastName;
                    requestData.customer_email = guestEmail;
                    break;
                    
                case 'new':
                    var newUsername = $('#iwp-new-user-username').val();
                    var newFirstName = $('#iwp-new-user-first-name').val();
                    var newLastName = $('#iwp-new-user-last-name').val();
                    var newEmail = $('#iwp-new-user-email').val();
                    
                    if (!newUsername || !newEmail) {
                        IWP_Woo_V2_Admin.showNotice('Username and email are required for new users.', 'error');
                        return;
                    }
                    
                    requestData.new_user_username = newUsername;
                    requestData.new_user_first_name = newFirstName;
                    requestData.new_user_last_name = newLastName;
                    requestData.new_user_email = newEmail;
                    break;
            }
            
            // Show loading state
            $button.prop('disabled', true);
            $button.text('Creating Test Order...');
            $status.html('<span style="color: #0073aa;">Creating order...</span>');
            $results.empty();
            
            $.ajax({
                url: iwp_woo_v2_admin.ajax_url,
                type: 'POST',
                data: requestData,
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #46b450;">✓ Order created successfully!</span>');
                        
                        var customerInfo = '';
                        if (response.data.customer_type === 'existing') {
                            customerInfo = '<p><strong>Customer:</strong> Existing user (' + response.data.customer_email + ')</p>';
                        } else if (response.data.customer_type === 'new') {
                            customerInfo = '<p><strong>Customer:</strong> New user created (' + response.data.customer_email + ')</p>' +
                                         '<p><small><em>Login credentials have been sent to the user via email.</em></small></p>';
                        } else {
                            customerInfo = '<p><strong>Customer:</strong> Guest checkout (' + response.data.customer_email + ')</p>';
                        }
                        
                        var myAccountUrl = response.data.customer_id ? 
                            iwp_woo_v2_admin.site_url + '/my-account/?customer_id=' + response.data.customer_id : '';
                        
                        var resultHtml = '<div style="background: #fff; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px;">' +
                            '<h4 style="margin-top: 0;">Test Order Created Successfully</h4>' +
                            '<p><strong>Message:</strong> ' + response.data.message + '</p>' +
                            '<p><strong>Order ID:</strong> ' + response.data.order_id + '</p>' +
                            '<p><strong>Snapshot:</strong> ' + response.data.snapshot_slug + '</p>' +
                            customerInfo +
                            '<p><strong>Actions:</strong> ' +
                            '<a href="' + response.data.order_edit_url + '" class="button button-secondary" target="_blank">View Order</a> ' +
                            '<a href="' + iwp_woo_v2_admin.orders_url + '" class="button button-secondary" target="_blank">View All Orders</a>';
                        
                        if (response.data.customer_id) {
                            resultHtml += ' <a href="' + iwp_woo_v2_admin.site_url + '/my-account/" class="button button-secondary" target="_blank">Customer My Account</a>';
                        }
                        
                        resultHtml += '</p>' +
                            '<p class="description"><em>The InstaWP site creation process should now be triggered automatically. Check the order notes for site creation status.</em></p>';
                        
                        if (response.data.customer_type === 'existing' && response.data.customer_id) {
                            resultHtml += '<p class="description"><em><strong>To test customer view:</strong> Login as the selected user and visit My Account → Orders to see the InstaWP site details.</em></p>';
                        }
                        
                        resultHtml += '</div>';
                        
                        $results.html(resultHtml);
                        
                        IWP_Woo_V2_Admin.showNotice('Test order created successfully! Site creation is now in progress.', 'success');
                    } else {
                        $status.html('<span style="color: #d63638;">✗ Order creation failed</span>');
                        
                        var errorHtml = '<div style="background: #fff; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px;">' +
                            '<h4 style="margin-top: 0; color: #d63638;">Test Order Creation Failed</h4>' +
                            '<p><strong>Error:</strong> ' + (response.data || 'Unknown error occurred') + '</p>' +
                            '</div>';
                        
                        $results.html(errorHtml);
                        
                        IWP_Woo_V2_Admin.showNotice('Failed to create test order: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $status.html('<span style="color: #d63638;">✗ Request failed</span>');
                    
                    var errorHtml = '<div style="background: #fff; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px;">' +
                        '<h4 style="margin-top: 0; color: #d63638;">Request Failed</h4>' +
                        '<p><strong>Error:</strong> ' + error + '</p>' +
                        '<p><strong>Status:</strong> ' + status + '</p>' +
                        '</div>';
                    
                    $results.html(errorHtml);
                    
                    IWP_Woo_V2_Admin.showNotice('Request failed: ' + error, 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        console.log('Admin.js loaded and document ready');
        console.log('Available variables:', typeof iwp_woo_v2_admin !== 'undefined' ? 'iwp_woo_v2_admin exists' : 'iwp_woo_v2_admin missing');
        IWP_Woo_V2_Admin.init();
    });

    // Make it globally accessible
    window.IWP_Woo_V2_Admin = IWP_Woo_V2_Admin;

})(jQuery);