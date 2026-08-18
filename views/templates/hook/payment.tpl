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
 * Comfino payment method fields in checkout (PrestaShop 1.6).
 *
 * The CDN checkout script (comfino-prestashop.min.js) reads the JSON configuration block, imports the ESM SDK
 * and renders the paywall into #comfino-paywall-container. PrestaShop 1.6 has no payment option form of its
 * own, so the hidden inputs the SDK populates are submitted by the form below.
 *
 * The tile layout, the logo swap and the loading state are driven by the SDK stylesheet served from the same
 * CDN (comfino-item-gate-prestashop.css), registered in hookHeader().
 *}
<div class="row">
    <div class="col-xs-12 col-md-12">
        <p class="payment_module comfino">
            <label id="pay-with-comfino" class="comfino-payment-method">
                {* Placeholder logo adopted by the SDK, which swaps its source for the authorized one. *}
                <img class="comfino-payment-method-item__logo" data-comfino-logo src="{$comfino_default_logo_url|escape:'htmlall':'UTF-8'}" alt="{$pay_with_comfino_text|escape:'htmlall':'UTF-8'}" />
                {$pay_with_comfino_text|escape:'htmlall':'UTF-8'}
                {* Loading skeleton covering the tile until the SDK marks it ready. *}
                <span class="comfino-payment-method-item__loader" aria-hidden="true">
                    <span class="comfino-payment-method-item__loader-spinner"><span></span></span>
                </span>
            </label>
        </p>
    </div>
</div>

<form id="comfino-payment-form" method="post" action="{$go_to_payment_url|escape:'htmlall':'UTF-8'}">
    {* Cart total in grosze. *}
    <input id="comfino-loan-amount" name="comfino_loan_amount" type="hidden" value="{$loan_amount|intval}" />
    {* Loan parameters written by the SDK, read on order placement. *}
    <input id="comfino-loan-type" name="comfino_loan_type" type="hidden" value="" />
    <input id="comfino-loan-term" name="comfino_loan_term" type="hidden" value="" />
    {* The SDK renders the paywall iframe here. *}
    <div id="comfino-paywall-container"></div>

    <div id="comfino-payment-bar" class="comfino-payment-bar">
        {* Enabled by the SDK once the customer selects a financing offer. *}
        <button type="submit" id="comfino-go-to-payment" class="comfino-payment-btn" disabled="disabled">
            {l s="Go to payment" mod="comfino"}
        </button>
    </div>
</form>

<script type="application/json" id="comfino-checkout-config">{$comfino_settings_json nofilter}</script>
<script data-cfasync="false" src="{$checkout_script_url|escape:'htmlall':'UTF-8'}" data-comfino-checkout="1" crossorigin="anonymous"></script>