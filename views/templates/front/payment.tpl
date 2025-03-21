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
        font-family: 'FontAwesome', serif;
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
<div id="comfino-iframe-container" class="comfino-iframe-container"></div>
{if $is_ps_16}
<div id="comfino-payment-bar" class="comfino-payment-bar">
    <a id="comfino-go-to-payment" href="{$comfino_redirect_url|escape:"htmlall":"UTF-8"}" class="comfino-payment-btn">
        {l s="Go to payment" mod="comfino"}
    </a>
</div>
{/if}
<script data-cmp-ab="2">window.ComfinoPaywallData = { paywallUrl: '{$paywall_url|escape:"htmlall":"UTF-8"}'.replaceAll('&amp;', '&'), paywallStateUrl: '{$payment_state_url|escape:"htmlall":"UTF-8"}'.replaceAll('&amp;', '&'), paywallOptions: {$paywall_options|@json_encode nofilter} }; if (typeof ComfinoPaywallInit === 'object') ComfinoPaywallInit.init();</script>
