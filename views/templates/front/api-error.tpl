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
{if $full_document_structure}
    <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>
                {if $is_debug_mode}
                    {$error_message|escape:"htmlall":"UTF-8"} [{$error_code|escape:"htmlall":"UTF-8"}]
                {else}
                    {$user_error_message|escape:"htmlall":"UTF-8"}
                {/if}
            </title>
            {foreach from=$paywall_styles item=style}
                <link rel="stylesheet" href="{$style|escape:"url":"UTF-8"}" media="all">
            {/foreach}
        </head>
        <body>
{/if}
{if $show_loader}<div class="loadingio-spinner-rolling-comfino"><div class="ldio-comfino"><div></div></div></div>{/if}
{if $show_message}<div class="error-message">{$user_error_message|escape:"htmlall":"UTF-8"}</div>{/if}
{if $is_debug_mode}
    <div class="debug-messages">
        <h2>API error</h2>
        <p><strong>Error message:</strong> {$error_message|escape:"htmlall":"UTF-8"}</p>
        <p><strong>Error code:</strong> {$error_code|escape:"htmlall":"UTF-8"}</p>
        <p><strong>File:</strong> {$error_file|escape:"htmlall":"UTF-8"}</p>
        <p><strong>Line:</strong> {$error_line|escape:"htmlall":"UTF-8"}</p>
        <p><strong>Exception class:</strong> {$exception_class|escape:"htmlall":"UTF-8"}</p>
        <p><strong>Trace:</strong></p>
        <pre>{$error_trace|escape:"htmlall":"UTF-8"}</pre>
        <p><strong>URL:</strong> {$url|escape:"url":"UTF-8"}</p>
        <p><strong>Request:</strong></p>
        <pre>{$request_body|escape:"htmlall":"UTF-8"}</pre>
        <p><strong>Response:</strong></p>
        <pre>{$response_body|escape:"htmlall":"UTF-8"}</pre>
    </div>
{/if}
{if $full_document_structure}
        </body>
    </html>
{/if}
