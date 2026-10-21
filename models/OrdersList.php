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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/StatusApplicationContext.php';

/**
 * Class OrdersList
 *
 * @property int $id_comfino
 * @property int $id_customer
 * @property string $order_status
 * @property string $legalize_link
 * @property string $self_link
 * @property string $cancel_link
 */
class OrdersList extends ObjectModel
{
    const CREATED = 'CREATED';
    const WAITING_FOR_FILLING = 'WAITING_FOR_FILLING';
    const WAITING_FOR_CONFIRMATION = 'WAITING_FOR_CONFIRMATION';
    const WAITING_FOR_PAYMENT = 'WAITING_FOR_PAYMENT';
    const ACCEPTED = 'ACCEPTED';
    const PAID = 'PAID';
    const REJECTED = 'REJECTED';
    const RESIGN = 'RESIGN';
    const CANCELLED_BY_SHOP = 'CANCELLED_BY_SHOP';
    const CANCELLED = 'CANCELLED';

    const COMFINO_CREATED = 'COMFINO_CREATED';
    const COMFINO_WAITING_FOR_FILLING = 'COMFINO_WAITING_FOR_FILLING';
    const COMFINO_WAITING_FOR_CONFIRMATION = 'COMFINO_WAITING_FOR_CONFIRMATION';
    const COMFINO_WAITING_FOR_PAYMENT = 'COMFINO_WAITING_FOR_PAYMENT';
    const COMFINO_ACCEPTED = 'COMFINO_ACCEPTED';
    const COMFINO_PAID = 'COMFINO_PAID';
    const COMFINO_REJECTED = 'COMFINO_REJECTED';
    const COMFINO_RESIGN = 'COMFINO_RESIGN';
    const COMFINO_CANCELLED_BY_SHOP = 'COMFINO_CANCELLED_BY_SHOP';
    const COMFINO_CANCELLED = 'COMFINO_CANCELLED';

    const STATUSES = [
        self::CREATED => self::COMFINO_CREATED,
        self::WAITING_FOR_FILLING => self::COMFINO_WAITING_FOR_FILLING,
        self::WAITING_FOR_CONFIRMATION => self::COMFINO_WAITING_FOR_CONFIRMATION,
        self::WAITING_FOR_PAYMENT => self::COMFINO_WAITING_FOR_PAYMENT,
        self::ACCEPTED => self::COMFINO_ACCEPTED,
        self::REJECTED => self::COMFINO_REJECTED,
        self::PAID => self::COMFINO_PAID,
        self::RESIGN => self::COMFINO_RESIGN,
        self::CANCELLED_BY_SHOP => self::COMFINO_CANCELLED_BY_SHOP,
        self::CANCELLED => self::COMFINO_CANCELLED,
    ];

    /**
     * After setting notification status we want some statuses to change to internal PrestaShop statuses right away.
     */
    const CHANGE_STATUS_MAP = [
        self::ACCEPTED => 'PS_OS_WS_PAYMENT',
        self::CANCELLED => 'PS_OS_CANCELED',
        self::CANCELLED_BY_SHOP => 'PS_OS_CANCELED',
        self::REJECTED => 'PS_OS_CANCELED',
        self::RESIGN => 'PS_OS_CANCELED',
    ];

    const CUSTOM_ORDER_STATUSES = [
        self::COMFINO_CREATED => [
            'name' => 'Order created - waiting for payment (ComfinoPay)',
            'name_pl' => 'Zamówienie utworzone - oczekiwanie na płatność (ComfinoPay)',
            'color' => '#87b921',
            'paid' => false,
            'deleted' => false,
        ],
        self::COMFINO_WAITING_FOR_FILLING => [
            'name' => 'Waiting for form\'s filling (ComfinoPay)',
            'name_pl' => 'Oczekiwanie na wypełnienie formularza (ComfinoPay)',
            'color' => '#ffffff',
            'paid' => false,
            'deleted' => true,
        ],
        self::COMFINO_WAITING_FOR_CONFIRMATION => [
            'name' => 'Waiting for form\'s confirmation (ComfinoPay)',
            'name_pl' => 'Oczekiwanie na zatwierdzenie formularza (ComfinoPay)',
            'color' => '#ffffff',
            'paid' => false,
            'deleted' => true,
        ],
        self::COMFINO_WAITING_FOR_PAYMENT => [
            'name' => 'Waiting for payment (ComfinoPay)',
            'name_pl' => 'Oczekiwanie na płatność (ComfinoPay)',
            'color' => '#ffffff',
            'paid' => false,
            'deleted' => true,
        ],
        self::COMFINO_ACCEPTED => [
            'name' => 'Credit granted (ComfinoPay)',
            'name_pl' => 'Kredyt udzielony (ComfinoPay)',
            'color' => '#227b34',
            'paid' => true,
            'deleted' => false,
        ],
        self::COMFINO_PAID => [
            'name' => 'Paid (ComfinoPay)',
            'name_pl' => 'Zapłacono (ComfinoPay)',
            'color' => '#227b34',
            'paid' => true,
            'deleted' => true,
        ],
        self::COMFINO_REJECTED => [
            'name' => 'Credit rejected (ComfinoPay)',
            'name_pl' => 'Wniosek kredytowy odrzucony (ComfinoPay)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
        self::COMFINO_RESIGN => [
            'name' => 'Resigned (ComfinoPay)',
            'name_pl' => 'Odstąpiono (ComfinoPay)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
        self::COMFINO_CANCELLED_BY_SHOP => [
            'name' => 'Cancelled by shop (ComfinoPay)',
            'name_pl' => 'Anulowano przez sklep (ComfinoPay)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
        self::COMFINO_CANCELLED => [
            'name' => 'Cancelled (ComfinoPay)',
            'name_pl' => 'Anulowano (ComfinoPay)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
    ];

    const IGNORED_STATUSES = [
        self::WAITING_FOR_FILLING,
        self::WAITING_FOR_CONFIRMATION,
        self::WAITING_FOR_PAYMENT,
        self::PAID,
    ];

    public static $definition = [
        'table' => 'comfino_orders',
        'primary' => 'id',
        'multilang' => false,
        'fields' => [
            'id_comfino' => ['type' => self::TYPE_INT],
            'id_customer' => ['type' => self::TYPE_INT],
            'order_status' => ['type' => self::TYPE_STRING],
            'legalize_link' => ['type' => self::TYPE_STRING],
            'self_link' => ['type' => self::TYPE_STRING],
            'cancel_link' => ['type' => self::TYPE_STRING],
        ],
    ];

    /**
     * OrdersList constructor.
     *
     * @param null $id
     * @param null $id_lang
     * @param null $id_shop
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        parent::__construct($id, $id_lang, $id_shop);
    }

    /**
     * Custom states after which ComfinoPay considers the financing application closed.
     */
    const TERMINAL_STATUSES = [
        self::COMFINO_CANCELLED,
        self::COMFINO_REJECTED,
        self::COMFINO_CANCELLED_BY_SHOP,
        self::COMFINO_RESIGN,
    ];

    /**
     * @param string $order_id
     * @param string $status
     *
     * @return bool
     *
     * @throws Exception
     */
    public static function processState($order_id, $status)
    {
        /* Every state change made here fires actionOrderStatusPostUpdate - the context tells the module's hook that
           the change comes from ComfinoPay and must not be reported back to it. */
        Comfino\StatusApplicationContext::enter();

        try {
            return self::applyState($order_id, $status);
        } finally {
            Comfino\StatusApplicationContext::leave();
        }
    }

    /**
     * Tells whether any of the given order states has ever been set on the order.
     *
     * Reads order_history directly - Order::getHistory() caches its result for the whole request, so it misses
     * states added in the meantime. The query cache is bypassed for the same reason.
     *
     * @param int $order_id
     * @param int[] $order_state_ids
     *
     * @return bool
     */
    public static function hasStateInHistory($order_id, array $order_state_ids)
    {
        $order_state_ids = self::filterIds($order_state_ids);

        if (!Validate::isUnsignedId($order_id) || (int) $order_id === 0 || !count($order_state_ids)) {
            return false;
        }

        $query = new DbQuery();
        $query->select('1')
            ->from('order_history')
            ->where('`id_order` = ' . (int) $order_id)
            ->where('`id_order_state` IN (' . implode(',', $order_state_ids) . ')')
            ->limit(1);

        return (bool) Db::getInstance()->getValue($query, false);
    }

    /**
     * Returns the newest order state recorded in the order history.
     *
     * Inside actionOrderStatusPostUpdate this is still the state the order had before the change, because
     * PrestaShop adds the history row only after the hook has run.
     *
     * @param int $order_id
     *
     * @return int 0 when the order has no history
     */
    public static function getLastHistoryState($order_id)
    {
        if (!Validate::isUnsignedId($order_id) || (int) $order_id === 0) {
            return 0;
        }

        $query = new DbQuery();
        $query->select('`id_order_state`')
            ->from('order_history')
            ->where('`id_order` = ' . (int) $order_id)
            ->orderBy('`date_add` DESC, `id_order_history` DESC')
            ->limit(1);

        return (int) Db::getInstance()->getValue($query, false);
    }

    /**
     * Reduces a list of IDs to unique positive integers, safe to be inlined into an IN () clause.
     *
     * @param array $ids
     *
     * @return int[]
     */
    private static function filterIds(array $ids)
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static function ($id) { return $id > 0; }
        )));
    }

    /**
     * @param string $order_id
     * @param string $status
     *
     * @return bool
     *
     * @throws Exception
     */
    private static function applyState($order_id, $status)
    {
        if (in_array($status, self::IGNORED_STATUSES, true)) {
            return true;
        }

        if (!Validate::isUnsignedId($order_id) || (int) $order_id === 0) {
            throw new Exception('Invalid order id.');
        }

        $order = new OrderCore((int) $order_id);

        if (!ValidateCore::isLoadedObject($order)) {
            throw new Exception(sprintf('Order not found by id: %s', $order_id));
        }

        $internal_status_new = self::getState($status);

        if ($internal_status_new === 'PS_OS_ERROR') {
            return false;
        }

        $internal_status_current_id = (int) $order->getCurrentState();
        $internal_status_new_id = (int) Configuration::get($internal_status_new);

        if (self::hasStateInHistory($order->id, [$internal_status_new_id])) {
            // Repeated notification - the state has already been applied, so the order is left as it is.
            return true;
        }

        if ($internal_status_new_id !== $internal_status_current_id) {
            $order->setCurrentState($internal_status_new_id);

            self::setSecondState($status, $order);
        }

        return true;
    }

    /**
     * @param string $state
     *
     * @return string
     */
    private static function getState($state)
    {
        $state = Tools::strtoupper($state);

        if (array_key_exists($state, self::STATUSES)) {
            return self::STATUSES[$state];
        }

        return 'PS_OS_ERROR';
    }

    private static function setSecondState($status, OrderCore $order)
    {
        if (!array_key_exists($status, self::CHANGE_STATUS_MAP)) {
            return;
        }

        if (self::wasSecondStatusSetInHistory($status, $order)) {
            return;
        }

        $order->setCurrentState(Configuration::get(self::CHANGE_STATUS_MAP[$status]));
    }

    private static function wasSecondStatusSetInHistory($status, OrderCore $order)
    {
        return self::hasStateInHistory($order->id, [Configuration::get(self::CHANGE_STATUS_MAP[$status])]);
    }
}
