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

        document
            .getElementById('comfino-iframe-container')
            ?.querySelector('#comfino-paywall-container')
            ?.remove();

        if (iframeContainer == null) {
            ComfinoPaywallFrontend.logEvent('Comfino paywall iframe container not found.', 'warning');

            return;
        }

        if (iframeContainer.querySelector('#comfino-paywall-container') !== null && ComfinoPaywallFrontend.isFrontendInitSet()) {
            ComfinoPaywallFrontend.logEvent('Comfino paywall iframe already initialized.', 'info', iframeContainer);

            return;
        }

        const iframe = ComfinoPaywallFrontend.createPaywallIframe(ComfinoPaywallData.paywallUrl, ComfinoPaywallData.paywallOptions);
        const frontendInitElement = document.getElementById('pay-with-comfino') ?? document.querySelector('input[data-module-name^="comfino"]');

        let priceModifier = 0;

        if ('priceModifier' in frontendInitElement.dataset) {
            priceModifier = parseInt(frontendInitElement.dataset.priceModifier);

            if (!Number.isNaN(priceModifier)) {
                iframe.src += ('&priceModifier=' + priceModifier);
            } else {
                priceModifier = 0;
            }
        }

        ComfinoPaywallData.paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
            ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', loanParams);

            const url = new URL(ComfinoPaywallData.paywallStateUrl);
            const urlSearchParams = new FormData();
            const urlParams = {
                loan_amount: loanParams.loanAmount,
                loan_type: loanParams.loanType,
                loan_term: loanParams.loanTerm,
                price_modifier: priceModifier
            };

            for (let paramName in urlParams) {
                urlSearchParams.append(paramName, urlParams[paramName]);
            }

            const paymentStateUrl = url.toString();

            fetch(paymentStateUrl, { method: 'POST', body: urlSearchParams }).then(response => {
                ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', paymentStateUrl, response);
            });
        }

        iframeContainer.appendChild(iframe);

        ComfinoPaywallFrontend.init(frontendInitElement, iframe, ComfinoPaywallData.paywallOptions);
    },
    initWithObserver: () => {
        ComfinoPaywallInit.init();

        if (ComfinoPaywallFrontend.isFrontendInitSet()) {
            return;
        }
function initComfinoPaywallWithObserver() {
    const frontendInitElement =
        document.getElementById('pay-with-comfino') ??
        document.querySelector('input[data-module-name^="comfino"]');

    ComfinoPaywallInit.init();

    if (ComfinoPaywallFrontend.isFrontendInitSet()) {
        return;
    }

    if (frontendInitElement) {
        ComfinoPaywallFrontend.logEvent("Paywall initialization with 'display' prop observer", 'debug');

        const observer = new MutationObserver(() => {
            const display = getComputedStyle(frontendInitElement).display;
            if (display === 'block') {
                ComfinoPaywallInit.init();
                if (ComfinoPaywallFrontend.isFrontendInitSet()) {
                    observer.disconnect();
                }
            }
        });

        observer.observe(frontendInitElement, {
            attributes: true,
            attributeFilter: ['style'],
        });
    }
}

if (document.readyState === 'complete') {
    initComfinoPaywallWithObserver()
} else {
    document.addEventListener('readystatechange', () => {
        if (document.readyState === 'complete') {
            initComfinoPaywallWithObserver()
            ComfinoPaywallInit.initWithObserver();
        }
    });
}
