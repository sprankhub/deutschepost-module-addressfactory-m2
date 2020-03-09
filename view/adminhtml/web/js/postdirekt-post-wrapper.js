/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'Magento_Ui/js/modal/confirm',
    'mage/translate'
], function ($, confirm) {
    'use strict';
    console.log('upsala');
    /**
     * @param {String} url
     * @returns {Object}
     */
    function getForm(url) {
        return $('<form>', {
            'action': url,
            'method': 'POST'
        }).append($('<input>', {
            'name': 'form_key',
            'value': window.FORM_KEY,
            'type': 'hidden'
        }));
    }

    $('#order-view-addressfactory-unhold-button').click(function () {
        var url = $('#order-view-addressfactory-unhold-button').data('url');

        getForm(url).appendTo('body').submit();
    });

    $('#order-view-addressfactory-cancel-button').click(function () {
        var msg = $.mage.__('Are you sure you want to cancel this order?'),
            url = $('#order-view-addressfactory-cancel-button').data('url');

        confirm({
            'content': msg,
            'actions': {

                /**
                 * 'Confirm' action handler.
                 */
                confirm: function () {
                    getForm(url).appendTo('body').submit();
                }
            }
        });

        return false;
    });
});
