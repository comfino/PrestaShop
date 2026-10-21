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
{extends file="helpers/form/form.tpl"}

{block name="input"}
    {if $input.type == 'password' && isset($input.api_key_saved)}
        {* API key fields: never pre-filled, not autofilled with the employee's password, and not required in the
           browser once a key is saved - an empty field keeps the saved key. *}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="icon-key"></i>
            </span>
            <input type="password"
                id="{if isset($input.id)}{$input.id|escape:'htmlall':'UTF-8'}{else}{$input.name|escape:'htmlall':'UTF-8'}{/if}"
                name="{$input.name|escape:'htmlall':'UTF-8'}"
                class="{if isset($input.class)}{$input.class|escape:'htmlall':'UTF-8'}{/if}"
                value=""
                autocomplete="new-password"
                {if isset($input.placeholder) && !$input.api_key_saved}placeholder="{$input.placeholder|escape:'htmlall':'UTF-8'}"{/if}
                {if isset($input.required) && $input.required && !$input.api_key_saved}required="required"{/if} />
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}
