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
window.ComfinoPaywallInit = {
    init: () => {
        const iframeContainer = document.getElementById('comfino-iframe-container');

        if (iframeContainer.querySelector('#comfino-paywall-container') !== null) {
            ComfinoPaywallFrontend.logEvent('Comfino paywall iframe already initialized.', 'info', iframeContainer);

            return;
        }

        ComfinoPaywallData.paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
            ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', loanParams);

            const url = new URL(ComfinoPaywallData.paywallStateUrl);
            const urlSearchParams = new FormData();
            const urlParams = {
                loan_amount: loanParams.loanAmount,
                loan_type: loanParams.loanType,
                loan_term: loanParams.loanTerm
            };

            for (let paramName in urlParams) {
                urlSearchParams.append(paramName, urlParams[paramName]);
            }

            const paymentStateUrl = url.toString();

            fetch(paymentStateUrl, { method: 'POST', body: urlSearchParams }).then(response => {
                ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', paymentStateUrl, response);
            });
        }

        const iframe = ComfinoPaywallFrontend.createPaywallIframe(ComfinoPaywallData.paywallUrl, ComfinoPaywallData.paywallOptions);
        const frontendInitElement = document.getElementById('pay-with-comfino') ?? document.querySelector('input[data-module-name^="comfino"]');

        if ('priceModifier' in frontendInitElement.dataset) {
            let priceModifier = parseInt(frontendInitElement.dataset.priceModifier);

            if (!Number.isNaN(priceModifier)) {
                iframe.src += ('&priceModifier=' + priceModifier);
            }
        }

        iframeContainer.appendChild(iframe);

        ComfinoPaywallFrontend.init(frontendInitElement, iframe, ComfinoPaywallData.paywallOptions);
    }
}

if (document.readyState === 'complete') {
    ComfinoPaywallInit.init();
} else {
    document.addEventListener('readystatechange', () => {
        if (document.readyState === 'complete') {
            ComfinoPaywallInit.init();
        }
    });
}
