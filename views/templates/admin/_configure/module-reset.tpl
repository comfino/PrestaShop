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
<div class="alert alert-info" role="alert">
    <strong>{l s="Module reset" mod="comfino"}</strong><br>
    {l s="This operation will" mod="comfino"}:
    <ul style="margin-top: 10px;">
        <li>{l s="Add missing configuration options (preserves existing values)." mod="comfino"}</li>
        <li>{l s="Re-register all PrestaShop hooks." mod="comfino"}</li>
        <li>{l s="Recreate custom order statuses." mod="comfino"}</li>
        <li>{l s="Clear module cache." mod="comfino"}</li>
    </ul>
    <p style="margin-top: 10px;"><em>{l s="Note: This operation does NOT delete any existing configuration or data." mod="comfino"}</em></p>
</div>
<div>
    <form method="post" id="comfino-reset-form">
        <button type="submit" name="submit_module_reset" class="btn btn-primary" onclick="return confirm('{l s="Are you sure you want to reset the module? This will re-register hooks and recreate order statuses." mod="comfino" js=1}');">
            {l s="Reset module" mod="comfino"}
        </button>
    </form>
</div>
