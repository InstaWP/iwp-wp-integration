jQuery(document).ready(function($) {
    'use strict';

    // Initialize all shortcode forms
    $('.iwp-site-creator-form').each(function() {
        initForm($(this));
    });

    function initForm($form) {
        const $container = $form.closest('.iwp-site-creator-container');
        const $submitButton = $form.find('.iwp-site-creator-submit');
        const $statusDiv = $form.find('.iwp-site-creator-status');
        const $messageDiv = $form.find('.iwp-site-creator-message');
        const $progressDiv = $form.find('.iwp-site-creator-progress');
        const $progressBar = $form.find('.iwp-site-creator-progress-bar');
        const $resultsDiv = $form.find('.iwp-site-creator-results');

        // Handle form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            
            // Validate form
            if (!validateForm($form)) {
                return;
            }

            // Show loading state
            showLoading($submitButton, $statusDiv, $messageDiv);

            // Submit form data
            const formData = $form.serialize();
            
            $.ajax({
                url: iwp_shortcode_ajax.ajax_url,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        handleSiteCreationResponse(response.data, $form);
                    } else {
                        showError(response.data.message || 'Site creation failed', $statusDiv, $messageDiv, $submitButton);
                    }
                },
                error: function(xhr, status, error) {
                    showError('Network error: ' + error, $statusDiv, $messageDiv, $submitButton);
                }
            });
        });

        // Handle copy buttons
        $container.on('click', '.iwp-site-creator-copy-btn', function(e) {
            e.preventDefault();
            const copyType = $(this).data('copy');
            const $button = $(this);
            
            let textToCopy = '';
            if (copyType === 'username') {
                textToCopy = $container.find('.iwp-site-creator-username').text();
            } else if (copyType === 'password') {
                textToCopy = $container.find('.iwp-site-creator-password').text();
            }

            if (textToCopy) {
                copyToClipboard(textToCopy, $button);
            }
        });

        // Handle password toggle
        $container.on('click', '.iwp-site-creator-toggle-password', function(e) {
            e.preventDefault();
            const $button = $(this);
            const $password = $container.find('.iwp-site-creator-password');
            const $passwordHidden = $container.find('.iwp-site-creator-password-hidden');

            if ($password.is(':visible')) {
                $password.hide();
                $passwordHidden.show();
                $button.text(iwp_shortcode_ajax.messages.show_password);
            } else {
                $password.show();
                $passwordHidden.hide();
                $button.text(iwp_shortcode_ajax.messages.hide_password);
            }
        });
    }

    function validateForm($form) {
        let isValid = true;
        
        // Clear previous error states
        $form.find('.iwp-site-creator-input').removeClass('error');
        
        // Check required fields
        $form.find('.iwp-site-creator-input[required]').each(function() {
            const $input = $(this);
            const value = $input.val().trim();
            
            if (!value) {
                $input.addClass('error');
                isValid = false;
            }
            
            // Additional email validation
            if ($input.attr('type') === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    $input.addClass('error');
                    isValid = false;
                }
            }
        });
        
        return isValid;
    }

    function showLoading($submitButton, $statusDiv, $messageDiv) {
        $submitButton.prop('disabled', true).text('Creating...');
        $statusDiv.show();
        $messageDiv.text(iwp_shortcode_ajax.messages.creating);
        startProgressAnimation();
    }

    function showError(message, $statusDiv, $messageDiv, $submitButton) {
        $submitButton.prop('disabled', false).text('Create Site');
        $statusDiv.show();
        $messageDiv.html('<span class="error">' + message + '</span>');
        stopProgressAnimation();
    }

    function showSuccess(message, $statusDiv, $messageDiv, $submitButton) {
        $submitButton.prop('disabled', false).text('Create Site');
        $statusDiv.show();
        $messageDiv.html('<span class="success">' + message + '</span>');
        stopProgressAnimation();
    }

    function handleSiteCreationResponse(data, $form) {
        const $container = $form.closest('.iwp-site-creator-container');
        const $submitButton = $form.find('.iwp-site-creator-submit');
        const $statusDiv = $form.find('.iwp-site-creator-status');
        const $messageDiv = $form.find('.iwp-site-creator-message');
        const $resultsDiv = $form.find('.iwp-site-creator-results');


        if (data.status === 'completed') {
            // Site is ready, show results
            displaySiteResults(data, $container, $resultsDiv);
            showSuccess('Site created successfully!', $statusDiv, $messageDiv, $submitButton);
            setTimeout(function() {
                $statusDiv.hide();
            }, 3000);
        } else if ((data.status === 'progress' || data.status === 'in_progress') && data.task_id) {
            // Site is being created, check status periodically
            $messageDiv.text(iwp_shortcode_ajax.messages.checking_status);
            checkTaskStatus(data.task_id, $form, data);
        } else {
            showError('Site creation status unknown', $statusDiv, $messageDiv, $submitButton);
        }
    }

    function checkTaskStatus(taskId, $form, initialData) {
        const $container = $form.closest('.iwp-site-creator-container');
        const $submitButton = $form.find('.iwp-site-creator-submit');
        const $statusDiv = $form.find('.iwp-site-creator-status');
        const $messageDiv = $form.find('.iwp-site-creator-message');
        const $resultsDiv = $form.find('.iwp-site-creator-results');

        // Check status every 3 seconds
        const statusInterval = setInterval(function() {
            $.ajax({
                url: iwp_shortcode_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_check_task_status',
                    task_id: taskId,
                    nonce: iwp_shortcode_ajax.nonce_check_status
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const status = response.data.status;
                        
                        if (status === 'completed') {
                            clearInterval(statusInterval);
                            // Merge initial data with status response
                            const siteData = $.extend({}, initialData, response.data);
                            displaySiteResults(siteData, $container, $resultsDiv);
                            
                            // Show finalizing message and wait 5 seconds before applying post_options
                            $messageDiv.text(iwp_shortcode_ajax.messages.finalizing);
                            
                            setTimeout(function() {
                                applyDelayedPostOptions(siteData.site_id || siteData.id, $statusDiv, $messageDiv, $submitButton);
                            }, 5000);
                        } else if (status === 'failed') {
                            clearInterval(statusInterval);
                            showError('Site creation failed: ' + (response.data.message || 'Unknown error'), $statusDiv, $messageDiv, $submitButton);
                        }
                        // If still in progress, continue checking
                    } else {
                        // API error, but continue checking
                        console.log('Status check failed:', response.data);
                    }
                },
                error: function() {
                    // Network error, but continue checking
                    console.log('Network error checking status');
                }
            });
        }, 3000);

        // Stop checking after 5 minutes
        setTimeout(function() {
            clearInterval(statusInterval);
            showError('Site creation is taking longer than expected. Please check back later.', $statusDiv, $messageDiv, $submitButton);
        }, 300000); // 5 minutes
    }

    function displaySiteResults(data, $container, $resultsDiv) {
        // Populate site information
        if (data.site_url) {
            $container.find('.iwp-site-creator-site-url')
                .attr('href', data.site_url)
                .text(data.site_url);
        }
        
        if (data.admin_username) {
            $container.find('.iwp-site-creator-username').text(data.admin_username);
        }
        
        if (data.admin_password) {
            $container.find('.iwp-site-creator-password').text(data.admin_password);
        }
        
        // Handle Magic Login vs regular admin login
        if (data.s_hash) {
            // Use Magic Login
            const magicLoginUrl = 'https://app.instawp.io/wordpress-auto-login?site=' + encodeURIComponent(data.s_hash);
            $container.find('.iwp-site-creator-admin-url')
                .attr('href', magicLoginUrl)
                .text('Magic Login');
        } else if (data.admin_url) {
            // Use regular admin login
            $container.find('.iwp-site-creator-admin-url')
                .attr('href', data.admin_url)
                .text('Login to Admin');
        }

        // Show results
        $resultsDiv.show();
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $resultsDiv.offset().top - 50
        }, 500);
    }

    function copyToClipboard(text, $button) {
        if (navigator.clipboard && window.isSecureContext) {
            // Use modern Clipboard API
            navigator.clipboard.writeText(text).then(function() {
                showCopyFeedback($button, true);
            }).catch(function() {
                showCopyFeedback($button, false);
            });
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                showCopyFeedback($button, successful);
            } catch (err) {
                showCopyFeedback($button, false);
            }
            
            document.body.removeChild(textArea);
        }
    }

    function showCopyFeedback($button, success) {
        const originalText = $button.text();
        const feedbackText = success ? iwp_shortcode_ajax.messages.copy_success : iwp_shortcode_ajax.messages.copy_error;
        
        $button.text(feedbackText);
        
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    }

    function startProgressAnimation() {
        $('.iwp-site-creator-progress-bar').addClass('animating');
    }

    function stopProgressAnimation() {
        $('.iwp-site-creator-progress-bar').removeClass('animating');
    }

    function applyDelayedPostOptions(siteId, $statusDiv, $messageDiv, $submitButton) {
        if (!siteId) {
            console.error('No site ID provided for post_options application');
            showSuccess('Site created successfully!', $statusDiv, $messageDiv, $submitButton);
            setTimeout(function() {
                $statusDiv.hide();
            }, 3000);
            return;
        }

        $.ajax({
            url: iwp_shortcode_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iwp_apply_delayed_post_options',
                site_id: siteId,
                nonce: iwp_shortcode_ajax.nonce_apply_post_options
            },
            dataType: 'json',
            success: function(response) {
                if (response.data && response.data.warning) {
                    console.warn('Post options warning:', response.data.warning);
                }
                // Just hide the status div - don't show duplicate success message
                setTimeout(function() {
                    $statusDiv.hide();
                }, 1000);
            },
            error: function(xhr, status, error) {
                console.error('Post options AJAX error:', error);
                // Just hide the status div - site was still created successfully
                setTimeout(function() {
                    $statusDiv.hide();
                }, 1000);
            }
        });
    }
});