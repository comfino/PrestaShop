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

use Comfino\Configuration\ConfigManager;
use Comfino\Order\ShopStatusManager;
use Comfino\Tests\TestState;
use PHPUnit\Framework\TestCase;

/**
 * Remote values are validated on write (ConfigManager::save() is the shared write chokepoint) and on read.
 */
class RemoteValueValidationTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        TestState::reset();
    }

    protected function tearDown()
    {
        TestState::reset();

        parent::tearDown();
    }

    public function testInvalidStatusMapEntriesAreDroppedOnWrite()
    {
        ConfigManager::save(['COMFINO_STATUS_MAP' => json_encode([
            'ACCEPTED' => 'PS_OS_PAYMENT',
            'EVIL' => 'PS_OS_CANCELED',
            'REJECTED' => 'not a status; DROP',
        ])]);

        $this->assertSame(['ACCEPTED' => 'PS_OS_PAYMENT'], json_decode(\Configuration::get('COMFINO_STATUS_MAP'), true));
    }

    public function testUnknownStatusListEntriesAreDroppedOnWrite()
    {
        ConfigManager::save([
            'COMFINO_IGNORED_STATUSES' => 'PAID,BOGUS,CREATED',
            'COMFINO_FORBIDDEN_STATUSES' => 'RESIGN,<script>',
        ]);

        $this->assertSame('PAID,CREATED', \Configuration::get('COMFINO_IGNORED_STATUSES'));
        $this->assertSame('RESIGN', \Configuration::get('COMFINO_FORBIDDEN_STATUSES'));
    }

    public function testInvalidValuesAreIgnoredOnRead()
    {
        \Configuration::updateValue('COMFINO_STATUS_MAP', json_encode(['BOGUS' => 'PS_OS_CANCELED']));
        \Configuration::updateValue('COMFINO_IGNORED_STATUSES', 'BOGUS,PAID');
        TestState::resetConfigManagers();

        $this->assertSame(ShopStatusManager::DEFAULT_STATUS_MAP, ConfigManager::getStatusMap());
        $this->assertSame(['PAID'], ConfigManager::getIgnoredStatuses());
    }

    public function testValidAndDiagnosticWritesAreAccepted()
    {
        ConfigManager::save([
            'COMFINO_STATUS_MAP' => json_encode(['ACCEPTED' => 'PS_OS_PAYMENT']),
            'COMFINO_DEBUG' => true,
            'COMFINO_SERVICE_MODE' => true,
            'COMFINO_IS_SANDBOX' => true,
            'COMFINO_DEV_ENV_VARS' => true,
        ]);

        $this->assertSame('{"ACCEPTED":"PS_OS_PAYMENT"}', \Configuration::get('COMFINO_STATUS_MAP'));

        foreach (['COMFINO_DEBUG', 'COMFINO_SERVICE_MODE', 'COMFINO_IS_SANDBOX', 'COMFINO_DEV_ENV_VARS'] as $name) {
            $this->assertTrue(\Configuration::get($name), $name);
        }
    }
}
