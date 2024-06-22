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

namespace Comfino;

use Comfino\Common\Shop\Order\StatusManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ShopStatusManager
{
    public const DEFAULT_STATUS_MAP = [
        StatusManager::STATUS_ACCEPTED => 'PS_OS_WS_PAYMENT',
        StatusManager::STATUS_CANCELLED => 'PS_OS_CANCELED',
        StatusManager::STATUS_REJECTED => 'PS_OS_CANCELED',
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

        foreach (self::CUSTOM_ORDER_STATUSES as $status_code => $status_details) {
            $comfino_status_id = \Configuration::get($status_code);

            if (!empty($comfino_status_id) && \Validate::isInt($comfino_status_id)) {
                $order_status = new \OrderState($comfino_status_id);

                if (\Validate::isLoadedObject($order_status)) {
                    // Update existing status definition.
                    $order_status->color = $status_details['color'];
                    $order_status->paid = $status_details['paid'];
                    $order_status->deleted = $status_details['deleted'];

                    $order_status->update();

                    continue;
                }
            } elseif ($status_details['deleted']) {
                // Ignore deleted statuses in first time plugin installations.
                continue;
            }

            // Add a new status definition.
            $order_status = new \OrderState();
            $order_status->send_email = false;
            $order_status->invoice = false;
            $order_status->color = $status_details['color'];
            $order_status->unremovable = false;
            $order_status->logable = false;
            $order_status->module_name = 'comfino';
            $order_status->paid = $status_details['paid'];

            foreach ($languages as $language) {
                $status_name = $language['iso_code'] === 'pl' ? $status_details['name_pl'] : $status_details['name'];
                $order_status->name[$language['id_lang']] = $status_name;
            }

            if ($order_status->add()) {
                \Configuration::updateValue($status_code, $order_status->id);
            }
        }
    }

    public static function updateOrderStatuses(): void
    {
        $languages = \Language::getLanguages(false);

        foreach (self::CUSTOM_ORDER_STATUSES as $status_code => $status_details) {
            $comfino_status_id = \Configuration::get($status_code);

            if (!empty($comfino_status_id) && \Validate::isInt($comfino_status_id)) {
                $order_status = new \OrderState($comfino_status_id);

                if (\Validate::isLoadedObject($order_status)) {
                    // Update existing status definition.
                    foreach ($languages as $language) {
                        if ($language['iso_code'] === 'pl') {
                            $order_status->name[$language['id_lang']] = $status_details['name_pl'];
                        } else {
                            $order_status->name[$language['id_lang']] = $status_details['name'];
                        }
                    }

                    $order_status->color = $status_details['color'];
                    $order_status->paid = $status_details['paid'];
                    $order_status->deleted = $status_details['deleted'];

                    $order_status->save();
                }
            }
        }
    }

    public static function orderStatusUpdateEventHandler(\PaymentModule $module, array $params): void
    {
        $order = new \Order($params['id_order']);

        if (stripos($order->payment, 'comfino') !== false) {
            // Process orders paid by Comfino only.

            /** @var \OrderState $new_order_state */
            $new_order_state = $params['newOrderStatus'];

            $new_order_state_id = (int) $new_order_state->id;
            $canceled_order_state_id = (int) \Configuration::get('PS_OS_CANCELED');

            if ($new_order_state_id === $canceled_order_state_id) {
                // Send notification about cancelled order paid by Comfino.
                ErrorLogger::init($module);

                try {
                    ApiClient::getInstance()->cancelOrder($params['id_order']);
                } catch (\Throwable $e) {
                    ApiClient::processApiError(
                        'Order cancellation error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e
                    );
                }
            }
        }
    }
}
