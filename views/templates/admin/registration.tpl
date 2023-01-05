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

<style>
    .register_all {
        display: grid;
        grid-template-columns: 56fr 44fr;
        max-width: 894px;
        margin: 30px 0 30px 0;
        grid-gap: 27px;
    }

    .register_left {
        padding: 33px 0 33px 33px;
    }

    .register_left a {
        text-decoration: underline !important;
        color: #5c657e !important;
    }

    .register_tab_left {
        border-right: 1px solid #f7f7f7;
        margin-right: 22px;
    }

    .register_tab {
        transition: 0.3s;
        cursor: pointer;
        font-size: 13px;
        color: #c2cacd;
    }

    .register_tab:hover {
        opacity: 0.9;
    }

    .register_tab_icon {
        width: 37px;
        height: 37px;
        background-color: #ddd;
        background-repeat: no-repeat;
        background-position: center center;
        border-radius: 100%;
        line-height: 40px;
        text-align: center;
        display: inline-block;
        vertical-align: middle;
        margin-right: 7px;

    }

    .register_tab.tab_active .register_tab_icon {
        background-color: #87b825;
    }

    .register_tab_icon_reg { background-image: url('/modules/comfino/views/img/registration/register_tab_reg.png'); }
    .register_tab_icon_log { background-image: url('/modules/comfino/views/img/registration/register_tab_log.png'); }

    .register_logo {
        width: 177px;
        margin: 30px 0;
    }

    .register_done_logo {
        width: 177px;
        margin: 0 0 15px 0;
    }

    .register_h {
        font-size: 22px;
        margin: 0 0 30px;
    }

    .register_h_mini{
        font-size: 17px;
    }
    .register_ol {
        margin: 15px 0 30px 0;
        list-style-type: disc;
    }
    .register_ol li {
        margin: 0 0 7px 23px;
    }

    .register_done_green_h {
        font-size: 27px;
        margin: 0 0 30px;
        color: #87b825;
    }

    .register_done_h {
        font-size: 27px;
        margin: 0 0 30px;
    }

    .register_done_icon_block {
        display: grid;
        grid-template-columns: 13fr 87fr;
        margin: 0 0 7px 0;
    }

    .register_done_caption {
        padding-top: 4px;
    }

    .register_done_caption_medium {
        margin: 27px 0;
        font-size: 19px;
    }

    .register_right {
        background: url('/modules/comfino/views/img/registration/register_right.png') no-repeat top left;
    }

    .register_right.register_thankyou_right {
        background: url('/modules/comfino/views/img/registration/register_done_right.png') no-repeat top left;

    }

    .register_tabs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        max-width: 330px;

    }

    .register_caption {
        font-size: 13px;
        margin: 0 0 22px 0;
    }

    .register_form_cols {
        display: grid;
        grid-template-columns: 1fr 1fr;
        margin: 10px 0;
        grid-gap: 10px;
    }

    .register_form_label {
        font-size: 17px;
        margin-bottom: 7px;
    }

    .register_form_input {
        margin-bottom: 14px;
    }

    .register_form_input input {
        border-radius: 4px;
        border: 1px solid #e4e4e4;
        padding: 7px;
        width: 100%;
    }

    .register_agreements_list {
        margin-top: 40px;
    }

    .register_agreement {
        margin: 7px 0;
        font-size: 13px;
    }

    .register_agreement input {
        width: 17px;
        vertical-align: middle;
        border-radius: 4px;
    }

    .register_agreements_caption {
        font-size: 13px;
        margin-top: 22px;
    }

    button.register_register_btn,a.register_register_btn {
        background: #87b825;
        width: 227px;
        height: 54px;
        line-height: 54px;
        color: #fff !important;
        font-size: 17px;
        border-radius: 4px;
        margin: 30px auto 0;
        cursor: pointer;
        transition: 0.3s;
        display: inline-block;
        text-decoration: none !important;
    }

    .register_register_btn:hover {
        opacity: 0.9;
    }

    @media only screen and (max-width: 800px) {
        .register_all {
            grid-template-columns: 1fr;
        }

        .register_right { display: none; }
    }
</style>

<div class="register_all">
    <div class="register_left">
        <div class="register_tabs">
            <div class="register_tab register_tab_left tab_active" data-tab="reg">
                <div class="register_tab_icon register_tab_icon_reg"></div>
                {l s="Register" mod="comfino"}
            </div>
            <div class="register_tab register_tab_right" data-tab="log">
                <div class="register_tab_icon register_tab_icon_log"></div>
                {l s="Log in" mod="comfino"}
            </div>
        </div>
        <img src="/modules/comfino/views/img/registration/logo.png" alt="Comfino" class="register_logo">
        <div class="max_wrap"></div>
        <div class="register_tab_reg">
            <div class="register_h">
                {l s="Register your shop in Comfino!" mod="comfino"}
            </div>
            <div class="register_caption">
                {l s="Fill in the fields below and we\'ll set up an account for you. This is the first step to start a joint adventure with Comfino installment payments." mod="comfino"}
            </div>
            <div class="register_h_mini">
                {l s="Why is it worth it?" mod="comfino"}
            </div>
            <ol class="register_ol">
                <li>{l s="Minimal formalities - you can sign the contract online," mod="comfino"}</li>
                <li>{l s="No implementation costs and subscription fees," mod="comfino"}</li>
                <li>{l s="Improving the competitiveness of the offer and increasing sales." mod="comfino"}</li>
            </ol>
            <form action="" method="post" class="register_form">
                <div class="register_form_cols">
                    <div class="register_form_col">
                        <div class="register_form_label">{l s="Name" mod="comfino"}</div>
                        <div class="register_form_input">
                            <input type="text" name="register[name]" value="{$register_form.name|escape:"htmlall":"UTF-8"}" tabindex="1" required>
                        </div>
                        <div class="register_form_label">{l s="E-mail address" mod="comfino"}</div>
                        <div class="register_form_input">
                            <input type="text" name="register[email]" value="{$register_form.email|escape:"htmlall":"UTF-8"}" tabindex="3" required>
                        </div>
                    </div>
                    <div class="register_form_col">
                        <div class="register_form_label">{l s="Surname" mod="comfino"}</div>
                        <div class="register_form_input">
                            <input type="text" name="register[surname]" value="{$register_form.surname|escape:"htmlall":"UTF-8"}" tabindex="2" required>
                        </div>
                        <div class="register_form_label">{l s="Phone number" mod="comfino"}</div>
                        <div class="register_form_input">
                            <input type="text" name="register[phone]" value="{$register_form.phone|escape:"htmlall":"UTF-8"}" tabindex="4" required>
                        </div>
                    </div>
                </div>
                <div class="min_wrap"></div>
                <div class="register_form_label">
                    {l s="Website address where the Comfino payment will be installed" mod="comfino"}
                </div>
                <div class="register_form_input">
                    <input type="text" name="register[url]" value="{$register_form.url|escape:"htmlall":"UTF-8"}" tabindex="5" required>
                </div>
                <div class="register_agreements_list">
                    {foreach $agreement as $agreements}
                        <div class="register_agreement">
                            <input type="checkbox" name="register[agreements][{$agreement.id|escape:"htmlall":"UTF-8"}]" id="register_agreement_{$agreement.id|escape:"htmlall":"UTF-8"}"{if $agreement.required} required{/if}>
                            <label for="register_agreement_{$agreement.id|escape:"htmlall":"UTF-8"}">
                                - {$agreement.content|escape:"htmlall":"UTF-8"}
                            </label>
                        </div>
                    {/foreach}
                </div>
                <div class="register_agreements_caption">
                    {l s="The administrator of personal data is Comperia.pl S.A." mod="comfino"}<br>
                    <a href="https://comfino.pl/polityka-prywatnosci/" target="_blank" rel="noopener">{l s="Read the full information on the processing of personal data" mod="comfino"}</a>.
                </div>
                <div class="center">
                    <button type="submit" class="register_register_btn">{l s="Create an account" mod="comfino"}</button>
                </div>
            </form>
        </div>
        <div class="register_tab_log" style="display: none;">
            <div class="register_h">
                {l s="Already have an account at Comfino.pl?" mod="comfino"}
            </div>
            <div class="register_caption">
                {l s="You are in the right place :)" mod="comfino"}<br><br>
                {l s="If you already have an account and a signed contract, it means that you have access to the production API." mod="comfino"}<br><br>
                {l s="All you need to do is set up and activate the Comfino payment gateway." mod="comfino"}
            </div>
            <div class="center">
                <a href="/" class="register_register_btn">{l s="Configure" mod="comfino"}</a>
            </div>
        </div>
    </div>
    <div class="register_right"></div>
</div>
