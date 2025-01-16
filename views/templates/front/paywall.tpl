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
<!DOCTYPE html>
<html lang="{$language|escape:"htmlall":"UTF-8"}">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{l s="Comfino - installment and deferred on-line payments" mod="comfino"}</title>
        {foreach from=$styles item=style}
            <link rel="stylesheet" href="{$style|escape:"htmlall":"UTF-8"}" media="all">
        {/foreach}
        {foreach from=$scripts item=script}
            <script src="{$script|escape:"htmlall":"UTF-8"}" data-cmp-ab="2"></script>
        {/foreach}
    </head>
    <body>
        <div id="paywall-container"></div>
        <script data-cmp-ab="2">ComfinoPaywall.init('{$shop_url|escape:"htmlall":"UTF-8"}', document.location.href, '{$paywall_hash|escape:"htmlall":"UTF-8"}', document.getElementById('paywall-container'), {$frontend_elements|@json_encode nofilter});</script>
    </body>
</html>
