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

<div class="row">
    <div class="col-md-12">
        <div class="panel">
            <div class="panel-body">
                <table class="table">
                    <tr>
                        <th>{l s='Order ID' mod='comfino'}</th>
                        <th>{l s='Customer' mod='comfino'}</th>
                        <th>{l s='Order status' mod='comfino'}</th>
                        <th>{l s='Action' mod='comfino'}</th>
                    </tr>
                    {foreach from=$orders item=item}
                    <tr>
                        <td>{$item.id_comfino|escape:'htmlall':'UTF-8'}</td>
                        <td>{$item.customer|escape:'htmlall':'UTF-8'}</td>
                        <td>{$item.order_status|escape:'htmlall':'UTF-8'}</td>
                        <td>
                            <form action="" method="post">
                                <input type="hidden" name="self_link" value="{$item.self_link|escape:'htmlall':'UTF-8'}">
                                <input type="submit" name="change_status" value='{l s='Update status' mod='comfino'}'>
                            </form>
                        </td>
                    </tr>
                    {/foreach}
                </table>
            </div>
        </div>
    </div>
</div>
