/**
 * IWP Product Fields - Client-side validation
 *
 * @package IWP
 * @since 0.0.4
 */
(function($) {
    'use strict';

    var usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
    var subdomainRegex = /^[a-zA-Z0-9]([a-zA-Z0-9-]{1,28})[a-zA-Z0-9]$/;

    function validateField(input, regex, errorEl, msg) {
        var val = input.val().trim();
        if (val === '') {
            // Optional field, clear any error
            errorEl.text('').hide();
            input.removeClass('iwp-field-invalid iwp-field-valid');
            return true;
        }
        if (regex.test(val)) {
            errorEl.text('').hide();
            input.removeClass('iwp-field-invalid').addClass('iwp-field-valid');
            return true;
        }
        errorEl.text(msg).show();
        input.removeClass('iwp-field-valid').addClass('iwp-field-invalid');
        return false;
    }

    $(document).ready(function() {
        var $username = $('#iwp_admin_username');
        var $subdomain = $('#iwp_subdomain');
        var $usernameError = $('#iwp_admin_username_error');
        var $subdomainError = $('#iwp_subdomain_error');
        var i18n = (typeof iwp_product_fields !== 'undefined') ? iwp_product_fields.i18n : {};

        if (!$username.length && !$subdomain.length) {
            return;
        }

        // Real-time validation on input
        $username.on('input', function() {
            validateField($username, usernameRegex, $usernameError, i18n.username_invalid || 'Invalid username.');
        });

        $subdomain.on('input', function() {
            // Auto-lowercase
            var val = $(this).val();
            if (val !== val.toLowerCase()) {
                $(this).val(val.toLowerCase());
            }
            validateField($subdomain, subdomainRegex, $subdomainError, i18n.subdomain_invalid || 'Invalid subdomain.');
        });

        // Prevent add-to-cart submission if invalid
        $('form.cart').on('submit', function(e) {
            var usernameValid = validateField($username, usernameRegex, $usernameError, i18n.username_invalid || 'Invalid username.');
            var subdomainValid = validateField($subdomain, subdomainRegex, $subdomainError, i18n.subdomain_invalid || 'Invalid subdomain.');

            if (!usernameValid || !subdomainValid) {
                e.preventDefault();
                // Scroll to first error
                var $firstError = $('.iwp-field-invalid').first();
                if ($firstError.length) {
                    $('html, body').animate({ scrollTop: $firstError.offset().top - 100 }, 300);
                }
                return false;
            }
        });
    });
})(jQuery);
