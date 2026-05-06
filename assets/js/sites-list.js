/**
 * Sites list page UI for the InstaWP Integration admin.
 *
 * Currently wires the Show/Hide toggle for the Password column rendered by
 * IWP_Sites_List_Table::column_password().
 *
 * @package IWP
 */

(function ($) {
    'use strict';

    var labels = (window.iwpSitesList && window.iwpSitesList.labels) || {
        show: 'Show',
        hide: 'Hide'
    };

    $(document).on('click', '.iwp-show-password', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $revealed = $btn.next('.iwp-password-revealed');
        if ($btn.text() === labels.show) {
            $revealed.text($btn.data('password'));
            $btn.text(labels.hide);
        } else {
            $revealed.text('');
            $btn.text(labels.show);
        }
    });
}(jQuery));
