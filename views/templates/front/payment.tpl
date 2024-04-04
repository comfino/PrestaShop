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

<div id="comfino-iframe-container"></div>
<script>
    window.Comfino = {
        paywallOptions: {$paywall_options|@json_encode nofilter},
        init: () => {
            var iframe = document.createElement('iframe');
            iframe.id = 'comfino-paywall-container';
            iframe.className = 'comfino-paywall';
            iframe.onload = function () {
                ComfinoPaywallFrontend.onload(
                    this,
                    '{$paywall_options.platformName|escape:"htmlall":"UTF-8"}',
                    '{$paywall_options.platformVersion|escape:"htmlall":"UTF-8"}'
                );
            };
            iframe.src = '{$paywall_api_url}';
            iframe.referrerPolicy = 'strict-origin';
            iframe.loading = 'lazy';
            iframe.scrolling = 'no';

            document.getElementById('comfino-iframe-container').appendChild(iframe);

            ComfinoPaywallFrontend.init(
                document.querySelector('input[data-module-name="comfino"]'),
                document.getElementById('comfino-paywall-container'),
                Comfino.paywallOptions
            );
        }
    }

    var script = document.createElement('script');
    script.onload = function () {
        if (ComfinoPaywallFrontend.isInitialized()) {
            Comfino.init();
        } else {
            Comfino.paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
                ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', loanParams);

                let offersUrl = '{$offers_url}'.replace(/&amp;/g, '&');
                let urlParams = new URLSearchParams({ loan_type: loanParams.loanType, loan_term: loanParams.loanTerm });

                offersUrl += (offersUrl.indexOf('?') > 0 ? '&' : '?') + urlParams.toString();

                fetch(offersUrl, { method: 'POST' }).then(response => {
                    ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', offersUrl, response);
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
    };
    script.src = '{$paywall_script_url}';
    script.async = true;
    document.getElementsByTagName('head')[0].appendChild(script);
</script>
