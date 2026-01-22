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
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Main;
use Comfino\View\FrontendManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Manages custom Comfino order statuses in PrestaShop.
 *
 * This class handles the complete lifecycle of custom order statuses used by the Comfino payment module:
 * - Creating and registering custom order statuses during module installation.
 * - Updating status definitions when upgrading the module.
 * - Removing statuses during uninstallation (with soft delete for used statuses).
 * - Synchronizing status changes between PrestaShop and Comfino API.
 * - Handling order cancellation events via PrestaShop hooks.
 *
 * Custom statuses include: CREATED, ACCEPTED, REJECTED, CANCELLED, CANCELLED_BY_SHOP
 */
final class ShopStatusManager
{
    /**
     * Default mapping between Comfino statuses and PrestaShop native statuses.
     *
     * Maps Comfino status codes to PrestaShop configuration keys for standard order states.
     * Used as fallback when custom statuses are not configured.
     *
     * @var array<string, string>
     */
    public const DEFAULT_STATUS_MAP = [
        StatusManager::STATUS_ACCEPTED => 'PS_OS_WS_PAYMENT',
        StatusManager::STATUS_CANCELLED => 'PS_OS_CANCELED',
        StatusManager::STATUS_REJECTED => 'PS_OS_CANCELED',
        StatusManager::STATUS_CANCELLED_BY_SHOP => 'PS_OS_CANCELED',
    ];

    /**
     * Definitions of custom Comfino order statuses.
     *
     * Each status includes:
     * - name: English display name
     * - name_pl: Polish display name
     * - color: Hex color code for admin panel display
     * - paid: Whether the order is considered paid in this status
     * - deleted: Whether the status is marked as deleted (soft delete flag)
     *
     * @var array<string, array{name: string, name_pl: string, color: string, paid: bool, deleted: bool}>
     */
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
            'name' => 'Canceled (Comfino)',
            'name_pl' => 'Anulowano (Comfino)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
        'COMFINO_' . StatusManager::STATUS_CANCELLED_BY_SHOP => [
            'name' => 'Canceled by shop (Comfino)',
            'name_pl' => 'Anulowano przez sklep (Comfino)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
    ];

    /**
     * Creates custom Comfino order statuses in PrestaShop database.
     *
     * This method is called during module installation or reset. It:
     * - Attempts to reuse existing status IDs from previous installations.
     * - Creates new statuses if they don't exist.
     * - Updates existing statuses if configuration keys are found.
     * - Handles multi-language status names (English and Polish).
     * - Stores status IDs in PrestaShop configuration.
     *
     * @param \Comfino $module Comfino module instance
     *
     * @return array Statistics about the operation containing:
     *               - statuses_created: Number of newly created statuses
     *               - statuses_updated: Number of updated statuses
     *               - statuses_create_failed: Number of failed creations
     *               - statuses_update_failed: Number of failed updates
     *               - operations: Detailed list of all operations performed
     */
    public static function addCustomOrderStatuses(\Comfino $module): array
    {
        $resultStats = [
            'statuses_created' => 0,
            'statuses_updated' => 0,
            'statuses_create_failed' => 0,
            'statuses_update_failed' => 0,
            'operations' => [],
        ];

        $languages = \Language::getLanguages(false);
        $previousOrderStatuses = self::getPreviousOrderStatuses($module->name, $languages);

        foreach (self::CUSTOM_ORDER_STATUSES as $statusCode => $statusDetails) {
            if ($comfinoStatusId = self::getStatusId($statusCode, $previousOrderStatuses, $statusDetails)) {
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
                        $resultStats = self::updateOrderStatus($orderStatus, $resultStats, $statusCode);
                    } catch (\PrestaShopDatabaseException|\PrestaShopException $e) {
                        FrontendManager::processError(
                            sprintf('Order status update error: %d', (int) $orderStatus->id),
                            $e, null, 'Order status update error.'
                        );

                        $resultStats['statuses_update_failed']++;
                        $resultStats['operations'][] = [
                            'name' => 'custom_status_update',
                            'success' => false,
                            'status' => $statusCode,
                            'error' => $e->getMessage(),
                        ];
                    }

                    continue;
                }
            }

            // Add a new status definition.
            $orderStatus = new \OrderState();
            $orderStatus->send_email = false;
            $orderStatus->invoice = false;
            $orderStatus->color = $statusDetails['color'];
            $orderStatus->unremovable = false;
            $orderStatus->logable = false;
            $orderStatus->module_name = COMFINO_MODULE_NAME;
            $orderStatus->paid = $statusDetails['paid'];

            foreach ($languages as $language) {
                $statusName = $language['iso_code'] === 'pl' ? $statusDetails['name_pl'] : $statusDetails['name'];
                $orderStatus->name[$language['id_lang']] = $statusName;
            }

            try {
                if ($orderStatus->add()) {
                    \Configuration::updateValue($statusCode, $orderStatus->id);

                    $resultStats['statuses_created']++;
                    $resultStats['operations'][] = [
                        'name' => 'custom_status_create',
                        'success' => true,
                        'status' => $statusCode,
                    ];
                } else {
                    $resultStats['statuses_create_failed']++;
                    $resultStats['operations'][] = [
                        'name' => 'custom_status_create',
                        'success' => false,
                        'status' => $statusCode,
                        'error' => 'Order status create error.',
                    ];
                }
            } catch (\PrestaShopDatabaseException|\PrestaShopException $e) {
                FrontendManager::processError(
                    sprintf('Order status adding error: %s, %d.', $statusCode, (int) $orderStatus->id),
                    $e, null, 'Order status adding error.'
                );

                $resultStats['statuses_create_failed']++;
                $resultStats['operations'][] = [
                    'name' => 'custom_status_create',
                    'success' => false,
                    'status' => $statusCode,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $resultStats;
    }

    /**
     * Updates all existing custom Comfino order statuses.
     *
     * Refreshes status definitions to match current CUSTOM_ORDER_STATUSES configuration.
     * This is useful after module upgrades to ensure status names, colors, and flags are current.
     * Only updates statuses that are already registered in PrestaShop configuration.
     *
     * @return array Statistics about the operation containing:
     *               - statuses_updated: Number of successfully updated statuses
     *               - statuses_update_failed: Number of failed updates
     *               - operations: Detailed list of all operations performed
     */
    public static function updateCustomOrderStatuses(\Comfino $module): array
    {
        $resultStats = [
            'statuses_updated' => 0,
            'statuses_update_failed' => 0,
            'operations' => [],
        ];

        $languages = \Language::getLanguages(false);
        $previousOrderStatuses = self::getPreviousOrderStatuses($module->name, $languages);

        foreach (self::CUSTOM_ORDER_STATUSES as $statusCode => $statusDetails) {
            if ($comfinoStatusId = self::getStatusId($statusCode, $previousOrderStatuses, $statusDetails)) {
                try {
                    $orderStatus = new \OrderState($comfinoStatusId);
                } catch (\PrestaShopDatabaseException|\PrestaShopException $e) {
                    FrontendManager::processError(
                        sprintf('Order status loading error: %s, %d', $statusCode, (int) $comfinoStatusId),
                        $e, null, 'Order status loading error.'
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
                        $resultStats = self::updateOrderStatus($orderStatus, $resultStats, $statusCode);
                    } catch (\PrestaShopException $e) {
                        FrontendManager::processError(
                            sprintf('Order status saving error: %s', $statusDetails['name']),
                            $e, null, 'Order status saving error.'
                        );

                        $resultStats['statuses_update_failed']++;
                        $resultStats['operations'][] = [
                            'name' => 'custom_status_update',
                            'success' => false,
                            'status' => $statusCode,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }
        }

        return $resultStats;
    }

    /**
     * Removes custom Comfino order statuses from PrestaShop.
     *
     * Called during module uninstallation. Uses a transactional approach to ensure data consistency:
     * - Hard delete: Completely removes statuses if never used in order history.
     * - Soft delete: Marks statuses as deleted if used to preserve database integrity.
     * - Removes configuration keys for all custom statuses.
     *
     * The operation is atomic - either all statuses are removed/updated or none (rollback on error).
     *
     * @return array Statistics about the operation containing:
     *               - statuses_removed: Number of hard-deleted statuses
     *               - statuses_updated: Number of soft-deleted statuses
     *               - statuses_remove_failed: Number of failed removals
     *               - statuses_update_failed: Number of failed soft deletes
     *               - status_config_options_removed: Number of configuration keys removed
     *               - operations: Detailed list of all operations performed
     */
    public static function removeCustomOrderStatuses(): array
    {
        $statusesUsed = false;
        $resultStats = [
            'statuses_removed' => 0,
            'statuses_updated' => 0,
            'statuses_remove_failed' => 0,
            'statuses_update_failed' => 0,
            'status_config_options_removed' => 0,
            'operations' => [],
        ];

        $db = \Db::getInstance();

        try {
            // Start transaction for atomic operations.
            $db->execute('START TRANSACTION', false);

            $orderStatusIds = self::getCustomOrderStatusIds();

            if (!($statusesUsed = self::comfinoOrderStatusesUsed($orderStatusIds))) {
                // Completely remove statuses only when never used to avoid database inconsistency and corruption.
                if (self::deleteComfinoOrderStatuses($orderStatusIds)) {
                    $resultStats['statuses_removed'] = $db->Affected_Rows();
                } else {
                    $resultStats['statuses_remove_failed'] = count(self::CUSTOM_ORDER_STATUSES);

                    throw new \RuntimeException('Failed to delete Comfino order statuses');
                }
            } else {
                // Mark statuses as deleted only (soft delete).
                if (self::softDeleteComfinoOrderStatuses($orderStatusIds)) {
                    $resultStats['statuses_updated'] = $db->Affected_Rows();
                } else {
                    $resultStats['statuses_update_failed'] = count(self::CUSTOM_ORDER_STATUSES);

                    throw new \RuntimeException('Failed to soft delete Comfino order statuses');
                }
            }

            if (ConfigManager::deleteConfigurationValues(array_keys(self::CUSTOM_ORDER_STATUSES))) {
                $resultStats['status_config_options_removed'] = count(self::CUSTOM_ORDER_STATUSES);
            }

            $resultStats['operations'][] = ['name' => 'custom_order_statuses_remove', 'success' => true];

            // Commit transaction.
            $db->execute('COMMIT', false);
        } catch (\Throwable $e) {
            // Rollback on any error.
            $db->execute('ROLLBACK', false);

            FrontendManager::processError(
                'Order status removal transaction failed',
                $e,
                null,
                'Order status removal error.'
            );

            if ($statusesUsed) {
                if ($resultStats['statuses_updated'] === 0) {
                    $resultStats['statuses_update_failed'] = count(self::CUSTOM_ORDER_STATUSES);
                }
            } else {
                if ($resultStats['statuses_removed'] === 0) {
                    $resultStats['statuses_remove_failed'] = count(self::CUSTOM_ORDER_STATUSES);
                }
            }

            $resultStats['operations'][] = ['name' => 'custom_order_statuses_remove', 'success' => false];
        }

        return $resultStats;
    }

    /**
     * Reinitializes custom order statuses during module reset or upgrade.
     *
     * This method performs a comprehensive status management operation:
     * 1. Attempts to load existing Comfino statuses from database.
     * 2. Repairs module_name if statuses exist but aren't properly linked.
     * 3. Creates missing statuses if needed.
     * 4. Updates all existing statuses to current definitions.
     *
     * Used during module reset and upgrade processes to ensure status consistency.
     *
     * @param \Comfino $module Comfino module instance
     *
     * @return array Combined statistics from add and update operations containing:
     *               - statuses_created: Number of newly created statuses
     *               - statuses_updated: Number of updated statuses
     *               - statuses_create_failed: Number of failed creations
     *               - statuses_update_failed: Number of failed updates
     *               - operations: Detailed list of all operations performed
     */
    public static function reinitializeCustomOrderStatuses(\Comfino $module): array
    {
        ErrorLogger::init();

        if (empty($rows = self::loadComfinoOrderStatuses($module->name))) {
            self::repairComfinoOrderStatuses($module->name);

            if (empty($rows = self::loadComfinoOrderStatuses($module->name))) {
                return self::addCustomOrderStatuses($module);
            }
        }

        $resultStats = [
            'statuses_created' => 0,
            'statuses_updated' => 0,
            'statuses_create_failed' => 0,
            'statuses_update_failed' => 0,
            'operations' => [],
        ];

        if (count(array_unique(array_column($rows, 'id_order_state'), SORT_NUMERIC)) !== count(self::CUSTOM_ORDER_STATUSES)) {
            $resultStats = self::addCustomOrderStatuses($module);
        }

        $updateStats = self::updateCustomOrderStatuses($module);

        $resultStats['statuses_updated'] += $updateStats['statuses_updated'];
        $resultStats['statuses_update_failed'] += $updateStats['statuses_update_failed'];
        $resultStats['operations'] = array_merge($resultStats['operations'], $updateStats['operations']);

        return $resultStats;
    }

    /**
     * Handles PrestaShop order status update events for Comfino orders.
     *
     * This method is called by PrestaShop's actionOrderStatusUpdate hook.
     * When a Comfino order is manually changed to "Canceled" status in admin panel,
     * this handler sends a cancellation notification to the Comfino API.
     *
     * Only processes orders paid via Comfino (checked via order->module).
     * Supports both numeric order IDs and order references based on configuration.
     *
     * @param array $params Hook parameters containing:
     *                      - id_order: Order ID
     *                      - newOrderStatus: New OrderState object
     *
     * @return void
     */
    public static function orderStatusUpdateEventHandler(array $params): void
    {
        DebugLogger::logEvent('[ORDER_STATUS_UPDATE]', "orderStatusUpdateEventHandler (order ID: $params[id_order])");

        try {
            $order = new \Order($params['id_order']);
        } catch (\PrestaShopDatabaseException|\PrestaShopException $e) {
            FrontendManager::processError(
                sprintf('Order loading error during cancel event: %s', $params['id_order']),
                $e, null, 'Order loading error during cancel event.'
            );

            return;
        }

        DebugLogger::logEvent('[ORDER_STATUS_UPDATE]', "Order $order->id loaded. (payment method: \"$order->payment\")");

        if ($order->module === COMFINO_MODULE_NAME) {
            // Process orders paid by Comfino only.

            /** @var \OrderState $newOrderState */
            $newOrderState = $params['newOrderStatus'];

            $newOrderStateId = (int) $newOrderState->id;
            $canceledOrderStateId = (int) \Configuration::get('PS_OS_CANCELED');

            if ($newOrderStateId === $canceledOrderStateId) {
                ErrorLogger::init();

                // Get order ID or reference based on configuration.
                if (ConfigManager::getConfigurationValue('COMFINO_USE_ORDER_REFERENCE', false)) {
                    $orderId = !empty($order->reference) ? $order->reference : (string) $order->id;
                } else {
                    $orderId = (string) $order->id;
                }

                try {
                    // Send notification about canceled order paid by Comfino.
                    ApiClient::getInstance()->cancelOrder($orderId);
                } catch (\Throwable $e) {
                    ApiClient::processApiError(
                        'Order cancellation error on page "' . Main::getRequestUri() . '" (Comfino API)', $e
                    );
                }
            }
        }
    }

    /**
     * Retrieves all custom Comfino order status IDs from configuration.
     *
     * @return int[] Array of order state IDs
     */
    private static function getCustomOrderStatusIds(): array
    {
        $orderStateIds = [];

        foreach (array_keys(self::CUSTOM_ORDER_STATUSES) as $orderStateCode) {
            if (!empty($orderStateId = (int) \Configuration::get($orderStateCode))) {
                $orderStateIds[] = $orderStateId;
            }
        }

        return $orderStateIds;
    }

    /**
     * Retrieves previously installed Comfino order statuses grouped by language.
     *
     * Used during status creation to detect and reuse existing status IDs from previous installations.
     * Prevents duplicate status creation and configuration key corruption.
     *
     * @param string $moduleName Module name to search for (usually "comfino")
     * @param array<array{id_lang: int, iso_code: string}> $languages Array of language definitions from PrestaShop
     *
     * @return array<string, array<string, array{id_order_state: int, id_lang: int, name: string}>>
     *               Nested array structure: [language_code][status_name] => status_data
     *               Example: ['en']['Order created - waiting for payment (Comfino)'] => ['id_order_state' => 123, ...]
     */
    private static function getPreviousOrderStatuses(string $moduleName, array $languages): array
    {
        $previousOrderStatuses = [];

        if (($existingStatuses = self::loadComfinoOrderStatuses($moduleName)) !== false) {
            $langCodeById = array_combine(array_column($languages, 'id_lang'), array_column($languages, 'iso_code'));

            foreach ($existingStatuses as $status) {
                if (isset($langCodeById[$status['id_lang']])) {
                    $previousOrderStatuses[$langCodeById[$status['id_lang']]][$status['name']] = $status;
                }
            }
        }

        return $previousOrderStatuses;
    }

    /**
     * Retrieves or reclaims the order status ID for a given status code.
     *
     * Implements intelligent status ID resolution:
     * 1. First checks PrestaShop configuration for existing status ID.
     * 2. If not found, attempts to reclaim ID from previous installations by matching status names.
     * 3. Restores configuration option if ID is successfully reclaimed.
     *
     * This prevents duplicate status creation when configuration keys are lost but statuses still exist.
     *
     * @param string $statusCode Configuration key for the status (e.g., "COMFINO_ACCEPTED")
     * @param array<string, array<string, array{id_order_state: int, id_lang: int, name: string}>> $previousOrderStatuses
     *                       Previously installed statuses grouped by language and name
     * @param array{name: string, name_pl: string, color: string, paid: bool, deleted: bool} $statusDetails
     *                       Status definition containing English and Polish names
     *
     * @return int Order status ID if found or reclaimed, 0 if not found
     */
    private static function getStatusId(string $statusCode, array $previousOrderStatuses, array $statusDetails): int
    {
        $comfinoStatusId = \Configuration::get($statusCode);

        if (empty($comfinoStatusId) || !\Validate::isInt($comfinoStatusId)) {
            // Status ID not found in configuration or is corrupted - try to use existing ID from previous installations.
            if (isset(
                $previousOrderStatuses['en'][$statusDetails['name']],
                $previousOrderStatuses['pl'][$statusDetails['name_pl']]
            )) {
                $idOrderStateEN = $previousOrderStatuses['en'][$statusDetails['name']]['id_order_state'];
                $idOrderStatePL = $previousOrderStatuses['pl'][$statusDetails['name_pl']]['id_order_state'];

                if ($idOrderStateEN === $idOrderStatePL) {
                    // Reuse existing status definition and load it in the next step with reclaimed ID.
                    $comfinoStatusId = $idOrderStateEN;

                    // Restore configuration option with proper status ID.
                    \Configuration::updateValue($statusCode, $comfinoStatusId);
                }
            }
        }

        return (int) $comfinoStatusId;
    }

    /**
     * Updates an existing order status and records the operation result.
     *
     * Attempts to save the updated OrderState object to the database and updates
     * the result statistics array with success or failure information.
     *
     * @param \OrderState $orderStatus PrestaShop OrderState object to update
     * @param array $resultStats Current statistics array containing operation counters and details
     * @param string $statusCode Configuration key for the status (e.g., "COMFINO_ACCEPTED")
     *
     * @return array Updated statistics array with:
     *               - statuses_updated: Incremented on success
     *               - statuses_update_failed: Incremented on failure
     *               - operations: Array with operation result entry added
     *
     * @throws \PrestaShopDatabaseException If database operation fails
     * @throws \PrestaShopException If PrestaShop validation fails
     */
    private static function updateOrderStatus(\OrderState $orderStatus, array $resultStats, string $statusCode): array
    {
        if ($orderStatus->update()) {
            $resultStats['statuses_updated']++;
            $resultStats['operations'][] = [
                'name' => 'custom_status_update',
                'success' => true,
                'status' => $statusCode,
            ];
        } else {
            $resultStats['statuses_update_failed']++;
            $resultStats['operations'][] = [
                'name' => 'custom_status_update',
                'success' => false,
                'status' => $statusCode,
                'error' => 'Order status update error.',
            ];
        }

        return $resultStats;
    }

    /**
     * Repairs orphaned Comfino order statuses by setting their module_name.
     *
     * Fixes statuses that exist in database but have incorrect or missing module_name.
     * This can happen after manual database modifications or PrestaShop upgrades.
     * Identifies Comfino statuses by searching for "comfino" in status names.
     *
     * @param string $moduleName Module name to set (usually "comfino")
     *
     * @return void
     */
    private static function repairComfinoOrderStatuses(string $moduleName): void
    {
        $dbPrefix = _DB_PREFIX_;
        $moduleName = pSQL($moduleName);

        \Db::getInstance()->execute(<<<SQL
            UPDATE
                {$dbPrefix}order_state os
                JOIN {$dbPrefix}order_state_lang osl ON osl.id_order_state = os.id_order_state
            SET
                os.module_name = '$moduleName'
            WHERE
                LOWER(osl.name) LIKE '%comfino%'
SQL
        );
    }

    /**
     * Loads all Comfino order statuses from database.
     *
     * Retrieves statuses linked to the Comfino module via module_name field.
     * Returns multi-language status data with translations.
     *
     * @param string $moduleName Module name to filter by (usually "comfino")
     *
     * @return array|false Array of status records with id_order_state, id_lang, and name fields,
     *                     or false if query fails
     */
    private static function loadComfinoOrderStatuses(string $moduleName)
    {
        $dbPrefix = _DB_PREFIX_;
        $moduleName = pSQL($moduleName);

        try {
            return \Db::getInstance()->executeS(<<<SQL
                SELECT
                    os.id_order_state, osl.id_lang, osl.name
                FROM
                    {$dbPrefix}order_state os
                    JOIN {$dbPrefix}order_state_lang osl ON osl.id_order_state = os.id_order_state
                WHERE
                    os.module_name = '$moduleName'
                ORDER BY
                    osl.id_lang, osl.name
SQL
            );
        } catch (\PrestaShopDatabaseException $e) {
            FrontendManager::processError('Comfino order statuses loading error', $e);
        }

        return false;
    }

    /**
     * Checks if any Comfino order statuses have been used in order history.
     *
     * Determines whether statuses can be safely hard-deleted or require soft delete.
     * If any status has been used in an order, all statuses should be soft-deleted
     * to maintain database referential integrity.
     *
     * @param int[] $orderStatusIds Array of order state IDs to check
     *
     * @return bool True if any status has been used in order history, false otherwise
     */
    private static function comfinoOrderStatusesUsed(array $orderStatusIds): bool
    {
        $dbPrefix = _DB_PREFIX_;
        $orderStateIdsSQL = implode(',', $orderStatusIds);

        return (bool) \Db::getInstance()->getValue(<<<SQL
            SELECT
                1
            FROM
                {$dbPrefix}order_state os
                JOIN {$dbPrefix}order_history oh ON oh.id_order_state = os.id_order_state
            WHERE
                os.id_order_state IN ($orderStateIdsSQL)
SQL
        );
    }

    /**
     * Permanently deletes Comfino order statuses from database (hard delete).
     *
     * Removes status records from both order_state and order_state_lang tables.
     * Should only be called when statuses have never been used in order history.
     *
     * @param int[] $orderStatusIds Array of order state IDs to delete
     *
     * @return bool True if all deletions succeeded, false otherwise
     */
    private static function deleteComfinoOrderStatuses(array $orderStatusIds): bool
    {
        $orderStateIdsSQL = implode(',', $orderStatusIds);

        return \Db::getInstance()->delete('order_state_lang', "id_order_state IN ($orderStateIdsSQL)")
            && \Db::getInstance()->delete('order_state', "id_order_state IN ($orderStateIdsSQL)");
    }

    /**
     * Marks Comfino order statuses as deleted without removing them (soft delete).
     *
     * Sets the 'deleted' flag to 1 in order_state table.
     * Used when statuses have been used in order history to preserve referential integrity.
     * Soft-deleted statuses remain in database but are hidden from admin interface.
     *
     * @param int[] $orderStatusIds Array of order state IDs to soft delete
     *
     * @return bool True if update succeeded, false otherwise
     */
    private static function softDeleteComfinoOrderStatuses(array $orderStatusIds): bool
    {
        $orderStateIdsSQL = implode(',', $orderStatusIds);

        return \Db::getInstance()->update('order_state', ['deleted' => 1], "id_order_state IN ($orderStateIdsSQL)");
    }
}
