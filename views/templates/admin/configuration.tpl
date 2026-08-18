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

{if $output|count > 0}
    <div class="bootstrap">
        <div class="module_confirmation conf confirm alert alert-{$output_type|escape:"htmlall":"UTF-8"}">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {if $output|count > 1}
                <ul>
                    {foreach from=$output item=msg}
                        <li>{$msg|escape:"htmlall":"UTF-8"}</li>
                    {/foreach}
                </ul>
            {elseif $output|count == 1}
                {$output[0]|escape:"htmlall":"UTF-8"}
            {/if}
        </div>
    </div>
{/if}

{$support_email_address=$support_email_address|escape:"htmlall":"UTF-8"}
{$support_email_subject=$support_email_subject|escape:"htmlall":"UTF-8"|escape:"url"}
{$support_email_body=$support_email_body|escape:"htmlall":"UTF-8"|escape:"url"}

<div class="row">
    <div class="col-md-12">
        <div class="panel">
            <div class="panel-body">
                <img style="width: 300px" src="{$logo_url|escape:"htmlall":"UTF-8"}" alt="Comfino logo"> <span style="font-weight: bold; font-size: 16px; vertical-align: bottom">{$plugin_version}</span>
            </div>
            <div class="panel-body">
                {$contact_msg1|escape:"htmlall":"UTF-8"}
                <a href="mailto:{$support_email_address}?subject={$support_email_subject}&body={$support_email_body}">
                    {$support_email_address}
                </a>
                {$contact_msg2|escape:"htmlall":"UTF-8"}
            </div>
            <div class="panel-body">
                <div class="panel">
                    <ul class="nav nav-tabs" id="comfino_settings_tabs" role="tablist">
                        <li class="nav-item{if $active_tab == "payment_settings"} active{/if}">
                            <a class="nav-link" id="comfino_payment_settings" data-toggle="tab" href="#payment_settings" role="tab" aria-controls="comfino_payment_settings" aria-selected="true">
                                {l s="Payment settings" mod="comfino"}
                            </a>
                        </li>
                        <li class="nav-item{if $active_tab == "sale_settings"} active{/if}">
                            <a class="nav-link" id="comfino_sale_settings" data-toggle="tab" href="#sale_settings" role="tab" aria-controls="comfino_sale_settings" aria-selected="true">
                                {l s="Sale settings" mod="comfino"}
                            </a>
                        </li>
                        <li class="nav-item{if $active_tab == "widget_settings"} active{/if}">
                            <a class="nav-link" id="comfino_widget_settings" data-toggle="tab" href="#widget_settings" role="tab" aria-controls="comfino_widget_settings" aria-selected="true">
                                {l s="Widget settings" mod="comfino"}
                            </a>
                        </li>
                        <li class="nav-item{if $active_tab == "developer_settings"} active{/if}">
                            <a class="nav-link" id="comfino_developer_settings" data-toggle="tab" href="#developer_settings" role="tab" aria-controls="comfino_developer_settings" aria-selected="true">
                                {l s="Developer settings" mod="comfino"}
                            </a>
                        </li>
                        <li class="nav-item{if $active_tab == "plugin_diagnostics"} active{/if}">
                            <a class="nav-link" id="comfino_plugin_diagnostics" data-toggle="tab" href="#plugin_diagnostics" role="tab" aria-controls="comfino_plugin_diagnostics" aria-selected="true">
                                {l s="Plugin diagnostics" mod="comfino"}
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane {if $active_tab == "payment_settings"}active{else}fade{/if}" id="payment_settings" role="tabpanel" aria-labelledby="payment_settings-tab">
                            {hook h="displayBackofficeComfinoForm" config_tab="payment_settings" form_name="submit_configuration"}
                        </div>
                        <div class="tab-pane {if $active_tab == "sale_settings"}active{else}fade{/if}" id="sale_settings" role="tabpanel" aria-labelledby="sale_settings-tab">
                            {hook h="displayBackofficeComfinoForm" config_tab="sale_settings" form_name="submit_configuration"}
                        </div>
                        <div class="tab-pane {if $active_tab == "widget_settings"}active{else}fade{/if}" id="widget_settings" role="tabpanel" aria-labelledby="widget_settings-tab">
                            {hook h="displayBackofficeComfinoForm" config_tab="widget_settings" form_name="submit_configuration"}
                        </div>
                        <div class="tab-pane {if $active_tab == "developer_settings"}active{else}fade{/if}" id="developer_settings" role="tabpanel" aria-labelledby="developer_settings-tab">
                            {hook h="displayBackofficeComfinoForm" config_tab="developer_settings" form_name="submit_configuration"}
                        </div>
                        <div class="tab-pane {if $active_tab == "plugin_diagnostics"}active{else}fade{/if}" id="plugin_diagnostics" role="tabpanel" aria-labelledby="plugin_diagnostics-tab">
                            {hook h="displayBackofficeComfinoForm" config_tab="plugin_diagnostics" form_name="submit_configuration"}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
