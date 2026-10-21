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
{* Server-rendered, escaped markup inside an inert <template>: no value is ever interpolated into script code. *}
<template id="comfino-update-notice-template">
<div class="row">
    <div class="alert alert-info comfino-update-notice" style="margin: 15px; position: relative"
         data-version="{$update_info.github_version|escape:'html':'UTF-8'}" data-dismiss-url="{$dismiss_url|escape:'html':'UTF-8'}">
        <button type="button" class="close comfino-update-notice-dismiss" aria-label="Close"
                style="position: absolute; top: 10px; right: 10px; cursor: pointer; background: none; border: none; font-size: 21px; font-weight: bold; line-height: 1; color: #000; opacity: 0.2"
        >
            <span aria-hidden="true">&times;</span>
        </button>
        <h4 style="margin-top: 0">
            <i class="icon-info-circle"></i>
            {l s="ComfinoPay plugin update available" mod="comfino"}
        </h4>
        <p style="margin-bottom: 10px">
            {l s="A new version of the ComfinoPay payment module is available!" mod="comfino"}
        </p>
        <p style="margin-bottom: 10px">
            <strong>{l s="Current version" mod="comfino"}:</strong> {$update_info.current_version|escape:"html":"UTF-8"}<br>
            <strong>{l s="New version" mod="comfino"}:</strong> {$update_info.github_version|escape:"html":"UTF-8"}
        </p>
        {if $update_info.description_html}
            {* "What's new" HTML of the available release. Purified with Tools::purifyHTML() in FrontendManager.
               Styled by views/css/admin/release-description.css, loaded in hookActionAdminControllerSetMedia on the
               admin dashboard (this notice is injected client-side into #dashboard, so it needs the sheet already present). *}
            <div class="comfino-release-description" style="margin-bottom: 10px">{$update_info.description_html nofilter}</div>
        {/if}
        <p style="margin-bottom: 15px">
            <a href="{$update_info.release_notes_url|escape:"html":"UTF-8"}" target="_blank" rel="noopener noreferrer" class="btn btn-link" style="padding-left: 0">
                <i class="icon-external-link"></i>
                {l s="View release notes on GitHub" mod="comfino"}
            </a>
        </p>
    </div>
</div>
</template>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const template = document.getElementById('comfino-update-notice-template');
        const dashboard = document.querySelector('div#dashboard');

        if (!template || !dashboard) {
            return;
        }

        dashboard.insertBefore(template.content.cloneNode(true), dashboard.firstChild);

        const notice = dashboard.querySelector('.comfino-update-notice');
        const button = notice ? notice.querySelector('.comfino-update-notice-dismiss') : null;

        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            const body = new URLSearchParams();

            body.append('comfino_dismiss_update_notice', notice.dataset.version);

            // Authenticated POST to the module settings page (AdminModules token is part of the URL).
            fetch(notice.dataset.dismissUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.success) {
                    notice.style.display = 'none';
                }
            })
            .catch(function (error) {
                console.error('Failed to dismiss update notice:', error);
            });
        });
    });
</script>
