/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

jQuery(function ($) {
    const paymentTextEnabledInput = $('#COMFINO_PAYMENT_TEXT_ENABLED_on');
    const paymentTextDisabledInput = $('#COMFINO_PAYMENT_TEXT_ENABLED_off');
    const paymentTextInput = $('#COMFINO_PAYMENT_TEXT');

    if (paymentTextEnabledInput.length && paymentTextDisabledInput.length && paymentTextInput.length) {
        const togglePaymentTextInput = function () {
            paymentTextInput.prop('disabled', !paymentTextEnabledInput.is(':checked'));
        };

        togglePaymentTextInput();

        paymentTextEnabledInput.on('change', togglePaymentTextInput);
        paymentTextDisabledInput.on('change', togglePaymentTextInput);
    }

    const maxSelectGroup = $('.js-comfino-max-select-2');

    if (maxSelectGroup.length) {
        const enforceMaxSelectLimit = function () {
            const limitReached = maxSelectGroup.filter(':checked').length >= 2;

            maxSelectGroup.not(':checked').prop('disabled', limitReached);
        };

        maxSelectGroup.on('change', enforceMaxSelectLimit);

        enforceMaxSelectLimit();
    }
});
