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

<form id="registration_form"{if isset($current) && $current} action="{$current|escape:'html':'UTF-8'}{if isset($token) && $token}&amp;token={$token|escape:'html':'UTF-8'}{/if}"{/if} method="post" enctype="multipart/form-data" class="register_form" novalidate>
    {if !empty($submit_action)}<input type="hidden" name="{$submit_action}" value="1" />{/if}
    {foreach $fields as $f => $fieldset}
        {block name="fieldset"}
            {capture name='fieldset_name'}{counter name='fieldset_name'}{/capture}
            <div id="fieldset_{$f}{if isset($smarty.capture.identifier_count) && $smarty.capture.identifier_count}_{$smarty.capture.identifier_count|intval}{/if}{if $smarty.capture.fieldset_name > 1}_{($smarty.capture.fieldset_name - 1)|intval}{/if}">
                {foreach $fieldset.form as $key => $field}
                    {if $key == 'legend'}
                        {block name="legend"}
                            <div class="panel-heading">
                                {if isset($field.image) && isset($field.title)}<img src="{$field.image}" alt="{$field.title|escape:'html':'UTF-8'}" />{/if}
                                {if isset($field.icon)}<i class="{$field.icon}"></i>{/if}
                                {$field.title}
                            </div>
                        {/block}
                    {elseif $key == 'description' && $field}
                        <div class="alert alert-info">{$field}</div>
                    {elseif $key == 'warning' && $field}
                        <div class="alert alert-warning">{$field}</div>
                    {elseif $key == 'success' && $field}
                        <div class="alert alert-success">{$field}</div>
                    {elseif $key == 'error' && $field}
                        <div class="alert alert-danger">{$field}</div>
                    {/if}
                {/foreach}
            </div>
        {/block}
    {/foreach}
    <div class="register_form_cols">
        <div class="register_form_col">
            <div class="register_form_label">
                <label for="register[name]" class="required"> {l s="Name" mod="comfino"}</label>
            </div>
            <div class="register_form_input">
                <input type="text" id="register[name]" name="register[name]" value="{$register_form.name|escape:"htmlall":"UTF-8"}" tabindex="1" required="required" />
            </div>
            <div class="register_form_label">
                <label for="register[email]" class="required"> {l s="E-mail address" mod="comfino"}</label>
            </div>
            <div class="register_form_input">
                <input type="text" id="register[email]" name="register[email]" value="{$register_form.email|escape:"htmlall":"UTF-8"}" tabindex="3" required="required" />
            </div>
        </div>
        <div class="register_form_col">
            <div class="register_form_label">
                <label for="register[surname]" class="required"> {l s="Surname" mod="comfino"}</label>
            </div>
            <div class="register_form_input">
                <input type="text" id="register[surname]" name="register[surname]" value="{$register_form.surname|escape:"htmlall":"UTF-8"}" tabindex="2" required="required" />
            </div>
            <div class="register_form_label">
                <label for="register[phone]" class="required"> {l s="Phone number" mod="comfino"}</label>
            </div>
            <div class="register_form_input">
                <input type="text" id="register[phone]" name="register[phone]" value="{$register_form.phone|escape:"htmlall":"UTF-8"}" tabindex="4" required="required" />
            </div>
        </div>
    </div>
    <div class="min_wrap"></div>
    <div class="register_form_label">
        <label for="register[url]" class="required"> {l s="Website address where the Comfino payment will be installed" mod="comfino"}</label>
    </div>
    <div class="register_form_input">
        <input type="text" id="register[url]" name="register[url]" value="{$register_form.url|escape:"htmlall":"UTF-8"}" tabindex="5" required="required" />
    </div>
    <div class="register_agreements_list">
        {foreach $agreements as $agreement}
            <div class="register_agreement">
                <div class="register_form_label" style="width: 95%; float: right">
                    <label for="register_agreement_{$agreement.id|escape:"htmlall":"UTF-8"}"{if $agreement.required} class="required"{/if}> {$agreement.content}</label>
                </div>
                <div class="register_form_input" style="float: left">
                    <input type="checkbox" name="register[agreements][{$agreement.id|escape:"htmlall":"UTF-8"}]" id="register_agreement_{$agreement.id|escape:"htmlall":"UTF-8"}"{if $agreement.checked} checked="checked"{/if}{if $agreement.required} required="required"{/if} />
                </div>
            </div>
        {/foreach}
    </div>
    <div class="register_agreements_caption">
        {l s="The administrator of personal data is Comperia.pl S.A." mod="comfino"}<br>
        <a href="https://comfino.pl/polityka-prywatnosci/" target="_blank" rel="noopener">{l s="Read the full information on the processing of personal data" mod="comfino"}</a>.
    </div>
    <div class="center">
        <button id="registration_form_submit_btn" name="submit_registration" type="submit" value="1" class="register_register_btn"{if !$registration_available} disabled{/if}>{l s="Create an account" mod="comfino"}</button>
    </div>
</form>
