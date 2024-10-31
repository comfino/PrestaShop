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

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>{$error_message|escape:"htmlall":"UTF-8"} [{$error_code|escape:"htmlall":"UTF-8"}]</title>
        <style>{$paywall_style}</style>
    </head>
    <body>
        <div class="loadingio-spinner-rolling-comfino">
            <div class="ldio-comfino"><div></div></div>
        </div>
        {if $is_debug_mode}
            <h2>API error</h2>
            <p>Error message: {$error_message|escape:"htmlall":"UTF-8"}</p>
            <p>Error code: {$error_code|escape:"htmlall":"UTF-8"}</p>
            <p>File: {$error_file|escape:"htmlall":"UTF-8"}</p>
            <p>Line: {$error_line|escape:"htmlall":"UTF-8"}</p>
            <p>Trace:</p>
            <code>{$error_trace|escape:"htmlall":"UTF-8"}</code>
            <p>URL: {$url|escape:"htmlall":"UTF-8"}</p>
            <p>Request:</p>
            <code>{$request_body|escape:"htmlall":"UTF-8"}</code>
            <p>Response:</p>
            <code>{$response_body|escape:"htmlall":"UTF-8"}</code>
        {/if}
    </body>
</html>
