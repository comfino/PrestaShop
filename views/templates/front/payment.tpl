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

{*
 * Template for the Comfino payment method fields in checkout.
 *
 * Rendered by Main::renderPaywallIframe() as the container for the paywall. The CDN-hosted checkout glue script
 * (comfino-prestashop.min.js) locates #comfino-paywall-container and renders the paywall iframe inside it.
 * Hidden inputs carry the selected loan type and term to the order submit handler.
 *}
{if $is_ps_16}
<div class="row">
    <div class="col-xs-12 col-md-12">
        <p class="payment_module comfino">
            <label id="pay-with-comfino" class="comfino-payment-method">
                {$comfino_label|escape:"htmlall":"UTF-8"}
            </label>
        </p>
    </div>
</div>
{* PS 1.6: form carries the SDK-populated hidden inputs to the payment controller via POST.
   PS 1.7+: the inputs below are rendered inside PrestaShop's own payment option form. *}
<form id="comfino-payment-form" method="post" action="{$comfino_redirect_url|escape:'htmlall':'UTF-8'}">
{/if}

{* Cart total in grosze; initial value set server-side, refreshed on cart/shipping changes by PrestaShopPaywallController. *}
<input id="comfino-loan-amount" name="comfino_loan_amount" type="hidden" value="{$loan_amount|intval}" />
{* Loan parameters written by PrestaShopAdapter.updatePaymentState(), read on order placement. *}
<input id="comfino-loan-type" name="comfino_loan_type" type="hidden" value="" />
<input id="comfino-loan-term" name="comfino_loan_term" type="hidden" value="" />
{* Comfino web frontend SDK renders paywall iframe here. *}
<div id="comfino-paywall-container"></div>

{if $is_ps_16}
<div id="comfino-payment-bar" class="comfino-payment-bar">
    <button type="submit" id="comfino-go-to-payment" class="comfino-payment-btn">
        {l s="Go to payment" mod="comfino"}
    </button>
</div>
</form>
{/if}

<script type="application/json" id="comfino-checkout-config">{$comfino_settings|@json_encode nofilter}</script>
<script data-cfasync="false" src="{$checkout_script_url|escape:'htmlall':'UTF-8'}" data-comfino-checkout="1" crossorigin="anonymous"{if isset($script_nonce) && $script_nonce !== ''} nonce="{$script_nonce|escape:'javascript'}"{/if}></script>
