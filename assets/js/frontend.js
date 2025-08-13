/**
 * Frontend JavaScript for InstaWP Integration
 *
 * @package IWP
 * @since 2.0.0
 */

(function($) {
    'use strict';

    var IWP_Frontend = {
        
        /**
         * Initialize the frontend functionality
         */
        init: function() {
            this.bindEvents();
            this.initComponents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Product page events
            $('.single-product').on('click', '.iwp-product-action', this.handleProductAction);
            
            // Cart events
            $(document.body).on('updated_cart_totals', this.handleCartUpdate);
            $(document.body).on('added_to_cart', this.handleAddToCart);
            $(document.body).on('removed_from_cart', this.handleRemoveFromCart);
            
            // Checkout events
            $(document.body).on('updated_checkout', this.handleCheckoutUpdate);
            $(document.body).on('checkout_error', this.handleCheckoutError);
            
            // General AJAX events
            $('.iwp-ajax-action').on('click', this.handleAjaxAction);
            
            // Form events
            $('.iwp-form').on('submit', this.handleFormSubmit);
            
            // Window events
            $(window).on('resize', this.handleWindowResize);
            $(window).on('scroll', this.handleWindowScroll);
            
            // InstaWP specific events
            $(document).on('click', '.iwp-copy-btn', this.handleCopyToClipboard);
            $(document).on('click', '.iwp-show-password-btn', this.handleTogglePassword);
            
            // Domain mapping events
            $(document).on('click', '.iwp-map-domain-btn', this.handleMapDomainClick);
            $(document).on('click', '.iwp-modal-close', this.handleModalClose);
            $(document).on('click', '.iwp-modal-cancel', this.handleModalClose);
            $(document).on('submit', '#iwp-domain-form', this.handleDomainSubmit);
        },

        /**
         * Initialize components
         */
        initComponents: function() {
            // Initialize tooltips
            this.initTooltips();
            
            // Initialize modals
            this.initModals();
            
            // Initialize notices
            this.initNotices();
            
            // Initialize auto-refresh for pending sites
            this.initAutoRefresh();
            
            // Initialize lazy loading
            this.initLazyLoading();
            
            // Track page view
            this.trackPageView();
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('.iwp-tooltip').each(function() {
                var $tooltip = $(this);
                var title = $tooltip.attr('title') || $tooltip.data('tooltip');
                
                if (title) {
                    $tooltip.removeAttr('title');
                    $tooltip.on('mouseenter', function(e) {
                        var $content = $('<div class="iwp-tooltip-content">' + title + '</div>');
                        $content.appendTo('body').css({
                            position: 'absolute',
                            top: e.pageY + 10,
                            left: e.pageX + 10,
                            zIndex: 1000
                        }).fadeIn(200);
                    }).on('mouseleave', function() {
                        $('.iwp-tooltip-content').fadeOut(200, function() {
                            $(this).remove();
                        });
                    });
                }
            });
        },

        /**
         * Initialize modals
         */
        initModals: function() {
            // Modal triggers
            $('[data-iwp-modal]').on('click', function(e) {
                e.preventDefault();
                var modalId = $(this).data('iwp-modal');
                IWP_Frontend.openModal(modalId);
            });
            
            // Modal close buttons
            $(document).on('click', '.iwp-modal-close', function(e) {
                e.preventDefault();
                IWP_Frontend.closeModal();
            });
            
            // Close modal on overlay click
            $(document).on('click', '.iwp-modal-overlay', function(e) {
                if (e.target === this) {
                    IWP_Frontend.closeModal();
                }
            });
            
            // Close modal on escape key
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) {
                    IWP_Frontend.closeModal();
                }
            });
        },

        /**
         * Initialize notices
         */
        initNotices: function() {
            // Auto-dismiss notices
            $('.iwp-notice[data-auto-dismiss]').each(function() {
                var $notice = $(this);
                var delay = parseInt($notice.data('auto-dismiss')) || 5000;
                
                setTimeout(function() {
                    $notice.fadeOut();
                }, delay);
            });
            
            // Dismiss button
            $(document).on('click', '.iwp-notice-dismiss', function(e) {
                e.preventDefault();
                $(this).closest('.iwp-notice').fadeOut();
            });
        },

        /**
         * Initialize lazy loading
         */
        initLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                var lazyImageObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var lazyImage = entry.target;
                            lazyImage.src = lazyImage.dataset.src;
                            lazyImage.classList.remove('iwp-lazy');
                            lazyImage.classList.add('iwp-loaded');
                            lazyImageObserver.unobserve(lazyImage);
                        }
                    });
                });

                $('.iwp-lazy').each(function() {
                    lazyImageObserver.observe(this);
                });
            }
        },

        /**
         * Initialize auto-refresh for pending sites
         */
        initAutoRefresh: function() {
            var $pendingSites = $('.iwp-site-progress');
            if ($pendingSites.length > 0) {
                var self = this;
                // Show a subtle notification that the page will auto-refresh
                if ($pendingSites.find('.iwp-auto-refresh-notice').length === 0) {
                    $pendingSites.append('<p class="iwp-auto-refresh-notice" style="font-size: 0.9em; opacity: 0.7; margin-top: 10px;"><em>🔄 This page will automatically refresh in 10 seconds to check for updates...</em></p>');
                }
                
                // Auto-refresh after 10 seconds
                setTimeout(function() {
                    location.reload();
                }, 10000);
            }
        },

        /**
         * Track page view
         */
        trackPageView: function() {
            // Only track if analytics is enabled
            if (typeof iwp_frontend.track_views !== 'undefined' && iwp_frontend.track_views) {
                this.sendAnalytics('page_view', {
                    page: window.location.pathname,
                    title: document.title
                });
            }
        },

        /**
         * Handle product action
         */
        handleProductAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var action = $button.data('action');
            var productId = $button.data('product-id');
            
            if (!action || !productId) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true);
            $button.addClass('loading');
            
            $.ajax({
                url: iwp_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_product_' + action,
                    product_id: productId,
                    nonce: iwp_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        IWP_Frontend.showNotice(response.data.message, 'success');
                        
                        // Trigger custom event
                        $(document.body).trigger('iwp_product_action_success', [action, productId, response.data]);
                    } else {
                        IWP_Frontend.showNotice(response.data.message || iwp_frontend.strings.error, 'error');
                    }
                },
                error: function() {
                    IWP_Frontend.showNotice(iwp_frontend.strings.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.removeClass('loading');
                }
            });
        },

        /**
         * Handle cart update
         */
        handleCartUpdate: function() {
            // Track cart update
            IWP_Frontend.sendAnalytics('cart_update');
            
            // Update cart indicators
            IWP_Frontend.updateCartIndicators();
        },

        /**
         * Handle add to cart
         */
        handleAddToCart: function(e, fragments, cart_hash, $button) {
            // Track add to cart
            IWP_Frontend.sendAnalytics('add_to_cart', {
                product_id: $button ? $button.data('product_id') : null
            });
            
            // Show success message
            IWP_Frontend.showNotice(iwp_frontend.strings.added_to_cart || 'Product added to cart', 'success');
        },

        /**
         * Handle remove from cart
         */
        handleRemoveFromCart: function() {
            // Track remove from cart
            IWP_Frontend.sendAnalytics('remove_from_cart');
        },

        /**
         * Handle checkout update
         */
        handleCheckoutUpdate: function() {
            // Track checkout update
            IWP_Frontend.sendAnalytics('checkout_update');
        },

        /**
         * Handle checkout error
         */
        handleCheckoutError: function() {
            // Track checkout error
            IWP_Frontend.sendAnalytics('checkout_error');
        },

        /**
         * Handle AJAX action
         */
        handleAjaxAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var action = $button.data('action');
            var data = $button.data();
            
            if (!action) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true);
            $button.addClass('loading');
            
            $.ajax({
                url: iwp_frontend.ajax_url,
                type: 'POST',
                data: $.extend({
                    action: 'iwp_' + action,
                    nonce: iwp_frontend.nonce
                }, data),
                success: function(response) {
                    if (response.success) {
                        IWP_Frontend.showNotice(response.data.message, 'success');
                        
                        // Handle specific actions
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                        
                        if (response.data.reload) {
                            location.reload();
                        }
                    } else {
                        IWP_Frontend.showNotice(response.data.message || iwp_frontend.strings.error, 'error');
                    }
                },
                error: function() {
                    IWP_Frontend.showNotice(iwp_frontend.strings.error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.removeClass('loading');
                }
            });
        },

        /**
         * Handle form submit
         */
        handleFormSubmit: function(e) {
            var $form = $(this);
            var action = $form.data('action');
            
            if (!action) {
                return;
            }
            
            e.preventDefault();
            
            // Validate form
            if (!IWP_Frontend.validateForm($form)) {
                return;
            }
            
            // Show loading state
            var $submitButton = $form.find('[type="submit"]');
            $submitButton.prop('disabled', true);
            $submitButton.addClass('loading');
            
            $.ajax({
                url: iwp_frontend.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=iwp_' + action + '&nonce=' + iwp_frontend.nonce,
                success: function(response) {
                    if (response.success) {
                        IWP_Frontend.showNotice(response.data.message, 'success');
                        
                        // Reset form if specified
                        if (response.data.reset_form) {
                            $form[0].reset();
                        }
                    } else {
                        IWP_Frontend.showNotice(response.data.message || iwp_frontend.strings.error, 'error');
                    }
                },
                error: function() {
                    IWP_Frontend.showNotice(iwp_frontend.strings.error, 'error');
                },
                complete: function() {
                    $submitButton.prop('disabled', false);
                    $submitButton.removeClass('loading');
                }
            });
        },

        /**
         * Handle window resize
         */
        handleWindowResize: function() {
            // Implement responsive adjustments
            clearTimeout(this.resizeTimer);
            this.resizeTimer = setTimeout(function() {
                // Trigger custom event
                $(document.body).trigger('iwp_window_resized');
            }, 250);
        },

        /**
         * Handle window scroll
         */
        handleWindowScroll: function() {
            // Implement scroll-based functionality
            clearTimeout(this.scrollTimer);
            this.scrollTimer = setTimeout(function() {
                // Trigger custom event
                $(document.body).trigger('iwp_window_scrolled');
            }, 100);
        },

        /**
         * Validate form
         */
        validateForm: function($form) {
            var isValid = true;
            
            // Clear previous errors
            $form.find('.iwp-field-error').removeClass('iwp-field-error');
            $form.find('.iwp-error-message').remove();
            
            // Validate required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                if (!$field.val()) {
                    IWP_Frontend.showFieldError($field, 'This field is required');
                    isValid = false;
                }
            });
            
            // Validate email fields
            $form.find('input[type="email"]').each(function() {
                var $field = $(this);
                var email = $field.val();
                if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    IWP_Frontend.showFieldError($field, 'Please enter a valid email address');
                    isValid = false;
                }
            });
            
            return isValid;
        },

        /**
         * Show field error
         */
        showFieldError: function($field, message) {
            $field.addClass('iwp-field-error');
            $('<div class="iwp-error-message">' + message + '</div>').insertAfter($field);
        },

        /**
         * Show notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="iwp-notice iwp-notice-' + type + '">' +
                '<p>' + message + '</p>' +
                '<button class="iwp-notice-dismiss">&times;</button>' +
                '</div>');
            
            $('body').prepend($notice);
            $notice.fadeIn();
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },

        /**
         * Open modal
         */
        openModal: function(modalId) {
            var $modal = $('#' + modalId);
            if ($modal.length) {
                $modal.fadeIn();
                $('body').addClass('iwp-modal-open');
            }
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.iwp-modal').fadeOut();
            $('body').removeClass('iwp-modal-open');
        },

        /**
         * Update cart indicators
         */
        updateCartIndicators: function() {
            // Update cart count and total in custom elements
            $('.iwp-cart-count').each(function() {
                var $element = $(this);
                $.ajax({
                    url: iwp_frontend.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'iwp_get_cart_count',
                        nonce: iwp_frontend.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $element.text(response.data.count);
                        }
                    }
                });
            });
        },

        /**
         * Send analytics
         */
        sendAnalytics: function(event, data) {
            data = data || {};
            
            $.ajax({
                url: iwp_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_track_event',
                    event: event,
                    data: data,
                    nonce: iwp_frontend.nonce
                }
            });
        },

        /**
         * Handle copy to clipboard
         */
        handleCopyToClipboard: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var textToCopy = $button.data('copy');
            
            if (!textToCopy) {
                return;
            }
            
            // Use the modern Clipboard API if available
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    IWP_Frontend.showCopyFeedback($button, 'Copied!');
                }).catch(function() {
                    IWP_Frontend.fallbackCopyTextToClipboard(textToCopy, $button);
                });
            } else {
                // Fallback for older browsers
                IWP_Frontend.fallbackCopyTextToClipboard(textToCopy, $button);
            }
        },

        /**
         * Fallback copy to clipboard method
         */
        fallbackCopyTextToClipboard: function(text, $button) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            
            // Avoid scrolling to bottom
            textArea.style.top = '0';
            textArea.style.left = '0';
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    IWP_Frontend.showCopyFeedback($button, 'Copied!');
                } else {
                    IWP_Frontend.showCopyFeedback($button, 'Copy failed', 'error');
                }
            } catch (err) {
                IWP_Frontend.showCopyFeedback($button, 'Copy failed', 'error');
            }
            
            document.body.removeChild(textArea);
        },

        /**
         * Show copy feedback
         */
        showCopyFeedback: function($button, message, type) {
            type = type || 'success';
            
            var originalTitle = $button.attr('title');
            var originalText = $button.text();
            
            // Update button temporarily
            $button.attr('title', message);
            
            // Show feedback styling
            $button.addClass('iwp-copy-' + type);
            
            // Reset after 2 seconds
            setTimeout(function() {
                $button.attr('title', originalTitle);
                $button.removeClass('iwp-copy-success iwp-copy-error');
            }, 2000);
        },

        /**
         * Handle toggle password visibility
         */
        handleTogglePassword: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $passwordField = $button.siblings('.iwp-credential-value');
            
            if (!$passwordField.length) {
                return;
            }
            
            var password = $passwordField.data('password');
            var isHidden = $passwordField.hasClass('iwp-password-hidden');
            
            if (isHidden) {
                // Show password
                $passwordField.text(password);
                $passwordField.removeClass('iwp-password-hidden');
                $button.attr('title', 'Hide password');
                $button.text('🙈');
            } else {
                // Hide password
                $passwordField.text('••••••••');
                $passwordField.addClass('iwp-password-hidden');
                $button.attr('title', 'Show password');
                $button.text('👁️');
            }
        },

        /**
         * Handle Map Domain button click
         */
        handleMapDomainClick: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var siteId = $button.data('site-id');
            var siteUrl = $button.data('site-url');
            
            // Set the site ID in the modal
            $('#iwp-modal-site-id').val(siteId);
            
            // Update the CNAME example with the actual site URL
            $('.iwp-target-url').text(siteUrl.replace(/^https?:\/\//, ''));
            
            // Show the modal
            $('#iwp-domain-modal').fadeIn();
            $('body').addClass('iwp-modal-open');
            
            // Focus on the domain input
            $('#iwp-domain-name').focus();
        },

        /**
         * Handle modal close
         */
        handleModalClose: function(e) {
            e.preventDefault();
            
            $('#iwp-domain-modal').fadeOut();
            $('body').removeClass('iwp-modal-open');
            
            // Reset form
            $('#iwp-domain-form')[0].reset();
            $('#iwp-domain-result').hide().empty();
        },

        /**
         * Handle domain form submission
         */
        handleDomainSubmit: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var $result = $('#iwp-domain-result');
            
            // Get form data
            var formData = {
                action: 'iwp_add_domain',
                order_id: $form.find('input[name="order_id"]').val(),
                site_id: $form.find('input[name="site_id"]').val(),
                domain_name: $form.find('input[name="domain_name"]').val().trim(),
                domain_type: $form.find('select[name="domain_type"]').val(),
                nonce: $form.find('input[name="nonce"]').val()
            };
            
            // Basic validation
            if (!formData.domain_name) {
                IWP_Frontend.showDomainResult('Please enter a domain name', 'error');
                return;
            }
            
            // Validate domain format (basic)
            var domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.([a-zA-Z]{2,}|[a-zA-Z0-9-]{2,}\.[a-zA-Z]{2,})$/;
            if (!domainRegex.test(formData.domain_name)) {
                IWP_Frontend.showDomainResult('Please enter a valid domain name (e.g., example.com or www.example.com)', 'error');
                return;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true).text('Mapping Domain...');
            $result.hide();
            
            // Submit via AJAX
            $.ajax({
                url: iwp_frontend.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        IWP_Frontend.showDomainResult(response.data.message, 'success');
                        
                        // Reset form after success
                        setTimeout(function() {
                            $form[0].reset();
                            // Optionally close modal or reload page to show new domain
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        }, 1000);
                    } else {
                        IWP_Frontend.showDomainResult(response.data.message || 'Domain mapping failed', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'An error occurred while mapping the domain';
                    
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    } catch (e) {
                        // Use default error message
                    }
                    
                    IWP_Frontend.showDomainResult(errorMessage, 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text('Map Domain');
                }
            });
        },

        /**
         * Show domain mapping result
         */
        showDomainResult: function(message, type) {
            type = type || 'info';
            
            var $result = $('#iwp-domain-result');
            var className = 'iwp-domain-result-' + type;
            
            $result.removeClass('iwp-domain-result-success iwp-domain-result-error iwp-domain-result-info')
                   .addClass(className)
                   .html('<p>' + message + '</p>')
                   .fadeIn();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        IWP_Frontend.init();
    });

    // Make it globally accessible
    window.IWP_Frontend = IWP_Frontend;

})(jQuery);