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
{if $validation.valid}
    <div class="alert alert-success" role="alert">
        <strong>{l s="Configuration is valid" mod="comfino"}</strong><br>
        {l s="All %d configuration options are present." sprintf=$validation.total_options mod="comfino"}
    </div>
{else}
    {assign var=missing_count value=$validation.missing_options|count}
    <div class="alert alert-warning" role="alert">
        <strong>{l s="Configuration issues detected" mod="comfino"}</strong><br>
        {l s="%d configuration option(s) are missing out of %d total options." sprintf=[$missing_count, $validation.total_options] mod="comfino"}
        <br><br><strong>{l s="Missing options" mod="comfino"}:</strong><br>
        <ul style="margin-top: 10px;">
            {foreach from=$validation.missing_options item=missing_option}
                <li><code>{$missing_option|escape:"html":"UTF-8"}</code></li>
            {/foreach}
        </ul>
    </div>
{/if}
{if !$validation.valid}
    <div>
        <button type="button" id="comfino-repair-config" class="btn btn-primary" data-repair-url="{$repair_url|escape:"html":"UTF-8"}">
            {l s="Repair configuration" mod="comfino"}
        </button>
    </div>
{/if}
<div id="comfino-repair-result" style="margin-top: 15px;"></div>
<script>
    (function () {
        document.getElementById('comfino-repair-config').addEventListener('click', function () {
            const button = this;
            const resultDiv = document.getElementById('comfino-repair-result');
            const repairUrl = button.getAttribute('data-repair-url');

            button.disabled = true;
            button.textContent = '{l s="Repairing..." mod="comfino" js=1}';
            resultDiv.innerHTML = '';

            fetch(repairUrl, {
                method: 'POST',
                headers: {
                    "Content-Type": "application/json",
                    "CR-Signature": {$cr_signature|escape:"html":"UTF-8"}
                },
                body: JSON.stringify({ vkey: '{$vkey|escape:"html":"UTF-8"}' })
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                button.disabled = false;
                button.textContent = '{l s="Repair configuration" mod="comfino" js=1}';

                if (data.status === 'ok' && data.repair_stats) {
                    const stats = data.repair_stats;
                    const alertClass = stats.failed > 0 ? 'alert-warning' : (stats.repaired > 0 ? 'alert-success' : 'alert-info');

                    let html = '<div class="alert ' + alertClass + '" role="alert">' +
                        '<strong>{l s="Repair completed" mod="comfino" js=1}</strong><br>' +
                        '{l s="Checked" mod="comfino" js=1}: ' + stats.checked + '<br>' +
                        '{l s="Missing" mod="comfino" js=1}: ' + stats.missing + '<br>' +
                        '{l s="Repaired" mod="comfino" js=1}: ' + stats.repaired + '<br>';

                    if (stats.failed > 0) {
                        html += '{l s="Failed" mod="comfino" js=1}: ' + stats.failed + '<br>';
                    }

                    if (stats.options_repaired.length > 0) {
                        html += '<br><strong>{l s="Repaired options" mod="comfino" js=1}:</strong><ul>';

                        stats.options_repaired.forEach(function (opt) {
                            html += '<li><code>' + opt + '</code></li>';
                        });

                        html += '</ul>';
                    }

                    if (stats.options_failed.length > 0) {
                        html += '<br><strong>{l s="Failed options" mod="comfino" js=1}:</strong><ul>';

                        stats.options_failed.forEach(function (opt) {
                            html += '<li><code>' + opt + '</code></li>';
                        });

                        html += '</ul>';
                    }

                    html += '</div>';

                    if (stats.repaired > 0 || stats.failed > 0) {
                        html += '<p><strong>{l s="Please refresh the page to see updated configuration status." mod="comfino" js=1}</strong></p>';
                    }

                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-danger" role="alert">{l s="Repair failed: Unknown error." mod="comfino" js=1}</div>';
                }
            })
            .catch(function (error) {
                button.disabled = false;
                button.textContent = '{l s="Repair configuration" mod="comfino" js=1}';
                resultDiv.innerHTML = '<div class="alert alert-danger" role="alert">{l s="Repair failed." mod="comfino" js=1}: ' + error.message + '</div>';
            });
        });
    })();
</script>
