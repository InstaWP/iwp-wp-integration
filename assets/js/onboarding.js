/**
 * IWP Post-Purchase Onboarding
 *
 * Handles the credential form, AJAX site creation, task polling,
 * and results display for the [iwp_onboarding] shortcode.
 *
 * @package IWP
 * @since 0.0.10
 */
(function($) {
    'use strict';

    var usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
    var subdomainRegex = /^[a-zA-Z0-9]([a-zA-Z0-9-]{1,28})[a-zA-Z0-9]$/;
    var config = typeof iwp_onboarding !== 'undefined' ? iwp_onboarding : {};
    var i18n = config.i18n || {};

    $(document).ready(function() {
        var $wrap = $('.iwp-onboarding-wrap');
        if (!$wrap.length) {
            return;
        }

        var orderId     = $wrap.data('order-id');
        var orderKey    = $wrap.data('order-key');
        var redirectUrl = $wrap.data('redirect') || '';

        // ---- Validation ----

        function validateField($input, regex, errorMsg) {
            var val = $input.val().trim();
            var $error = $input.closest('.iwp-onboarding-field').find('.iwp-onboarding-field-error');

            if (val === '') {
                $error.hide().text('');
                $input.removeClass('iwp-field-invalid iwp-field-valid');
                return true;
            }
            if (regex.test(val)) {
                $error.hide().text('');
                $input.removeClass('iwp-field-invalid').addClass('iwp-field-valid');
                return true;
            }
            $error.text(errorMsg).show();
            $input.removeClass('iwp-field-valid').addClass('iwp-field-invalid');
            return false;
        }

        // Real-time validation
        $wrap.on('input', 'input[name="admin_username"]', function() {
            validateField($(this), usernameRegex, i18n.username_invalid || 'Invalid username.');
        });

        $wrap.on('input', 'input[name="subdomain"]', function() {
            var $el = $(this);
            var val = $el.val();
            if (val !== val.toLowerCase()) {
                $el.val(val.toLowerCase());
            }
            validateField($el, subdomainRegex, i18n.subdomain_invalid || 'Invalid subdomain.');
        });

        // ---- Form submission ----

        $wrap.on('submit', '.iwp-onboarding-form', function(e) {
            e.preventDefault();

            var $form     = $(this);
            var $username = $form.find('input[name="admin_username"]');
            var $subdomain = $form.find('input[name="subdomain"]');
            var $submit   = $form.find('.iwp-onboarding-submit');
            var $progress = $form.find('.iwp-onboarding-progress');
            var $message  = $form.find('.iwp-onboarding-progress-message');
            var $results  = $form.find('.iwp-onboarding-results');
            var itemIndex = $form.data('item-index');

            // Validate
            var uValid = validateField($username, usernameRegex, i18n.username_invalid || 'Invalid username.');
            var sValid = validateField($subdomain, subdomainRegex, i18n.subdomain_invalid || 'Invalid subdomain.');
            if (!uValid || !sValid) {
                var $first = $form.find('.iwp-field-invalid').first();
                if ($first.length) {
                    $('html, body').animate({ scrollTop: $first.offset().top - 100 }, 300);
                }
                return;
            }

            // Disable form
            $submit.prop('disabled', true).text(i18n.creating || 'Creating...');
            $form.find('input').prop('readonly', true);
            $progress.show();
            $message.text(i18n.creating || 'Creating your site...');
            startProgressBar($form);

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action:         'iwp_onboarding_create_site',
                    nonce:          config.nonce,
                    order_id:       orderId,
                    order_key:      orderKey,
                    item_index:     itemIndex,
                    admin_username: $username.val().trim(),
                    subdomain:      $subdomain.val().trim()
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        handleCreationResponse(response.data, $form);
                    } else {
                        showFormError($form, response.data.message || i18n.error_generic);
                    }
                },
                error: function(xhr, status, error) {
                    showFormError($form, (i18n.error_generic || 'Network error') + ': ' + error);
                }
            });
        });

        // ---- Handle creation response ----

        function handleCreationResponse(data, $form) {
            var $message = $form.find('.iwp-onboarding-progress-message');

            if (data.status === 'completed') {
                stopProgressBar($form);
                displaySiteResults(data, $form);
            } else if ((data.status === 'progress' || data.status === 'in_progress') && data.task_id) {
                $message.text(i18n.checking_status || 'Setting up your site...');
                pollTaskStatus(data.task_id, data, $form);
            } else {
                showFormError($form, i18n.error_generic || 'Unknown status');
            }
        }

        // ---- Task polling ----

        function pollTaskStatus(taskId, initialData, $form) {
            var $message = $form.find('.iwp-onboarding-progress-message');
            var attempts = 0;
            var maxAttempts = 100; // ~5 min at 3s interval

            var interval = setInterval(function() {
                attempts++;
                if (attempts > maxAttempts) {
                    clearInterval(interval);
                    showFormError($form, 'Site creation is taking longer than expected. Please check your account page later.');
                    return;
                }

                $.ajax({
                    url: config.ajax_url,
                    type: 'POST',
                    data: {
                        action:  'iwp_check_task_status',
                        task_id: taskId,
                        nonce:   config.nonce_check_status
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (!response.success) return;

                        if (response.data.status === 'completed') {
                            clearInterval(interval);
                            var siteData = $.extend({}, initialData, response.data);
                            $message.text(i18n.almost_ready || 'Almost ready...');
                            setTimeout(function() {
                                stopProgressBar($form);
                                displaySiteResults(siteData, $form);
                            }, 1000);
                        } else if (response.data.status === 'failed') {
                            clearInterval(interval);
                            showFormError($form, response.data.message || 'Site creation failed.');
                        }
                        // Otherwise keep polling
                    },
                    error: function() {
                        // Silently retry on network hiccup
                    }
                });
            }, 3000);
        }

        // ---- Display results ----

        function displaySiteResults(data, $form) {
            var $results = $form.find('.iwp-onboarding-results');
            var $progress = $form.find('.iwp-onboarding-progress');
            var $fields = $form.find('.iwp-onboarding-field, .iwp-onboarding-form-actions, .iwp-onboarding-description');

            // Hide form fields and progress
            $fields.slideUp(200);
            $progress.slideUp(200);

            // Build results HTML
            var html = '<div class="iwp-onboarding-success">';
            html += '<h4>' + (i18n.success || 'Your site is ready!') + '</h4>';

            if (data.site_url) {
                html += '<div class="iwp-onboarding-detail"><strong>URL:</strong> ';
                html += '<a href="' + escHtml(data.site_url) + '" target="_blank">' + escHtml(data.site_url) + '</a></div>';
            }

            if (data.admin_username) {
                html += '<div class="iwp-onboarding-detail"><strong>Username:</strong> ';
                html += '<span>' + escHtml(data.admin_username) + '</span>';
                html += ' <button type="button" class="iwp-onboarding-copy-btn" data-copy-text="' + escAttr(data.admin_username) + '">Copy</button>';
                html += '</div>';
            }

            if (data.admin_password) {
                html += '<div class="iwp-onboarding-detail"><strong>Password:</strong> ';
                html += '<span class="iwp-onboarding-password-hidden">********</span>';
                html += '<span class="iwp-onboarding-password-value" style="display:none;">' + escHtml(data.admin_password) + '</span>';
                html += ' <button type="button" class="iwp-onboarding-toggle-pw">' + (i18n.show || 'Show') + '</button>';
                html += ' <button type="button" class="iwp-onboarding-copy-btn" data-copy-text="' + escAttr(data.admin_password) + '">Copy</button>';
                html += '</div>';
            }

            // Action buttons
            html += '<div class="iwp-onboarding-actions">';
            if (data.s_hash) {
                var magicUrl = 'https://app.instawp.io/wordpress-auto-login?site=' + encodeURIComponent(data.s_hash);
                html += '<a href="' + escHtml(magicUrl) + '" target="_blank" class="iwp-onboarding-btn iwp-onboarding-btn-primary">Magic Login</a> ';
            }
            if (data.site_url) {
                html += '<a href="' + escHtml(data.site_url) + '" target="_blank" class="iwp-onboarding-btn">Visit Site</a>';
            }
            html += '</div>';
            html += '</div>';

            $results.html(html).slideDown(300);

            // Update card class
            $form.closest('.iwp-onboarding-pending').removeClass('iwp-onboarding-pending').addClass('iwp-onboarding-completed');

            // Redirect if configured, otherwise scroll to results
            if (redirectUrl) {
                setTimeout(function() {
                    window.location.href = redirectUrl;
                }, 3000); // Show results briefly before redirecting
            } else {
                $('html, body').animate({ scrollTop: $results.offset().top - 80 }, 400);
            }
        }

        // ---- Error display ----

        function showFormError($form, message) {
            var $submit  = $form.find('.iwp-onboarding-submit');
            var $message = $form.find('.iwp-onboarding-progress-message');

            stopProgressBar($form);
            $submit.prop('disabled', false).text(i18n.retry || 'Try Again');
            $form.find('input').prop('readonly', false);
            $message.html('<span class="iwp-onboarding-error-text">' + escHtml(message) + '</span>');
        }

        // ---- Progress bar ----

        function startProgressBar($form) {
            $form.find('.iwp-onboarding-progress-bar').addClass('animating');
        }

        function stopProgressBar($form) {
            $form.find('.iwp-onboarding-progress-bar').removeClass('animating');
        }

        // ---- Copy / toggle password ----

        $wrap.on('click', '.iwp-onboarding-copy-btn', function(e) {
            e.preventDefault();
            var text = $(this).data('copy-text');
            var $btn = $(this);
            if (!text) return;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    flashBtn($btn, i18n.copy_success || 'Copied!');
                }).catch(function() {
                    flashBtn($btn, i18n.copy_fail || 'Failed');
                });
            } else {
                // Fallback
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); flashBtn($btn, i18n.copy_success || 'Copied!'); }
                catch (err) { flashBtn($btn, i18n.copy_fail || 'Failed'); }
                document.body.removeChild(ta);
            }
        });

        $wrap.on('click', '.iwp-onboarding-toggle-pw', function(e) {
            e.preventDefault();
            var $btn     = $(this);
            var $hidden  = $btn.closest('.iwp-onboarding-detail').find('.iwp-onboarding-password-hidden');
            var $value   = $btn.closest('.iwp-onboarding-detail').find('.iwp-onboarding-password-value');

            if ($value.is(':visible')) {
                $value.hide();
                $hidden.show();
                $btn.text(i18n.show || 'Show');
            } else {
                $value.show();
                $hidden.hide();
                $btn.text(i18n.hide || 'Hide');
            }
        });

        function flashBtn($btn, text) {
            var orig = $btn.text();
            $btn.text(text);
            setTimeout(function() { $btn.text(orig); }, 2000);
        }

        // ---- Helpers ----

        function escHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }

        function escAttr(str) {
            return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    });
})(jQuery);
