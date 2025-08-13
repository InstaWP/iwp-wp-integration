/**
 * Admin JavaScript for InstaWP Integration
 *
 * @package IWP
 * @since 2.0.0
 */

(function($) {
    'use strict';

    var IWPAdmin = {
        
        // Form state tracking
        originalFormData: null,
        hasUnsavedChanges: false,
        
        /**
         * Initialize the admin functionality
         */
        init: function() {
            console.log('IWPAdmin.init() called');
            this.bindEvents();
            this.initComponents();
            this.loadActiveTab();
            this.initKeyboardNavigation();
            this.initFormChangeTracking();
            this.initTabPreloading();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Tab navigation
            $('.iwp-admin-tabs .nav-tab').on('click', function(e) {
                e.preventDefault();
                var tabId = $(this).attr('href').substring(1);
                self.switchTab(tabId);
            });
            
            // Settings form submission
            $('#iwp-settings-form').on('submit', this.handleSettingsSubmit);
            
            // Reset settings confirmation
            $('.iwp-reset-settings').on('click', this.handleResetSettings);
            
            // Toggle dependent fields
            $('input[name="iwp_options[debug_mode]"]').on('change', this.toggleDebugFields);
            
            // AJAX actions
            $('.iwp-ajax-action').on('click', this.handleAjaxAction);
            
            // Templates refresh
            $('#iwp-refresh-templates').on('click', this.handleRefreshTemplates);
            
            // Plans refresh
            $('#iwp-refresh-plans').on('click', this.handleRefreshPlans);
            
            // Cache management
            $('#iwp-clear-transients').on('click', this.handleClearTransients);
            $('#iwp-warm-cache').on('click', this.handleWarmCache);
            
            // Site status refresh
            $('#refresh-site-status').on('click', this.handleRefreshSiteStatus.bind(this));
            
            // Test order creation - bind events with validation
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
            $('#iwp-settings-form input, #iwp-settings-form select').on('change', this.validateField);
            
            // Window beforeunload handler for unsaved changes
            $(window).on('beforeunload', this.handleBeforeUnload.bind(this));
            
            // Browser back/forward navigation
            $(window).on('hashchange', function() {
                var hash = window.location.hash.substring(1);
                if (hash && $('#' + hash).length) {
                    self.switchTab(hash, false);
                }
            });
        },

        /**
         * Initialize tab-specific events
         */
        initTabEvents: function() {
            var self = this;
            
            // Handle settings form submission with tab preservation
            $(document).on('submit', '#iwp-settings-form', function(e) {
                // Store the current tab before form submission
                var currentTab = $('.iwp-admin-tabs .nav-tab-active').attr('href');
                if (currentTab) {
                    localStorage.setItem('iwp_active_tab_form_submit', currentTab.replace('#', ''));
                }
            });
            
            // Restore tab after page reload from form submission
            if (localStorage.getItem('iwp_active_tab_form_submit')) {
                var savedTab = localStorage.getItem('iwp_active_tab_form_submit');
                localStorage.removeItem('iwp_active_tab_form_submit');
                if ($('#' + savedTab).length) {
                    setTimeout(function() {
                        self.switchTab(savedTab);
                    }, 100);
                }
            }
            
            // Handle tab-specific button clicks with improved handling
            $(document).on('click', '#iwp-refresh-templates', function() {
                self.refreshSnapshots(true);
            });
            
            $(document).on('click', '#iwp-refresh-plans', function() {
                self.refreshPlans(true);
            });
            
            // Add loading states to data refresh buttons  
            $(document).on('click', '#iwp-refresh-snapshots, #iwp-refresh-plans', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var originalText = $btn.text();
                
                $btn.prop('disabled', true)
                   .text(iwp_admin.strings.loading || 'Loading...')
                   .addClass('updating-message');
            });
            
            // Enhance notices for better UX
            $(document).on('iwp:tab:changed', function(e, tabId) {
                // Clear any temporary notices when switching tabs
                $('.iwp-notice.temporary').fadeOut();
                
                // Show tab-specific help text
                self.showTabHelp(tabId);
            });
        },

        /**
         * Show tab-specific help text
         */
        showTabHelp: function(tabId) {
            // Remove any existing help notices
            $('.iwp-tab-help').remove();
            
            var helpText = '';
            switch(tabId) {
                case 'general-settings':
                    helpText = iwp_admin.strings.help_general || 'Configure your InstaWP API key and integration settings.';
                    break;
                case 'instawp-data':  
                    helpText = iwp_admin.strings.help_data || 'View and refresh cached data from InstaWP API. Click refresh buttons to update snapshots and plans.';
                    break;
                case 'testing':
                    helpText = iwp_admin.strings.help_testing || 'Test order creation and site functionality. Use these tools to verify your integration is working correctly.';
                    break;
                case 'documentation':
                    helpText = iwp_admin.strings.help_docs || 'Find shortcode examples, API references, and helpful links for using the plugin.';
                    break;
            }
            
            if (helpText) {
                var $help = $('<div class="iwp-tab-help" style="background: #f0f6fc; border-left: 4px solid #0073aa; padding: 10px 15px; margin-bottom: 20px; border-radius: 0 3px 3px 0;">' +
                    '<p style="margin: 0; color: #0073aa; font-size: 14px;"><strong>💡 Tip:</strong> ' + helpText + '</p>' +
                    '</div>');
                
                $('#' + tabId).prepend($help);
                
                // Auto-hide help after 10 seconds
                setTimeout(function() {
                    $help.fadeOut();
                }, 10000);
            }
        },

        /**
         * Initialize components
         */
        initComponents: function() {
            // Initialize tooltips
            this.initTooltips();
            
            // Set initial field states
            this.toggleDebugFields();
            
            // Initialize test order form
            this.initTestOrderForm();
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('.iwp-tooltip').each(function() {
                var $tooltip = $(this);
                var title = $tooltip.attr('title');
                
                if (title) {
                    $tooltip.removeAttr('title');
                    $tooltip.on('mouseenter', function() {
                        $('<div class="iwp-tooltip-content">' + title + '</div>')
                            .appendTo('body')
                            .fadeIn(200);
                    }).on('mouseleave', function() {
                        $('.iwp-tooltip-content').fadeOut(200, function() {
                            $(this).remove();
                        });
                    });
                }
            });
        },

        /**
         * Initialize keyboard navigation for tabs
         */
        initKeyboardNavigation: function() {
            var self = this;
            $('.iwp-admin-tabs .nav-tab').on('keydown', function(e) {
                var $tabs = $('.iwp-admin-tabs .nav-tab');
                var currentIndex = $tabs.index(this);
                var targetIndex = -1;
                
                switch(e.keyCode) {
                    case 37: // Left arrow
                        e.preventDefault();
                        targetIndex = currentIndex > 0 ? currentIndex - 1 : $tabs.length - 1;
                        break;
                    case 39: // Right arrow
                        e.preventDefault();
                        targetIndex = currentIndex < $tabs.length - 1 ? currentIndex + 1 : 0;
                        break;
                    case 36: // Home
                        e.preventDefault();
                        targetIndex = 0;
                        break;
                    case 35: // End
                        e.preventDefault();
                        targetIndex = $tabs.length - 1;
                        break;
                }
                
                if (targetIndex !== -1) {
                    var $targetTab = $tabs.eq(targetIndex);
                    $targetTab.focus();
                    var tabId = $targetTab.attr('href').substring(1);
                    self.switchTab(tabId);
                }
            });
        },

        /**
         * Switch to a specific tab
         */
        switchTab: function(tabId, updateHash) {
            updateHash = updateHash !== false; // Default to true
            
            var $tabs = $('.iwp-admin-tabs');
            var $targetTab = $tabs.find('.nav-tab[href="#' + tabId + '"]');
            var $targetContent = $('#' + tabId);
            
            if ($targetTab.length && $targetContent.length) {
                // Update active tab
                $tabs.find('.nav-tab').removeClass('nav-tab-active');
                $targetTab.addClass('nav-tab-active');
                
                // Update active content with animation
                $('.iwp-tab-content').removeClass('active').hide();
                $targetContent.addClass('active').show();
                
                // Update URL hash (without triggering hashchange)
                if (updateHash) {
                    if (history.replaceState) {
                        history.replaceState(null, null, '#' + tabId);
                    } else {
                        window.location.hash = tabId;
                    }
                }
                
                // Save active tab to localStorage
                localStorage.setItem('iwp_active_tab', tabId);
                
                // Load tab-specific content if needed
                this.loadTabContent(tabId);
                
                // Show contextual help
                this.showTabHelp(tabId);
                
                // Preload next tab content
                this.preloadNextTab(tabId);
            }
        },

        /**
         * Load active tab from URL hash or localStorage
         */
        loadActiveTab: function() {
            var hash = window.location.hash.replace('#', '');
            var savedTab = localStorage.getItem('iwp_active_tab');
            var defaultTab = 'general-settings';
            
            // Priority: URL hash > localStorage > default
            var activeTab = hash || savedTab || defaultTab;
            
            // Ensure the tab exists
            if (!$('#' + activeTab).length) {
                activeTab = defaultTab;
            }
            
            // Force show the first tab immediately to prevent blank content
            if (!$('.iwp-tab-content.active').length) {
                $('#' + activeTab).addClass('active').show();
                $('.iwp-admin-tabs .nav-tab[href="#' + activeTab + '"]').addClass('nav-tab-active');
            }
            
            this.switchTab(activeTab, !hash); // Don't update hash if it's already set
        },

        /**
         * Load tab-specific content (for lazy loading)
         */
        loadTabContent: function(tabId) {
            // Load heavy content only when tab is activated
            switch(tabId) {
                case 'instawp-data':
                    // Refresh snapshots/plans if not loaded
                    if (!$('#iwp-snapshots-list').hasClass('loaded')) {
                        this.refreshSnapshots(false);
                    }
                    if (!$('#iwp-plans-list').hasClass('loaded')) {
                        this.refreshPlans(false);
                    }
                    break;
                    
                case 'testing':
                    // Initialize test components
                    this.initTestOrderForm();
                    break;
            }
        },
        
        /**
         * Show contextual help for tabs
         */
        showTabHelp: function(tabId) {
            var helpText = '';
            switch(tabId) {
                case 'general-settings':
                    helpText = 'Configure your basic plugin settings here.';
                    break;
                case 'instawp-data':
                    helpText = 'View your InstaWP snapshots and plans data.';
                    break;
                case 'testing':
                    helpText = 'Create test orders to verify your configuration.';
                    break;
                case 'documentation':
                    helpText = 'Find helpful resources and usage examples.';
                    break;
            }
            
            // Show help via console for now (could be enhanced with UI)
            if (helpText) {
                console.log('Tab Help - ' + tabId + ': ' + helpText);
            }
        },
        
        /**
         * Initialize form change tracking
         */
        initFormChangeTracking: function() {
            var self = this;
            var $form = $('form');
            if ($form.length === 0) return;
            
            // Store original form data
            this.originalFormData = $form.serialize();
            this.hasUnsavedChanges = false;
            
            // Track form changes
            $form.on('change input', 'input, select, textarea', function() {
                var currentData = $form.serialize();
                var hasChanges = currentData !== self.originalFormData;
                
                self.updateChangeIndicators(hasChanges);
            });
            
            // Form submission handling to preserve active tab
            $form.on('submit', function() {
                var activeTab = $('.nav-tab-active').attr('href');
                if (activeTab) {
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'iwp_active_tab',
                        value: activeTab.substring(1)
                    }).appendTo(this);
                }
                
                // Clear change tracking on form submit
                self.clearChangeTracking();
            });
        },
        
        /**
         * Update visual indicators for unsaved changes
         */
        updateChangeIndicators: function(hasChanges) {
            this.hasUnsavedChanges = hasChanges;
            var $activeTab = $('.nav-tab-active');
            var $form = $('form');
            
            if (hasChanges) {
                $activeTab.addClass('has-changes');
                $form.addClass('iwp-form-changed');
            } else {
                $('.nav-tab').removeClass('has-changes');
                $form.removeClass('iwp-form-changed');
            }
        },
        
        /**
         * Clear change tracking
         */
        clearChangeTracking: function() {
            this.hasUnsavedChanges = false;
            $('.nav-tab').removeClass('has-changes');
            $('form').removeClass('iwp-form-changed');
            this.originalFormData = $('form').serialize();
        },
        
        /**
         * Handle browser beforeunload for unsaved changes
         */
        handleBeforeUnload: function(e) {
            if (this.hasUnsavedChanges) {
                var message = 'You have unsaved changes. Are you sure you want to leave?';
                e.returnValue = message;
                return message;
            }
        },
        
        /**
         * Preload content for next tab
         */
        preloadNextTab: function(currentTabId) {
            var tabs = ['general-settings', 'instawp-data', 'testing', 'documentation'];
            var currentIndex = tabs.indexOf(currentTabId);
            var nextIndex = (currentIndex + 1) % tabs.length;
            var nextTabId = tabs[nextIndex];
            
            var $nextTab = $('#' + nextTabId);
            if ($nextTab.length && !$nextTab.data('preloaded')) {
                // Mark as preloaded to avoid duplicate requests
                $nextTab.data('preloaded', true);
                
                // Add subtle preloader indicator
                var $preloader = $('<div class="iwp-tab-preloader">Preparing content...</div>');
                $nextTab.append($preloader);
                
                // Simulate content loading (in real implementation, this would be AJAX)
                setTimeout(function() {
                    $preloader.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 1000);
            }
        },
        
        /**
         * Initialize tab preloading system
         */
        initTabPreloading: function() {
            var self = this;
            // Preload adjacent tabs on hover
            $('.iwp-admin-tabs .nav-tab').on('mouseenter', function() {
                var tabId = $(this).attr('href').substring(1);
                self.preloadNextTab(tabId);
            });
        },
        
        /**
         * Show save notification
         */
        showSaveNotification: function(message, isError) {
            isError = isError || false;
            
            // Remove existing notification
            $('.iwp-save-notification').remove();
            
            // Create notification element
            var $notification = $('<div class="iwp-save-notification' + (isError ? ' error' : '') + '">' + message + '</div>');
            
            // Add to page
            $('body').append($notification);
            
            // Show with animation
            setTimeout(function() {
                $notification.addClass('show');
            }, 100);
            
            // Hide after delay
            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        },

        /**
         * Refresh snapshots data
         */
        refreshSnapshots: function(showNotice) {
            showNotice = showNotice !== false;
            var $container = $('#iwp-snapshots-list');
            
            if ($container.length) {
                $container.html('<p>Loading snapshots...</p>');
                
                $.ajax({
                    url: iwp_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'iwp_refresh_templates',
                        nonce: iwp_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $container.addClass('loaded');
                            if (showNotice) {
                                IWPAdmin.showSaveNotification('Snapshots refreshed successfully');
                            }
                            // Reload the page to show updated data
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            $container.html('<p class="error">Failed to load snapshots</p>');
                            if (showNotice) {
                                IWPAdmin.showSaveNotification('Failed to refresh snapshots', true);
                            }
                        }
                    },
                    error: function() {
                        $container.html('<p class="error">Network error loading snapshots</p>');
                        if (showNotice) {
                            IWPAdmin.showSaveNotification('Network error refreshing snapshots', true);
                        }
                    }
                });
            }
        },

        /**
         * Refresh plans data
         */
        refreshPlans: function(showNotice) {
            showNotice = showNotice !== false;
            var $container = $('#iwp-plans-list');
            
            if ($container.length) {
                $container.html('<p>Loading plans...</p>');
                
                $.ajax({
                    url: iwp_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'iwp_refresh_plans',
                        nonce: iwp_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $container.addClass('loaded');
                            if (showNotice) {
                                IWPAdmin.showSaveNotification('Plans refreshed successfully');
                            }
                            // Reload the page to show updated data
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            $container.html('<p class="error">Failed to load plans</p>');
                            if (showNotice) {
                                IWPAdmin.showSaveNotification('Failed to refresh plans', true);
                            }
                        }
                    },
                    error: function() {
                        $container.html('<p class="error">Network error loading plans</p>');
                        if (showNotice) {
                            IWPAdmin.showSaveNotification('Network error refreshing plans', true);
                        }
                    }
                });
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
            $submitButton.val(iwp_admin.strings.saving || 'Saving...');
            
            // Add loading spinner
            $('<span class="iwp-loading"></span>').insertAfter($submitButton);
        },

        /**
         * Handle reset settings
         */
        handleResetSettings: function(e) {
            if (!confirm(iwp_admin.strings.confirm_reset)) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            var $button = $(this);
            $button.prop('disabled', true);
            $('<span class="iwp-loading"></span>').insertAfter($button);
        },

        /**
         * Toggle debug fields
         */
        toggleDebugFields: function() {
            var $debugMode = $('input[name="iwp_options[debug_mode]"]');
            var $debugFields = $('.iwp-debug-field');
            
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
            $button.text(iwp_admin.strings.loading || 'Loading...');
            
            $.ajax({
                url: iwp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_' + action,
                    nonce: iwp_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        IWP_Admin.showNotice(response.data.message, 'success');
                    } else {
                        IWP_Admin.showNotice(response.data.message || iwp_admin.strings.error_occurred, 'error');
                    }
                },
                error: function() {
                    IWP_Admin.showNotice(iwp_admin.strings.error_occurred, 'error');
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
            $field.siblings('.iwp-validation-message').remove();
            
            // Basic validation
            if ($field.prop('required') && !fieldValue) {
                IWP_Admin.showFieldError($field, 'This field is required.');
                return false;
            }
            
            // Email validation
            if ($field.attr('type') === 'email' && fieldValue) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(fieldValue)) {
                    IWP_Admin.showFieldError($field, 'Please enter a valid email address.');
                    return false;
                }
            }
            
            // URL validation
            if ($field.attr('type') === 'url' && fieldValue) {
                try {
                    new URL(fieldValue);
                } catch (e) {
                    IWP_Admin.showFieldError($field, 'Please enter a valid URL.');
                    return false;
                }
            }
            
            return true;
        },

        /**
         * Show field error
         */
        showFieldError: function($field, message) {
            $field.addClass('iwp-field-error');
            $('<div class="iwp-validation-message error">' + message + '</div>').insertAfter($field);
        },

        /**
         * Show notice (enhanced for tabs)
         */
        showNotice: function(message, type, temporary) {
            type = type || 'info';
            temporary = temporary !== false; // Default to temporary
            
            var noticeClass = 'notice notice-' + type + ' is-dismissible iwp-notice';
            if (temporary) {
                noticeClass += ' temporary';
            }
            
            var $notice = $('<div class="' + noticeClass + '">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss"></button>' +
                '</div>');
            
            // Position notice appropriately  
            if ($('.iwp-admin-tabs').length) {
                $('.iwp-admin-tabs').after($notice);
            } else {
                $('.wrap h1').after($notice);
            }
            
            // Make notice dismissible
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut();
            });
            
            // Auto-dismiss temporary notices after 5 seconds
            if (temporary) {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
            }
            
            // Scroll to notice if not visible
            var noticeTop = $notice.offset().top;
            var scrollTop = $(window).scrollTop();
            var windowHeight = $(window).height();
            
            if (noticeTop < scrollTop || noticeTop > scrollTop + windowHeight) {
                $('html, body').animate({
                    scrollTop: noticeTop - 100
                }, 500);
            }
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
            if ($('.iwp-loading-overlay').length === 0) {
                $('<div class="iwp-loading-overlay"><div class="iwp-loading"></div></div>')
                    .appendTo('body');
            }
        },

        /**
         * Utility: Hide loading overlay
         */
        hideLoading: function() {
            $('.iwp-loading-overlay').remove();
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
                url: iwp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_refresh_templates',
                    nonce: iwp_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        IWP_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated templates
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        IWP_Admin.showNotice(response.data.message || 'An error occurred while refreshing templates.', 'error');
                    }
                },
                error: function() {
                    IWP_Admin.showNotice('An error occurred while refreshing templates.', 'error');
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
                url: iwp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_refresh_plans',
                    nonce: iwp_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        IWP_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated plans
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        IWP_Admin.showNotice(response.data.message || 'An error occurred while refreshing plans.', 'error');
                    }
                },
                error: function() {
                    IWP_Admin.showNotice('An error occurred while refreshing plans.', 'error');
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
                url: iwp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_clear_transients',
                    nonce: iwp_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        IWP_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated cache status
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        IWP_Admin.showNotice(response.data.message || 'An error occurred while clearing transients.', 'error');
                    }
                },
                error: function() {
                    IWP_Admin.showNotice('An error occurred while clearing transients.', 'error');
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
                url: iwp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_warm_cache',
                    nonce: iwp_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        IWP_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated cache status
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        IWP_Admin.showNotice(response.data.message || 'An error occurred while warming up cache.', 'error');
                    }
                },
                error: function() {
                    IWP_Admin.showNotice('An error occurred while warming up cache.', 'error');
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
            
            var $button = $(e.target);
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true);
            $button.text('Refreshing...').addClass('loading');
            
            $.ajax({
                url: iwp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_refresh_site_status',
                    nonce: iwp_admin.nonce
                },
                success: function(response) {
                    console.log('Refresh response:', response);
                    
                    if (response.success) {
                        // Show success notification
                        IWPAdmin.showSaveNotification(response.data.message);
                        
                        // Reload the page to show updated status
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        console.error('Refresh failed:', response);
                        
                        // Show error notification
                        var errorMessage = response.data && response.data.message ? response.data.message : 'Failed to refresh site status.';
                        IWPAdmin.showSaveNotification(errorMessage, true);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error:', textStatus, errorThrown);
                    
                    // Show error notification
                    IWPAdmin.showSaveNotification('Network error occurred while refreshing site status.', true);
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false)
                           .text(originalText)
                           .removeClass('loading');
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
                IWP_Admin.showNotice('Please select a product first.', 'error');
                return;
            }
            
            // Prepare data based on customer type
            var requestData = {
                action: 'iwp_create_test_order',
                nonce: iwp_admin.nonce,
                product_id: productId,
                customer_type: customerType
            };
            
            switch (customerType) {
                case 'existing':
                    var customerId = $('#iwp-test-customer-select').val();
                    if (!customerId) {
                        IWP_Admin.showNotice('Please select a user.', 'error');
                        return;
                    }
                    requestData.customer_id = customerId;
                    break;
                    
                case 'guest':
                    var guestFirstName = $('#iwp-test-customer-first-name').val();
                    var guestLastName = $('#iwp-test-customer-last-name').val();
                    var guestEmail = $('#iwp-test-customer-email').val();
                    
                    if (!guestEmail || !guestFirstName || !guestLastName) {
                        IWP_Admin.showNotice('Please fill in all guest customer details.', 'error');
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
                        IWP_Admin.showNotice('Username and email are required for new users.', 'error');
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
                url: iwp_admin.ajax_url,
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
                            iwp_admin.site_url + '/my-account/?customer_id=' + response.data.customer_id : '';
                        
                        var resultHtml = '<div style="background: #fff; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px;">' +
                            '<h4 style="margin-top: 0;">Test Order Created Successfully</h4>' +
                            '<p><strong>Message:</strong> ' + response.data.message + '</p>' +
                            '<p><strong>Order ID:</strong> ' + response.data.order_id + '</p>' +
                            '<p><strong>Snapshot:</strong> ' + response.data.snapshot_slug + '</p>' +
                            customerInfo +
                            '<p><strong>Actions:</strong> ' +
                            '<a href="' + response.data.order_edit_url + '" class="button button-secondary" target="_blank">View Order</a> ' +
                            '<a href="' + iwp_admin.orders_url + '" class="button button-secondary" target="_blank">View All Orders</a>';
                        
                        if (response.data.customer_id) {
                            resultHtml += ' <a href="' + iwp_admin.site_url + '/my-account/" class="button button-secondary" target="_blank">Customer My Account</a>';
                        }
                        
                        resultHtml += '</p>' +
                            '<p class="description"><em>The site creation process should now be triggered automatically. Check the order notes for site creation status.</em></p>';
                        
                        if (response.data.customer_type === 'existing' && response.data.customer_id) {
                            resultHtml += '<p class="description"><em><strong>To test customer view:</strong> Login as the selected user and visit My Account → Orders to see the site details.</em></p>';
                        }
                        
                        resultHtml += '</div>';
                        
                        $results.html(resultHtml);
                        
                        IWPAdmin.showSaveNotification('Test order created successfully! Site creation is now in progress.');
                    } else {
                        $status.html('<span style="color: #d63638;">✗ Order creation failed</span>');
                        
                        var errorHtml = '<div style="background: #fff; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px;">' +
                            '<h4 style="margin-top: 0; color: #d63638;">Test Order Creation Failed</h4>' +
                            '<p><strong>Error:</strong> ' + (response.data || 'Unknown error occurred') + '</p>' +
                            '</div>';
                        
                        $results.html(errorHtml);
                        
                        IWPAdmin.showSaveNotification('Failed to create test order: ' + (response.data || 'Unknown error'), true);
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
                    
                    IWPAdmin.showSaveNotification('Request failed: ' + error, true);
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
        console.log('Available variables:', typeof iwp_admin !== 'undefined' ? 'iwp_admin exists' : 'iwp_admin missing');
        IWPAdmin.init();
    });

    // Make it globally accessible
    window.IWPAdmin = IWPAdmin;
    window.IWP_Admin = IWPAdmin; // Backward compatibility

})(jQuery);