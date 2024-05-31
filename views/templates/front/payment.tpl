{**
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
 *}

<div id="comfino-iframe-container">{$paywall_iframe nofilter}</div>
<script>
    window.Comfino = {
        paywallOptions: {$paywall_options|@json_encode nofilter},
        init: () => {
            let iframe = document.getElementById('comfino-paywall-container');
            let frontendInitElement = document.querySelector('input[data-module-name="comfino"]');

            if ('priceModifier' in frontendInitElement.dataset) {
                let priceModifier = parseInt(frontendInitElement.dataset.priceModifier);

                if (!Number.isNaN(priceModifier)) {
                    iframe.src += ('&priceModifier=' + priceModifier);
                }
            }

            ComfinoPaywallFrontend.init(
                frontendInitElement,
                document.getElementById('comfino-paywall-container'),
                Comfino.paywallOptions
            );
        }
    }

    if (ComfinoPaywallFrontend.isInitialized()) {
        Comfino.init();
    } else {
        Comfino.paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
            ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', loanParams);

            let paymentStateUrl = '{$payment_state_url|escape:"url"}';
            let urlParams = new URLSearchParams({
                loan_amount: loanParams.loanAmount,
                loan_type: loanParams.loanType,
                loan_term: loanParams.loanTerm
            });

            paymentStateUrl += (paymentStateUrl.indexOf('?') > 0 ? '&' : '?') + urlParams.toString();

            fetch(paymentStateUrl, { method: 'POST' }).then(response => {
                ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', paymentStateUrl, response);
            });
        }

        if (document.readyState === 'complete') {
            Comfino.init();
        } else {
            document.addEventListener('readystatechange', () => {
                if (document.readyState === 'complete') {
                    Comfino.init();
                }
            });
        }
    }
</script>
