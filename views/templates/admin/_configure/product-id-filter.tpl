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
 * Template for the collapsible product ID filter (global blacklist).
 *
 * A single text field where the admin enters comma-separated product IDs that should
 * disable all Comfino financial products when present in the cart.
 *
 * Variables:
 *   {$product_ids} string Comma-separated currently excluded product IDs
 *}

<details class="comfino-filter-accordion">
    <summary style="cursor: pointer; font-size: 14px; font-weight: 600; padding: 8px 0;">{l s='Filter by product ID' mod='comfino'}</summary>
    <div style="padding: 8px 0 0 0;">
        <p class="help-block">
            {l s='Enter product IDs (separated by commas) for which Comfino payment options should not be offered. If the cart contains any of the listed products, all Comfino financial products will be hidden at checkout.' mod='comfino'}
        </p>
        <textarea id="comfino_product_id_filter" name="comfino_product_id_filter" rows="3" class="form-control" style="width: 100%; max-width: 600px;" placeholder="{l s='e.g. 12, 45, 108' mod='comfino'}">{$product_ids}</textarea>
    </div>
</details>
