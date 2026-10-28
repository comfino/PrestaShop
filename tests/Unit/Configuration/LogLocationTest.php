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

namespace Comfino\Tests\Unit\Configuration;

use Comfino\Main;
use PHPUnit\Framework\TestCase;

/**
 * Log location and legacy log migration.
 */
class LogLocationTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->removeDirectory(Main::getLogPath());
        $this->removeDirectory(Main::getVarPath() . '/log');
    }

    protected function tearDown()
    {
        $this->removeDirectory(Main::getLogPath());
        $this->removeDirectory(Main::getVarPath() . '/log');

        parent::tearDown();
    }

    public function testLogPathIsOutsideTheModuleDirectory()
    {
        $this->assertSame(0, strpos(Main::getLogPath(), _PS_ROOT_DIR_));
        $this->assertSame(false, strpos(Main::getLogPath(), _PS_MODULE_DIR_));
        $this->assertSame(_PS_ROOT_DIR_ . '/var/logs/comfino', Main::getLogPath());
    }

    public function testEnsureLogPathCreatesDeniedDirectory()
    {
        $this->assertTrue(Main::ensureLogPath());
        $this->assertFileExists(Main::getLogPath() . '/.htaccess');
        $this->assertContains('Require all denied', file_get_contents(Main::getLogPath() . '/.htaccess'));
        $this->assertContains('Deny from all', file_get_contents(Main::getLogPath() . '/.htaccess'));
    }

    public function testMigrationMovesLegacyLogsAndLeavesOldDirectoryEmptyAndDenied()
    {
        $legacy = Main::getVarPath() . '/log';

        @mkdir($legacy, 0755, true);
        file_put_contents("$legacy/index.php", '<?php');
        file_put_contents("$legacy/debug.log", 'debug');
        file_put_contents("$legacy/errors.log", 'errors');

        $this->assertSame(2, Main::migrateLegacyLogs());

        $this->assertSame('debug', file_get_contents(Main::getLogPath() . '/debug.log'));
        $this->assertSame('errors', file_get_contents(Main::getLogPath() . '/errors.log'));
        $this->assertSame([], array_values(array_diff(scandir($legacy), ['.', '..', 'index.php', '.htaccess'])));
        $this->assertFileExists($legacy . '/.htaccess');
    }

    public function testMigrationNeverOverwritesANewerLog()
    {
        $legacy = Main::getVarPath() . '/log';

        Main::ensureLogPath();
        @mkdir($legacy, 0755, true);
        file_put_contents(Main::getLogPath() . '/debug.log', 'new');
        file_put_contents("$legacy/debug.log", 'old');

        Main::migrateLegacyLogs();

        $this->assertSame('new', file_get_contents(Main::getLogPath() . '/debug.log'));
        $this->assertFileNotExists("$legacy/debug.log");
    }

    private function removeDirectory(string $directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (array_diff(scandir($directory), ['.', '..']) as $file) {
            is_dir("$directory/$file") ? $this->removeDirectory("$directory/$file") : @unlink("$directory/$file");
        }

        @rmdir($directory);
    }
}
