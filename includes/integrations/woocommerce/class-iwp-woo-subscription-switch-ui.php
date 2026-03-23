<?php
/**
 * Subscription Switch UI Enhancement
 *
 * Transforms the grouped product quantity-based UI into a clean
 * radio-button plan selector when the user is switching subscriptions.
 *
 * @package IWP
 * @since 0.0.7
 */

if (!defined('ABSPATH')) {
    exit;
}

class IWP_Woo_Subscription_Switch_UI {

    /**
     * Whether we're on a subscription switch page
     *
     * @var bool
     */
    private $is_switch_page = false;

    /**
     * The product ID the user is currently subscribed to
     *
     * @var int|null
     */
    private $current_product_id = null;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp', array($this, 'detect_switch_page'));
    }

    /**
     * Detect if we're on a subscription switch page and set up hooks
     */
    public function detect_switch_page() {
        if (!isset($_GET['switch-subscription']) || !is_product()) {
            return;
        }

        if (!function_exists('wcs_get_subscription')) {
            return;
        }

        $subscription_id = absint($_GET['switch-subscription']);
        $item_id = isset($_GET['item']) ? absint($_GET['item']) : 0;

        $subscription = wcs_get_subscription($subscription_id);
        if (!$subscription) {
            return;
        }

        $this->is_switch_page = true;

        // Find the current product from the subscription item
        $items = $subscription->get_items();
        if ($item_id) {
            foreach ($items as $item) {
                if ($item->get_id() == $item_id) {
                    $this->current_product_id = $item->get_product_id();
                    break;
                }
            }
        }

        // Fallback: get the first item's product
        if (!$this->current_product_id && !empty($items)) {
            $first_item = reset($items);
            $this->current_product_id = $first_item->get_product_id();
        }

        // Set up UI hooks
        add_action('wp_head', array($this, 'output_switch_css'));
        add_action('wp_footer', array($this, 'output_switch_js'));
    }

    /**
     * Output inline CSS for the switch UI
     */
    public function output_switch_css() {
        if (!$this->is_switch_page) {
            return;
        }
        ?>
        <style id="iwp-switch-ui-css">
            /* Hide the default grouped product table layout */
            .iwp-switch-active .group_table {
                display: none !important;
            }

            /* Plan selector container */
            .iwp-plan-selector {
                display: flex;
                flex-direction: column;
                gap: 0;
                margin: 0 0 24px;
            }

            /* Individual plan option */
            .iwp-plan-option {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 16px 20px;
                border: 2px solid #e0e0e0;
                margin-top: -2px;
                cursor: pointer;
                transition: border-color 0.2s, background-color 0.2s;
                position: relative;
            }

            .iwp-plan-option:first-child {
                border-radius: 8px 8px 0 0;
                margin-top: 0;
            }

            .iwp-plan-option:last-child {
                border-radius: 0 0 8px 8px;
            }

            .iwp-plan-option:only-child {
                border-radius: 8px;
            }

            .iwp-plan-option:hover {
                border-color: #a0a0a0;
                z-index: 1;
            }

            .iwp-plan-option.iwp-selected {
                border-color: #2271b1;
                background-color: #f0f6fc;
                z-index: 2;
            }

            .iwp-plan-option.iwp-current-plan {
                background-color: #f9f9f9;
            }

            .iwp-plan-option.iwp-disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            /* Radio button */
            .iwp-plan-radio {
                appearance: none;
                -webkit-appearance: none;
                width: 20px;
                height: 20px;
                border: 2px solid #c0c0c0;
                border-radius: 50%;
                flex-shrink: 0;
                position: relative;
                cursor: pointer;
                transition: border-color 0.2s;
            }

            .iwp-plan-radio:checked {
                border-color: #2271b1;
            }

            .iwp-plan-radio:checked::after {
                content: '';
                position: absolute;
                top: 3px;
                left: 3px;
                width: 10px;
                height: 10px;
                background: #2271b1;
                border-radius: 50%;
            }

            /* Plan details */
            .iwp-plan-details {
                flex: 1;
                min-width: 0;
            }

            .iwp-plan-name {
                font-weight: 600;
                font-size: 15px;
                color: #1d2327;
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            /* Price */
            .iwp-plan-price {
                font-size: 15px;
                font-weight: 600;
                color: #1d2327;
                white-space: nowrap;
                text-align: right;
                min-width: 140px;
            }

            .iwp-plan-price .woocommerce-Price-amount {
                font-size: 15px;
            }

            /* Badges */
            .iwp-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.3px;
                line-height: 1.4;
            }

            .iwp-badge-current {
                background-color: #e0e0e0;
                color: #50575e;
            }

            .iwp-badge-upgrade {
                background-color: #dff0d8;
                color: #3c763d;
            }

            .iwp-badge-downgrade {
                background-color: #fcf8e3;
                color: #8a6d3b;
            }

            /* Switch button */
            .iwp-switch-active .single_add_to_cart_button {
                min-width: 200px;
            }

            /* Responsive */
            @media (max-width: 480px) {
                .iwp-plan-option {
                    padding: 12px 14px;
                    gap: 12px;
                }

                .iwp-plan-price {
                    min-width: auto;
                    font-size: 14px;
                }

                .iwp-plan-name {
                    font-size: 14px;
                }
            }
        </style>
        <?php
    }

    /**
     * Output inline JS for the switch UI
     */
    public function output_switch_js() {
        if (!$this->is_switch_page) {
            return;
        }

        $current_product_id = intval($this->current_product_id);
        ?>
        <script id="iwp-switch-ui-js">
        (function($) {
            'use strict';

            var currentProductId = <?php echo $current_product_id; ?>;

            $(function() {
                var $form = $('form.grouped_form');
                if (!$form.length) return;

                var $table = $form.find('.group_table');
                if (!$table.length) return;

                // Build plan data from the grouped product table
                var plans = [];
                var currentIndex = -1;

                $table.find('.woocommerce-grouped-product-list-item').each(function(index) {
                    var $row = $(this);
                    var productId = $row.attr('id').replace('product-', '');
                    var $label = $row.find('.woocommerce-grouped-product-list-item__label');
                    var $price = $row.find('.woocommerce-grouped-product-list-item__price');
                    var $qtyInput = $row.find('input[name^="quantity"]');
                    var name = $label.text().trim();
                    var priceHtml = $price.html();

                    plans.push({
                        productId: productId,
                        name: name,
                        priceHtml: priceHtml,
                        $qtyInput: $qtyInput,
                        index: index
                    });

                    if (parseInt(productId) === currentProductId) {
                        currentIndex = index;
                    }
                });

                if (!plans.length) return;

                // Build the new plan selector UI
                var $selector = $('<div class="iwp-plan-selector"></div>');

                $.each(plans, function(index, plan) {
                    var isCurrent = parseInt(plan.productId) === currentProductId;
                    var isUpgrade = currentIndex >= 0 && index > currentIndex;
                    var isDowngrade = currentIndex >= 0 && index < currentIndex;

                    var classes = 'iwp-plan-option';
                    if (isCurrent) classes += ' iwp-current-plan';

                    var $option = $('<div class="' + classes + '" data-product-id="' + plan.productId + '"></div>');

                    // Radio button
                    var $radio = $('<input type="radio" name="iwp_switch_plan" value="' + plan.productId + '" class="iwp-plan-radio" />');
                    if (isCurrent) {
                        $radio.prop('checked', true);
                        $option.addClass('iwp-selected');
                    }

                    // Plan name + badges
                    var $details = $('<div class="iwp-plan-details"></div>');
                    var $name = $('<div class="iwp-plan-name"></div>');
                    $name.append(document.createTextNode(plan.name));

                    if (isCurrent) {
                        $name.append('<span class="iwp-badge iwp-badge-current"><?php echo esc_js(__('Current plan', 'iwp-wp-integration')); ?></span>');
                    } else if (isUpgrade) {
                        $name.append('<span class="iwp-badge iwp-badge-upgrade"><?php echo esc_js(__('Upgrade', 'iwp-wp-integration')); ?></span>');
                    } else if (isDowngrade) {
                        $name.append('<span class="iwp-badge iwp-badge-downgrade"><?php echo esc_js(__('Downgrade', 'iwp-wp-integration')); ?></span>');
                    }

                    $details.append($name);

                    // Price
                    var $price = $('<div class="iwp-plan-price"></div>');
                    $price.html(plan.priceHtml);

                    $option.append($radio).append($details).append($price);
                    $selector.append($option);
                });

                // Insert the selector before the table
                $table.before($selector);

                // Mark form as switch-active to trigger CSS hiding of table
                $form.addClass('iwp-switch-active');

                // Set initial quantities: 1 for current plan, 0 for others
                updateQuantities(currentProductId);

                // Handle plan selection
                $selector.on('click', '.iwp-plan-option', function(e) {
                    if ($(e.target).is('.iwp-plan-radio')) return; // Let radio handle itself

                    var $option = $(this);
                    var $radio = $option.find('.iwp-plan-radio');
                    $radio.prop('checked', true).trigger('change');
                });

                $selector.on('change', '.iwp-plan-radio', function() {
                    var selectedId = $(this).val();

                    // Update visual state
                    $selector.find('.iwp-plan-option').removeClass('iwp-selected');
                    $(this).closest('.iwp-plan-option').addClass('iwp-selected');

                    // Update hidden quantity inputs
                    updateQuantities(selectedId);
                });

                function updateQuantities(selectedProductId) {
                    $.each(plans, function(index, plan) {
                        if (plan.$qtyInput.length) {
                            var val = (plan.productId == selectedProductId) ? 1 : 0;
                            plan.$qtyInput.val(val);
                        }
                    });
                }
            });
        })(jQuery);
        </script>
        <?php
    }
}
