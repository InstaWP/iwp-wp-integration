/**
 * Admin Product JavaScript for IWP WooCommerce Integration v2
 *
 * @package IWP_Woo_V2
 * @since 2.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    // Handle site expiry type change
    $('input[name="_iwp_site_expiry_type"]').on('change', function() {
        var expiryType = $(this).val();
        var $expiryHoursField = $('._iwp_site_expiry_hours_field');
        
        if (expiryType === 'temporary') {
            $expiryHoursField.show();
        } else {
            $expiryHoursField.hide();
        }
    });
    
    // Initialize expiry field visibility on page load
    var initialExpiryType = $('input[name="_iwp_site_expiry_type"]:checked').val();
    if (initialExpiryType === 'temporary') {
        $('._iwp_site_expiry_hours_field').show();
    } else {
        $('._iwp_site_expiry_hours_field').hide();
    }

    // Handle snapshot refresh button
    $('#iwp-refresh-product-snapshots').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        
        // Show loading state
        $button.text(iwp_product_admin.strings.refreshing).prop('disabled', true);
        
        // Clear snapshots cache and refresh dropdown
        $.ajax({
            url: iwp_product_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'iwp_refresh_product_snapshots',
                nonce: iwp_product_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Refresh the page to reload the dropdown with new snapshots
                    location.reload();
                } else {
                    alert(iwp_product_admin.strings.error + ': ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert(iwp_product_admin.strings.error);
            },
            complete: function() {
                // Restore button state
                $button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Handle plans refresh button
    $('#iwp-refresh-product-plans').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        
        // Show loading state
        $button.text(iwp_product_admin.strings.refreshing_plans).prop('disabled', true);
        
        // Clear plans cache and refresh dropdown
        $.ajax({
            url: iwp_product_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'iwp_refresh_product_plans',
                nonce: iwp_product_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Refresh the page to reload the dropdown with new plans
                    location.reload();
                } else {
                    alert(iwp_product_admin.strings.plans_error + ': ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert(iwp_product_admin.strings.plans_error);
            },
            complete: function() {
                // Restore button state
                $button.text(originalText).prop('disabled', false);
            }
        });
    });

    // Handle snapshot selection change
    $('#_iwp_selected_snapshot').on('change', function() {
        var snapshotSlug = $(this).val();
        var $previewContainer = $('#iwp-snapshot-preview');
        
        if (snapshotSlug) {
            // Show loading state
            $previewContainer.html('<p>Loading snapshot preview...</p>');
            
            // Load snapshot preview
            $.ajax({
                url: iwp_product_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'iwp_get_snapshot_preview',
                    snapshot_slug: snapshotSlug,
                    nonce: iwp_product_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $previewContainer.html(response.data);
                    } else {
                        $previewContainer.html('<p>Error loading snapshot preview.</p>');
                    }
                },
                error: function() {
                    $previewContainer.html('<p>Error loading snapshot preview.</p>');
                }
            });
        } else {
            $previewContainer.empty();
        }
    });

});
