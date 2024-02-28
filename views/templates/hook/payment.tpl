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

<style>
    a.comfino-payment-method {
        padding: 25px 20px !important;
        cursor: pointer;
    }

    a.comfino-payment-method:after {
        width: 14px;
        height: 22px;
        display: block;
        content: "\f078";
        font-family: 'FontAwesome';
        font-size: 25px;
        color: #777777;
        position: absolute;
        right: 25px;
        margin-top: -11px;
        top: 50%;
    }
</style>
<div class="row">
    <div class="col-xs-12 col-md-12">
        <p class="payment_module">
            <a id="pay-with-comfino" class="comfino-payment-method">
                <img style="height: 49px" src="{$logo_url}" alt="{l s="Pay with comfino" mod="comfino"}" loading="lazy" onload="ComfinoPaywallFrontend.onload(this, '{$paywall_options.platformName|escape:"htmlall":"UTF-8"}', '{$paywall_options.platformVersion|escape:"htmlall":"UTF-8"}')" />
                {$pay_with_comfino_text|escape:"htmlall":"UTF-8"}
            </a>
        </p>
    </div>
</div>
<iframe id="comfino-paywall-container" src="{$paywall_api_url}" referrerpolicy="strict-origin" loading="lazy" class="comfino-paywall" scrolling="no" onload="ComfinoPaywallFrontend.onload(this, '{$paywall_options.platformName|escape:"htmlall":"UTF-8"}', '{$paywall_options.platformVersion|escape:"htmlall":"UTF-8"}')"></iframe>
<div id="comfino-payment-bar" class="comfino-payment-bar">
    <a id="comfino-go-to-payment" href="{$go_to_payment_url|escape:"htmlall":"UTF-8"}" class="comfino-payment-btn">
        {l s="Go to payment" mod="comfino"}
    </a>
</div>
<script>
    document.addEventListener('readystatechange', () => {
        if (document.readyState === 'complete') {
            let paywallOptions = {$paywall_options|@json_encode nofilter};

            paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
                ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', loanParams);

                let offersUrl = '{$offers_url}'.replace(/&amp;/g, '&');
                let urlParams = new URLSearchParams({ loan_type: loanParams.loanType, loan_term: loanParams.loanTerm });

                offersUrl += (offersUrl.indexOf('?') > 0 ? '&' : '?') + urlParams.toString();

                fetch(offersUrl, { method: 'POST' }).then(response => {
                    ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', offersUrl, response);
                });
            }

            ComfinoPaywallFrontend.init(
                document.getElementById('pay-with-comfino'),
                document.getElementById('comfino-paywall-container'),
                paywallOptions
            );
        }
    });
</script>
