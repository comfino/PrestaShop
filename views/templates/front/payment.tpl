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

<div id="comfino-container" style="display: none"></div>
<script>
    if (!window.Comfino) {
        window.Comfino = {
            options: null,
            initialized: false,

            init(frontendScriptURL) {
                if (Comfino.initialized && typeof ComfinoFrontendRenderer !== 'undefined') {
                    ComfinoFrontendRenderer.init(Comfino.options);

                    return;
                }

                let script = document.createElement('script');

                script.onload = () => ComfinoFrontendRenderer.init(Comfino.options);
                script.src = frontendScriptURL;
                script.async = true;

                document.getElementsByTagName('head')[0].appendChild(script);

                Comfino.initialized = true;
            }
        }
    }

    Comfino.options = {$frontend_renderer_options|@json_encode nofilter};
    Comfino.options.frontendInitElement = document.querySelector('input[data-module-name="comfino"]');
    Comfino.options.frontendTargetElement = document.getElementById('comfino-container');
    Comfino.init('{$frontend_script_url|escape:"javascript":"UTF-8"}');
</script>
