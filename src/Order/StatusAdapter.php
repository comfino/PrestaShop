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

use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Common\Shop\OrderStatusAdapterInterface;
use Comfino\ConfigManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

class StatusAdapter implements OrderStatusAdapterInterface
{
    public function setStatus($orderId, $status): void
    {
        $order = new \Order($orderId);

        if (!\ValidateCore::isLoadedObject($order)) {
            throw new \RuntimeException(sprintf('Order not found by id: %s', $orderId));
        }

        $input_status = \Tools::strtoupper($status);

        if (in_array($input_status, StatusManager::STATUSES, true)) {
            $custom_status_new = "COMFINO_$input_status";
        } else {
            return;
        }

        $current_internal_status_id = (int) $order->getCurrentState();
        $new_custom_status_id = (int) \Configuration::get($custom_status_new);

        if ($new_custom_status_id !== $current_internal_status_id) {
            $order->setCurrentState($new_custom_status_id);

            $status_map = ConfigManager::getConfigurationValue('COMFINO_STATUS_MAP');

            if (!array_key_exists($input_status, $status_map)) {
                return;
            }

            $new_internal_status_id = (int) \Configuration::get($status_map[$input_status]);

            foreach ($order->getHistory(0) as $history_entry) {
                if ($history_entry['id_order_state'] === $new_internal_status_id) {
                    return;
                }
            }

            $order->setCurrentState($new_internal_status_id);
        }
    }
}
