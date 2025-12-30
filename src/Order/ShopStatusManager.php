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

use Comfino\Api\ApiClient;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\ErrorLogger;
use Comfino\Main;
use Comfino\View\FrontendManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ShopStatusManager
{
    public const DEFAULT_STATUS_MAP = [
        StatusManager::STATUS_ACCEPTED => 'PS_OS_WS_PAYMENT',
        StatusManager::STATUS_CANCELLED => 'PS_OS_CANCELED',
        StatusManager::STATUS_REJECTED => 'PS_OS_CANCELED',
        StatusManager::STATUS_CANCELLED_BY_SHOP => 'PS_OS_CANCELED',
    ];

    private const CUSTOM_ORDER_STATUSES = [
        'COMFINO_' . StatusManager::STATUS_CREATED => [
            'name' => 'Order created - waiting for payment (Comfino)',
            'name_pl' => 'Zamówienie utworzone - oczekiwanie na płatność (Comfino)',
            'color' => '#87b921',
            'paid' => false,
            'deleted' => false,
        ],
        'COMFINO_' . StatusManager::STATUS_ACCEPTED => [
            'name' => 'Credit granted (Comfino)',
            'name_pl' => 'Kredyt udzielony (Comfino)',
            'color' => '#227b34',
            'paid' => true,
            'deleted' => false,
        ],
        'COMFINO_' . StatusManager::STATUS_REJECTED => [
            'name' => 'Credit rejected (Comfino)',
            'name_pl' => 'Wniosek kredytowy odrzucony (Comfino)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
        'COMFINO_' . StatusManager::STATUS_CANCELLED => [
            'name' => 'Cancelled (Comfino)',
            'name_pl' => 'Anulowano (Comfino)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
    ];

    public static function addCustomOrderStatuses(): void
    {
        $languages = \Language::getLanguages(false);

        foreach (self::CUSTOM_ORDER_STATUSES as $statusCode => $statusDetails) {
            $comfinoStatusId = \Configuration::get($statusCode);

            if (!empty($comfinoStatusId) && \Validate::isInt($comfinoStatusId)) {
                try {
                    $orderStatus = new \OrderState($comfinoStatusId);
                } catch (\PrestaShopDatabaseException|\PrestaShopException $e) {
                    FrontendManager::processError(
                        sprintf('Order status loading error: %d', (int) $comfinoStatusId),
                        $e, null, 'Order status loading error.'
                    );

                    continue;
                }

                if (\Validate::isLoadedObject($orderStatus)) {
                    // Update existing status definition.
                    $orderStatus->color = $statusDetails['color'];
                    $orderStatus->paid = $statusDetails['paid'];
                    $orderStatus->deleted = $statusDetails['deleted'];

                    try {
                        $orderStatus->update();
                    } catch (\PrestaShopDatabaseException|\PrestaShopException $e) {
                        FrontendManager::processError(
                            sprintf('Order status update error: %d', (int) $orderStatus->id),
                            $e, null, 'Order status update error.'
                        );
                    }

                    continue;
                }
            } elseif ($statusDetails['deleted']) {
                // Ignore deleted statuses in first time plugin installations.
                continue;
            }

            // Add a new status definition.
            $orderStatus = new \OrderState();
            $orderStatus->send_email = false;
            $orderStatus->invoice = false;
            $orderStatus->color = $statusDetails['color'];
            $orderStatus->unremovable = false;
            $orderStatus->logable = false;
            $orderStatus->module_name = 'comfino';
            $orderStatus->paid = $statusDetails['paid'];

            foreach ($languages as $language) {
                $statusName = $language['iso_code'] === 'pl' ? $statusDetails['name_pl'] : $statusDetails['name'];
                $orderStatus->name[$language['id_lang']] = $statusName;
            }

            try {
                if ($orderStatus->add()) {
                    \Configuration::updateValue($statusCode, $orderStatus->id);
                }
            } catch (\PrestaShopDatabaseException|\PrestaShopException $e) {
                FrontendManager::processError(
                    sprintf('Order status adding error: %s, %d.', $statusCode, (int) $orderStatus->id),
                    $e, null, 'Order status adding error.'
                );
            }
        }
    }

    public static function updateOrderStatuses(): void
    {
        $languages = \Language::getLanguages(false);

        foreach (self::CUSTOM_ORDER_STATUSES as $statusCode => $statusDetails) {
            $comfinoStatusId = \Configuration::get($statusCode);

            if (!empty($comfinoStatusId) && \Validate::isInt($comfinoStatusId)) {
                try {
                    $orderStatus = new \OrderState($comfinoStatusId);
                } catch (\PrestaShopDatabaseException|\PrestaShopException $e) {
                    FrontendManager::processError(
                        sprintf('Order status creation error: %s, %d', $statusCode, (int) $comfinoStatusId),
                        $e, null, 'Order status creation error.'
                    );

                    continue;
                }

                if (\Validate::isLoadedObject($orderStatus)) {
                    // Update existing status definition.
                    foreach ($languages as $language) {
                        if ($language['iso_code'] === 'pl') {
                            $orderStatus->name[$language['id_lang']] = $statusDetails['name_pl'];
                        } else {
                            $orderStatus->name[$language['id_lang']] = $statusDetails['name'];
                        }
                    }

                    $orderStatus->color = $statusDetails['color'];
                    $orderStatus->paid = $statusDetails['paid'];
                    $orderStatus->deleted = $statusDetails['deleted'];

                    try {
                        $orderStatus->save();
                    } catch (\PrestaShopException $e) {
                        FrontendManager::processError(
                            sprintf('Order status saving error: %s', $statusDetails['name']),
                            $e, null, 'Order status saving error.'
                        );
                    }
                }
            }
        }
    }

    public static function orderStatusUpdateEventHandler(array $params): void
    {
        try {
            $order = new \Order($params['id_order']);
        } catch (\PrestaShopDatabaseException|\PrestaShopException $e) {
            FrontendManager::processError(
                sprintf('Order loading error during cancel event: %s', $params['id_order']),
                $e, null, 'Order loading error during cancel event.'
            );

            return;
        }

        if (stripos($order->payment, 'comfino') !== false) {
            // Process orders paid by Comfino only.

            /** @var \OrderState $newOrderState */
            $newOrderState = $params['newOrderStatus'];

            $newOrderStateId = (int) $newOrderState->id;
            $canceledOrderStateId = (int) \Configuration::get('PS_OS_CANCELED');

            if ($newOrderStateId === $canceledOrderStateId) {
                ErrorLogger::init();

                try {
                    // Send notification about canceled order paid by Comfino.
                    ApiClient::getInstance()->cancelOrder((string) $params['id_order']);
                } catch (\Throwable $e) {
                    ApiClient::processApiError(
                        'Order cancellation error on page "' . Main::getRequestUri() . '" (Comfino API)', $e
                    );
                }
            }
        }
    }
}
