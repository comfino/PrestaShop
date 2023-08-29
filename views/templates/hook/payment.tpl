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

<div class="row">
    <div class="col-xs-12 col-md-12">
        <p class="payment_module">
            <a id="pay-with-comperia" class="comfino-payment-method">
                {if $presentation_type == "only_icon" || $presentation_type == "icon_and_text"}
                    <img style="height: 49px" src="//widget.comfino.pl/image/comfino/ecommerce/prestashop/comfino_logo.svg" alt="{l s="Pay with comfino" mod="comfino"}" />
                {/if}
                {if $presentation_type == "only_text" || $presentation_type == "icon_and_text"}
                    {$pay_with_comfino_text|escape:"htmlall":"UTF-8"}
                {/if}
            </a>
        </p>
    </div>
</div>
<div id="comfino-container"></div>
<script>
    Comfino.options = {$frontend_renderer_options|@json_encode nofilter};
    Comfino.options.frontendInitElement = document.getElementById('pay-with-comperia');
    Comfino.options.frontendTargetElement = document.getElementById('comfino-box');
    Comfino.init('{$frontend_script_url|escape:"javascript":"UTF-8"}');
</script>
