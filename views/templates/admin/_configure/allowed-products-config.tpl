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
 * Template for installment term limits configuration per financial product type.
 *
 * Variables:
 *   {$product_types} array [typeCode => typeName, ...]
 *   {$saved_config}  array [typeCode => ['maxTerm' => int|null, 'minTerm' => int|null, 'terms' => int[]|null, 'termsStr' => string], ...]
 *}

<div class="comfino-term-limits-wrapper" style="margin-top:15px">
    <h3>{l s='Installment term limits' mod='comfino'}</h3>
    <table class="table comfino-term-limits-table">
        <thead>
            <tr>
                <th>{l s='Product type' mod='comfino'}</th>
                <th>{l s='Min term (months)' mod='comfino'}</th>
                <th>{l s='Max term (months)' mod='comfino'}</th>
                <th>{l s='Specific terms (comma-separated)' mod='comfino'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach $product_types as $typeCode => $typeName}
                {assign var='saved' value=$saved_config[$typeCode]|default:[]}
                {assign var='minTerm' value=$saved.minTerm|default:''}
                {assign var='maxTerm' value=$saved.maxTerm|default:''}
                {assign var='termsStr' value=$saved.termsStr|default:''}
                <tr>
                    <td>
                        <strong>{$typeName|escape:'htmlall':'UTF-8'}</strong><br>
                        <code>{$typeCode|escape:'htmlall':'UTF-8'}</code>
                    </td>
                    <td>
                        <input type="number" min="1" max="999" name="comfino_term_limits[{$typeCode|escape:'htmlall':'UTF-8'}][minTerm]" {if $minTerm !== ''}value="{$minTerm|intval}"{/if}placeholder="{l s='No limit' mod='comfino'}" style="width:80px" />
                    </td>
                    <td>
                        <input type="number" min="1" max="999" name="comfino_term_limits[{$typeCode|escape:'htmlall':'UTF-8'}][maxTerm]" {if $maxTerm !== ''}value="{$maxTerm|intval}"{/if}placeholder="{l s='No limit' mod='comfino'}" style="width:80px" />
                    </td>
                    <td>
                        <input type="text" name="comfino_term_limits[{$typeCode|escape:'htmlall':'UTF-8'}][terms]" value="{$termsStr|escape:'htmlall':'UTF-8'}" placeholder="{l s='e.g. 6,12,24,36' mod='comfino'}" style="width:200px" />
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
    <p class="help-block">
        {l s='Leave fields empty to apply no restriction for that product type. "Specific terms" overrides min/max when both are set.' mod='comfino'}
    </p>
</div>
