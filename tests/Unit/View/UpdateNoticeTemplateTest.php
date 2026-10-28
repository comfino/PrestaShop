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
 * Structural guards for the update notice: no Smarty value inside script code, no unauthenticated dismissal.
 */
class UpdateNoticeTemplateTest extends TestCase
{
    const ROOT = __DIR__ . '/../../..';

    public function testNoSmartyValueIsInterpolatedIntoScriptCode()
    {
        $template = file_get_contents(self::ROOT . '/views/templates/admin/update-notice.tpl');

        $this->assertSame(1, preg_match_all('#<script>(.*?)</script>#s', $template, $scripts));
        $this->assertNotRegExp('/\{\$|\{l s=|`/', $scripts[1][0], 'Script code must be static.');
        $this->assertContains('<template id="comfino-update-notice-template">', $template);
    }

    public function testReleaseDescriptionStaysInsideInertTemplate()
    {
        $template = file_get_contents(self::ROOT . '/views/templates/admin/update-notice.tpl');
        $templateEnd = strpos($template, '</template>');

        $this->assertNotFalse($templateEnd);
        $this->assertLessThan($templateEnd, strpos($template, 'description_html nofilter'));
    }

    public function testUnauthenticatedDismissControllerIsGone()
    {
        $this->assertFileNotExists(self::ROOT . '/controllers/front/updatedismiss.php');
        $this->assertNotContains("'updatedismiss'", file_get_contents(self::ROOT . '/comfino.php'));
    }
}
