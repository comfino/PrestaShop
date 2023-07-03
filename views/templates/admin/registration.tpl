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

<style>
    .register_all {
        display: grid;
        grid-template-columns: 56fr 44fr;
        max-width: 894px;
        min-height: 520px;
        margin: 0 0 30px 0;
        grid-gap: 27px;
    }

    .register_left {
        padding: 0;
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
        font-size: 13px;
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

    .register_logo {
        width: 177px;
        margin: 30px 0;
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
        padding-left: 10px;
    }
    .register_ol li {
        margin: 0 0 7px 23px;
    }

    .register_right {
        background: url('/modules/comfino/views/img/registration/register_right.png') no-repeat top left;
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

    .register_form_label label {
        font-weight: normal;
        display: inline;
        margin: 0;
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

    button.register_register_btn, a.register_register_btn {
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

<div class="panel">
    <div class="register_all">
        <div class="register_left">
            {if $registration_available}
                <div class="register_tabs">
                    <div class="register_tab register_tab_left tab_active" data-tab="reg">
                        <div class="register_tab_icon register_tab_icon_reg"></div>
                        {l s="Register" mod="comfino"}
                    </div>
                </div>
            {/if}
            <img src="/modules/comfino/views/img/registration/logo.png" alt="Comfino" class="register_logo">
            <div class="max_wrap"></div>
            {if $registration_available}
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
                    {hook h="displayBackofficeComfinoForm" config_tab="registration" form_name="submit_registration"}
                </div>
            {else}
                <div class="register_tab_log">
                    <div class="register_h">{l s="Already have an account at Comfino.pl?" mod="comfino"}</div>
                    <div class="register_caption">
                        {l s="You are in the right place :)" mod="comfino"}<br><br>
                        {l s="If you already have an account and a signed contract, it means that you have access to the production API." mod="comfino"}<br><br>
                        {l s="All you need to do is set up and activate the Comfino payment gateway." mod="comfino"}
                    </div>
                </div>
            {/if}
        </div>
        <div class="register_right"></div>
    </div>
</div>
