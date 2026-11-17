<?php
/**
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
 */

namespace Comfino\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

/**
 * Structural guards: the product-page widget config is part of a possibly full-page-cached page, so it must carry no
 * per-visitor value, and the values must come from the uncached widgetsession controller.
 */
class WidgetCacheSafetyTest extends TestCase
{
    const ROOT = __DIR__ . '/../../..';

    public function testWidgetSessionControllerIsRegisteredAndNeverCached()
    {
        $controller = file_get_contents(self::ROOT . '/controllers/front/widgetsession.php');

        $this->assertContains("'widgetsession'", file_get_contents(self::ROOT . '/comfino.php'));
        $this->assertContains('no-store', $controller);
        $this->assertContains('Pragma: no-cache', $controller);
        $this->assertContains('getWidgetSessionValues', $controller);
    }

    public function testWidgetConfigCarriesNoPerVisitorValue()
    {
        $frontendManager = file_get_contents(self::ROOT . '/src/View/FrontendManager.php');
        $configManager = file_get_contents(self::ROOT . '/src/Configuration/ConfigManager.php');

        $this->assertNotContains("'LOGGING_TOKEN'", $configManager);
        $this->assertNotContains("'TRACK_ID'", $configManager);
        $this->assertNotContains("\$variables['LOGGING_TOKEN']", $frontendManager);
        $this->assertNotContains("\$variables['TRACK_ID']", $frontendManager);
    }

    public function testLoaderMergesSessionValuesBeforeLoadingWidgetScript()
    {
        $comfino = file_get_contents(self::ROOT . '/comfino.php');
        $frontendManager = file_get_contents(self::ROOT . '/src/View/FrontendManager.php');

        $this->assertNotContains("'comfino-widget'", $comfino, 'The widget script must be loaded by the loader only.');
        $this->assertContains("'comfino-widget-loader'", $comfino);
        $this->assertNotContains('renderWidgetLoaderElement', $frontendManager, 'The loader must not be embedded inline.');
        $this->assertContains('data-session-url', $frontendManager);

        foreach (['widget-loader.js', 'widget-loader.min.js'] as $file) {
            $script = file_get_contents(self::ROOT . '/views/js/front/' . $file);

            $this->assertContains('data-session-url', $script, $file);
            $this->assertContains('data-widget-script-url', $script, $file);
            $this->assertContains('trackId', $script, $file);
            $this->assertContains('loggingToken', $script, $file);
            $this->assertLessThan(strrpos($script, 'then('), strpos($script, 'fetch('), $file);
        }
    }
}
