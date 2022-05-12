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
        <div class="module_confirmation conf confirm alert alert-{$outputType|escape:'htmlall':'UTF-8'}">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {if $output|count > 1}
                <ul>
                    {foreach from=$output item=msg}
                        <li>{$msg|escape:'htmlall':'UTF-8'}</li>
                    {/foreach}
                </ul>
            {elseif $output|count == 1}
                {$output[0]|escape:'htmlall':'UTF-8'}
            {/if}
        </div>
    </div>
{/if}

{$supportEmailAddress=$supportEmailAddress|escape:'htmlall':'UTF-8'}
{$supportEmailSubject=$supportEmailSubject|escape:'htmlall':'UTF-8'|escape:'url'}
{$supportEmailBody=$supportEmailBody|escape:'htmlall':'UTF-8'|escape:'url'}

<div class="row">
    <div class="col-md-12">
        <div class="panel">
            <div class="panel-body">
                <img src="{$logoUrl|escape:'htmlall':'UTF-8'}" alt="Comfino logo">
            </div>
            <div class="panel-body">
                {$contactMsg1}
                <a href="mailto:{$supportEmailAddress}?subject={$supportEmailSubject}&body={$supportEmailBody}">
                    {$supportEmailAddress}
                </a>
                {$contactMsg2}
            </div>
            <div class="panel-body">
                {hook h='displayBackofficeComfinoForm'}
            </div>
        </div>
    </div>
</div>
