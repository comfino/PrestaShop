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

namespace Comfino\Tests\Unit\Order;

use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Common\Shop\OrderStatusAdapterInterface;
use Comfino\Order\ShopStatusManager;
use Comfino\Tests\TestState;
use PHPUnit\Framework\TestCase;

/**
 * The shop must not send a cancellation back to the ComfinoPay API for an order ComfinoPay already closed.
 *
 * The spy adapter asks for the suppression decision from inside the shared StatusManager, which is where PrestaShop
 * fires actionOrderStatusPostUpdate (and so ShopStatusManager::orderStatusUpdateEventHandler()) while a status
 * notification is applied.
 */
class RedundantCancelGuardTest extends TestCase
{
    public const ORDER_ID = 42;
    public const TERMINAL_STATUS_IDS = [
        'COMFINO_CANCELLED' => 101,
        'COMFINO_REJECTED' => 102,
        'COMFINO_CANCELLED_BY_SHOP' => 103,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        TestState::reset();

        foreach (self::TERMINAL_STATUS_IDS as $orderStateCode => $orderStateId) {
            \Configuration::updateValue($orderStateCode, $orderStateId);
        }
    }

    protected function tearDown(): void
    {
        TestState::reset();

        parent::tearDown();
    }

    /**
     * @dataProvider cancellationStatusProvider
     */
    public function testWebhookCancellationNotEchoed($status): void
    {
        $adapter = new SuppressionSpyAdapter();

        StatusManager::getInstance($adapter)->setOrderStatus((string) self::ORDER_ID, $status);

        $this->assertSame([ShopStatusManager::CANCEL_SUPPRESSED_BY_CONTEXT], $adapter->reasons);
    }

    public function cancellationStatusProvider(): array
    {
        return [
            'REJECTED' => [StatusManager::STATUS_REJECTED],
            'CANCELLED' => [StatusManager::STATUS_CANCELLED],
            'CANCELLED_BY_SHOP' => [StatusManager::STATUS_CANCELLED_BY_SHOP],
        ];
    }

    public function testCustomStatusMapToCanceledNotEchoed(): void
    {
        /* A status map entry may point any ComfinoPay status at PS_OS_CANCELED. The 4.3.x flag covered only the three
           cancellation statuses; the shared context covers every notification. */
        $adapter = new SuppressionSpyAdapter();

        StatusManager::getInstance($adapter)->setOrderStatus((string) self::ORDER_ID, StatusManager::STATUS_ACCEPTED);

        $this->assertSame([ShopStatusManager::CANCEL_SUPPRESSED_BY_CONTEXT], $adapter->reasons);
    }

    public function testNestedSetStatusKeepsContext(): void
    {
        $adapter = new SuppressionSpyAdapter();
        $adapter->nestedStatus = StatusManager::STATUS_CANCELLED;

        StatusManager::getInstance($adapter)->setOrderStatus((string) self::ORDER_ID, StatusManager::STATUS_REJECTED);

        // Inner call first, then the outer one, which runs after the inner call has returned.
        $this->assertSame(
            [ShopStatusManager::CANCEL_SUPPRESSED_BY_CONTEXT, ShopStatusManager::CANCEL_SUPPRESSED_BY_CONTEXT],
            $adapter->reasons
        );
    }

    public function testContextEndsWithTheNotification(): void
    {
        StatusManager::getInstance(new SuppressionSpyAdapter())->setOrderStatus((string) self::ORDER_ID, StatusManager::STATUS_REJECTED);

        // A later, genuine cancellation in the same process is not suppressed.
        $this->assertNull(ShopStatusManager::getCancelSuppressionReason(self::ORDER_ID));
    }

    public function testTerminalHistorySuppressesAdminCancel(): void
    {
        \Db::$valueResolver = static function ($sql) {
            return strpos($sql, 'id_order = ' . self::ORDER_ID) !== false ? '1' : false;
        };

        $this->assertSame(
            ShopStatusManager::CANCEL_SUPPRESSED_BY_HISTORY,
            ShopStatusManager::getCancelSuppressionReason(self::ORDER_ID)
        );

        // Read from order_history directly (not Order::getHistory()'s static cache), for the terminal states only.
        $this->assertCount(1, \Db::$queries);
        $this->assertContains('ps_order_history', \Db::$queries[0]);
        $this->assertContains('id_order_state IN (101,102,103)', \Db::$queries[0]);
    }

    public function testGenuineShopCancellationSent(): void
    {
        // No terminal ComfinoPay state in the history and no notification being applied.
        $this->assertNull(ShopStatusManager::getCancelSuppressionReason(self::ORDER_ID));
        $this->assertCount(1, \Db::$queries);
    }

    public function testNoHistoryQueryWithoutTerminalStatusIds(): void
    {
        TestState::reset();

        $this->assertNull(ShopStatusManager::getCancelSuppressionReason(self::ORDER_ID));
        $this->assertCount(0, \Db::$queries);
    }
}

/**
 * Records the cancellation decision ShopStatusManager would make while a status notification is being applied.
 */
class SuppressionSpyAdapter implements OrderStatusAdapterInterface
{
    /** @var array<int, string|null> */
    public $reasons = [];
    /** @var string|null Status applied through a nested setOrderStatus() call, once. */
    public $nestedStatus;

    public function setStatus($orderId, $status): void
    {
        if ($this->nestedStatus !== null) {
            $nestedStatus = $this->nestedStatus;
            $this->nestedStatus = null;

            StatusManager::getInstance($this)->setOrderStatus($orderId, $nestedStatus);
        }

        $this->reasons[] = ShopStatusManager::getCancelSuppressionReason((int) $orderId);
    }
}
