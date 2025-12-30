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
<script>
    let comfinoUpdateNotice = `
<div class="row">
    <div class="alert alert-info comfino-update-notice" style="margin: 15px; position: relative">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"
                style="position: absolute; top: 10px; right: 10px; cursor: pointer; background: none; border: none; font-size: 21px; font-weight: bold; line-height: 1; color: #000; opacity: 0.2"
                onclick="comfinoDismissUpdateNotice('{$update_info.github_version|escape:'javascript':'UTF-8'}', '{$dismiss_url|escape:'javascript':'UTF-8'}')"
        >
            <span aria-hidden="true">&times;</span>
        </button>
        <h4 style="margin-top: 0">
            <i class="icon-info-circle"></i>
            {l s="Comfino plugin update available" mod="comfino"}
        </h4>
        <p style="margin-bottom: 10px">
            {l s="A new version of the Comfino payment module is available!" mod="comfino"}
        </p>
        <p style="margin-bottom: 10px">
            <strong>{l s="Current version" mod="comfino"}:</strong> {$update_info.current_version|escape:"html":"UTF-8"}<br>
            <strong>{l s="New version" mod="comfino"}:</strong> {$update_info.github_version|escape:"html":"UTF-8"}
        </p>
        <p style="margin-bottom: 15px">
            <a href="{$update_info.release_notes_url|escape:"html":"UTF-8"}" target="_blank" class="btn btn-link" style="padding-left: 0">
                <i class="icon-external-link"></i>
                {l s="View release notes on GitHub" mod="comfino"}
            </a>
        </p>
    </div>
</div>`;

    function comfinoDismissUpdateNotice(version, dismissUrl)
    {
        fetch(dismissUrl, {
            method: 'POST',
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ version: version })
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (data.success) {
                // Find and remove the notice element.
                const notice = document.querySelector('.comfino-update-notice');

                if (notice) {
                    notice.style.display = 'none';
                }
            }
        })
        .catch(function (error) {
            console.error('Failed to dismiss update notice:', error);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelector('div#dashboard')?.insertAdjacentHTML('afterbegin', comfinoUpdateNotice.trim());
    });
</script>
