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

namespace Comfino\Order;

use Comfino\Api\Exception\NotFound;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Common\Shop\OrderStatusAdapterInterface;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Adapter for handling order status updates from Comfino API webhooks.
 *
 * This class implements the OrderStatusAdapterInterface from the shared library
 * and provides PrestaShop-specific logic for updating order statuses based on
 * payment status changes received from Comfino API.
 *
 * Supports two order identification modes:
 * - Legacy: Numeric order ID (e.g., "12345")
 * - Modern: Order reference string (e.g., "XKTARQXWV")
 *
 * Status update flow:
 * 1. Loads order by ID or reference.
 * 2. Validates order is paid via Comfino.
 * 3. Maps Comfino status to custom PrestaShop status.
 * 4. Applies custom status if not already in history.
 * 5. Maps to standard PrestaShop status and applies if configured.
 */
class StatusAdapter implements OrderStatusAdapterInterface
{
    /**
     * Updates PrestaShop order status based on Comfino payment status.
     *
     * This method is called by StatusNotification REST endpoint when receiving
     * status update webhooks from Comfino API. It:
     * - Determines order lookup method (by ID or reference).
     * - Validates order belongs to Comfino payment module.
     * - Converts Comfino status to PrestaShop custom status.
     * - Applies both custom and mapped standard statuses if not already in history.
     *
     * The method avoids creating duplicate status history entries by checking
     * if the status was already applied to the order.
     *
     * @param string|int $orderId Order identifier - either numeric ID or reference string
     * @param string $status Comfino payment status (e.g., "ACCEPTED", "REJECTED", "CANCELLED")
     *
     * @return void
     *
     * @throws NotFound If order not found by provided ID or reference
     * @throws RequestValidationError If order exists but is not a Comfino order
     * @throws ServiceUnavailable If database error occurs during order loading
     */
    public function setStatus($orderId, $status): void
    {
        DebugLogger::logEvent(
            '[ORDER_STATUS_UPDATE]',
            'StatusAdapter::setStatus: Order status update from Comfino API.',
            ['orderId' => $orderId, 'status' => $status]
        );

        // Determine if orderId is numeric (legacy) or reference (new).
        $isNumericId = is_numeric($orderId) && ctype_digit((string) $orderId);

        if ($isNumericId) {
            // Legacy path: Load by numeric ID.
            try {
                $order = new \Order((int) $orderId);
            } catch (\PrestaShopDatabaseException|\PrestaShopException $e) {
                throw new ServiceUnavailable("Order $orderId loading error.", $e->getCode(), $e);
            }

            if (!empty($order->module) && $order->module !== COMFINO_MODULE_NAME) {
                // Process orders paid by Comfino only.
                throw new RequestValidationError("Order $orderId is not a valid Comfino order.");
            }
        } else {
            // New path: Load by reference using PrestaShopCollection.
            try {
                $orderCollection = new \PrestaShopCollection('Order');
                $orderCollection->where('reference', '=', $orderId)->where('module', '=', COMFINO_MODULE_NAME);
            } catch (\PrestaShopException $e) {
                throw new ServiceUnavailable("Order \"$orderId\" loading error.", $e->getCode(), $e);
            }

            $order = $orderCollection->getFirst();
        }

        if (!\Validate::isLoadedObject($order)) {
            throw new NotFound(sprintf('Order not found by %s: %s', $isNumericId ? 'id' : 'reference', $orderId));
        }

        $inputStatus = \Tools::strtoupper($status);

        DebugLogger::logEvent(
            '[ORDER_STATUS_UPDATE]',
            sprintf(
                "StatusAdapter::setStatus (order %s: %s, status: \"%s\", internal ID: %d)",
                $isNumericId ? 'ID' : 'reference',
                $orderId,
                $inputStatus,
                $order->id
            )
        );

        if (in_array($inputStatus, StatusManager::STATUSES, true)) {
            $customStatusNew = "COMFINO_$inputStatus";
        } else {
            return;
        }

        $currentInternalStatusId = (int) $order->getCurrentState();
        $newCustomStatusId = (int) \Configuration::get($customStatusNew);

        DebugLogger::logEvent(
            '[ORDER_STATUS_UPDATE]',
            "current internal status ID: $currentInternalStatusId, new custom status ID: $newCustomStatusId"
        );

        if ($newCustomStatusId !== $currentInternalStatusId) {
            $statusIdsHistory = array_unique(array_column($order->getHistory(0), 'id_order_state'), SORT_NUMERIC);

            if (!in_array($newCustomStatusId, $statusIdsHistory, true)) {
                $order->setCurrentState($newCustomStatusId);
            }

            $statusMap = ConfigManager::getStatusMap();

            if (!array_key_exists($inputStatus, $statusMap)) {
                return;
            }

            $newInternalStatusId = (int) \Configuration::get($statusMap[$inputStatus]);

            DebugLogger::logEvent('[ORDER_STATUS_UPDATE]', "new internal status ID: $newInternalStatusId");

            if (!in_array($newInternalStatusId, $statusIdsHistory, true)) {
                $order->setCurrentState($newInternalStatusId);
            }
        }
    }
}
