{*
* 2007-2022 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
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
                <img style="width: 300px" src="{$logo_url|escape:"htmlall":"UTF-8"}" alt="Comfino logo">
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
                        <li class="nav-item{if $active_tab == "registration"} active{/if}">
                            <a class="nav-link" id="comfino_registration" data-toggle="tab" href="#registration" role="tab" aria-controls="comfino_registration" aria-selected="true">
                                {$tab_labels.registration}
                            </a>
                        </li>
                        <li class="nav-item{if $active_tab == "payment_settings"} active{/if}">
                            <a class="nav-link" id="comfino_payment_settings" data-toggle="tab" href="#payment_settings" role="tab" aria-controls="comfino_payment_settings" aria-selected="true">
                                {$tab_labels.payment_settings}
                            </a>
                        </li>
                        <li class="nav-item{if $active_tab == "widget_settings"} active{/if}">
                            <a class="nav-link" id="comfino_widget_settings" data-toggle="tab" href="#widget_settings" role="tab" aria-controls="comfino_widget_settings" aria-selected="true">
                                {$tab_labels.widget_settings}
                            </a>
                        </li>
                        <li class="nav-item{if $active_tab == "developer_settings"} active{/if}">
                            <a class="nav-link" id="comfino_developer_settings" data-toggle="tab" href="#developer_settings" role="tab" aria-controls="comfino_developer_settings" aria-selected="true">
                                {$tab_labels.developer_settings}
                            </a>
                        </li>
                        <li class="nav-item{if $active_tab == "plugin_diagnostics"} active{/if}">
                            <a class="nav-link" id="comfino_plugin_diagnostics" data-toggle="tab" href="#plugin_diagnostics" role="tab" aria-controls="comfino_plugin_diagnostics" aria-selected="true">
                                {$tab_labels.plugin_diagnostics}
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content" id="TabmiraklContent">
                        <div class="tab-pane {if $active_tab == "registration"}active{else}fade{/if}" id="registration" role="tabpanel" aria-labelledby="registration-tab">
                            {include file="./registration.tpl"}
                        </div>
                        <div class="tab-pane {if $active_tab == "payment_settings"}active{else}fade{/if}" id="payment_settings" role="tabpanel" aria-labelledby="payment_settings-tab">
                            {hook h="displayBackofficeComfinoForm" config_tab="payment_settings"}
                        </div>
                        <div class="tab-pane {if $active_tab == "widget_settings"}active{else}fade{/if}" id="widget_settings" role="tabpanel" aria-labelledby="widget_settings-tab">
                            {hook h="displayBackofficeComfinoForm" config_tab="widget_settings"}
                        </div>
                        <div class="tab-pane {if $active_tab == "developer_settings"}active{else}fade{/if}" id="developer_settings" role="tabpanel" aria-labelledby="developer_settings-tab">
                            {hook h="displayBackofficeComfinoForm" config_tab="developer_settings"}
                        </div>
                        <div class="tab-pane {if $active_tab == "plugin_diagnostics"}active{else}fade{/if}" id="plugin_diagnostics" role="tabpanel" aria-labelledby="plugin_diagnostics-tab">
                            {hook h="displayBackofficeComfinoForm" config_tab="plugin_diagnostics"}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
