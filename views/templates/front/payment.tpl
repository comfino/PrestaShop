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

{if $is_ps_16}
<style>
    a.comfino-payment-method {
        padding: 25px 20px !important;
        background-color: #FFFFFF !important;
        color: #000000 !important;
        cursor: pointer;
        border: 1px solid #d6d4d4;
    }

    a.comfino-payment-method:hover {
        background-color: #f6f6f6;
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

    a.comfino-payment-method::before {
        position: static !important;
        display: inline !important;
        margin-right: 10px;
    }
</style>
<div class="row">
    <div class="col-xs-12 col-md-12">
        <p class="payment_module">
            <a id="pay-with-comfino" class="comfino-payment-method">
                <img class="comfino-image" style="height: 49px" src="{$comfino_logo_url|escape:"htmlall":"UTF-8"}" alt="{l s="Pay with comfino" mod="comfino"}" loading="lazy" onload="ComfinoPaywallFrontend.onload(this, '{$paywall_options.platformName|escape:"htmlall":"UTF-8"}', '{$paywall_options.platformVersion|escape:"htmlall":"UTF-8"}')" />
                {$comfino_label|escape:"htmlall":"UTF-8"}
            </a>
        </p>
    </div>
</div>
{/if}
<div id="comfino-iframe-container">{$paywall_iframe nofilter}</div>
{if $is_ps_16}
<div id="comfino-payment-bar" class="comfino-payment-bar">
    <a id="comfino-go-to-payment" href="{$comfino_redirect_url|escape:"htmlall":"UTF-8"}" class="comfino-payment-btn">
        {l s="Go to payment" mod="comfino"}
    </a>
</div>
{/if}
<script>
    window.Comfino = {
        paywallOptions: {$paywall_options|@json_encode nofilter},
        init: () => {
            let iframe = document.getElementById('comfino-paywall-container');
            let frontendInitElement = {if $is_ps_16}document.getElementById('pay-with-comfino'){else}document.querySelector('input[data-module-name^="comfino"]'){/if};

            if ('priceModifier' in frontendInitElement.dataset) {
                let priceModifier = parseInt(frontendInitElement.dataset.priceModifier);

                if (!Number.isNaN(priceModifier)) {
                    iframe.src += ('&priceModifier=' + priceModifier);
                }
            }

            ComfinoPaywallFrontend.init(frontendInitElement, iframe, Comfino.paywallOptions);
        }
    }

    if (ComfinoPaywallFrontend.isInitialized()) {
        Comfino.init();
    } else {
        Comfino.paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
            ComfinoPaywallFrontend.logEvent('updateOrderPaymentState PrestaShop', 'debug', loanParams);

            const url = new URL('{$payment_state_url nofilter}');
            const urlParams = {
                loan_amount: loanParams.loanAmount,
                loan_type: loanParams.loanType,
                loan_term: loanParams.loanTerm
            };

            for (let paramName in urlParams) {
                url.searchParams.append(paramName, urlParams[paramName]);
            }

            const paymentStateUrl = url.toString();

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
