<?php
/**
 * 2007-2021 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author PrestaShop SA <contact@prestashop.com>
 * @copyright  2007-2021 PrestaShop SA
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version  Release: $Revision$
 *  International Registered Trademark & Property of PrestaShop SA
 */

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
    const CANCELLED_BY_SHOP = 'CANCELLED_BY_SHOP';
    const CANCELLED = 'CANCELLED';

    const COMFINO_CREATED = 'COMFINO_CREATED';
    const COMFINO_WAITING_FOR_FILLING = 'COMFINO_WAITING_FOR_FILLING';
    const COMFINO_WAITING_FOR_CONFIRMATION = 'COMFINO_WAITING_FOR_CONFIRMATION';
    const COMFINO_WAITING_FOR_PAYMENT = 'COMFINO_WAITING_FOR_PAYMENT';
    const COMFINO_ACCEPTED = 'COMFINO_ACCEPTED';
    const COMFINO_PAID = 'COMFINO_PAID';
    const COMFINO_REJECTED = 'COMFINO_REJECTED';
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
        self::CANCELLED_BY_SHOP => self::COMFINO_CANCELLED_BY_SHOP,
        self::CANCELLED => self::COMFINO_CANCELLED,
    ];

    /**
     * After setting notification status we want some statuses to change to internal prestashop statuses right away
     */
    const CHANGE_STATUS_MAP = [
        self::CREATED => 'PS_OS_BANKWIRE',
        self::WAITING_FOR_FILLING => 'PS_OS_BANKWIRE',
        self::WAITING_FOR_CONFIRMATION => 'PS_OS_BANKWIRE',
        self::WAITING_FOR_PAYMENT => 'PS_OS_WS_PAYMENT',
        self::ACCEPTED => 'PS_OS_WS_PAYMENT',
        self::PAID => 'PS_OS_WS_PAYMENT',
        self::CANCELLED => 'PS_OS_CANCELED',
        self::CANCELLED_BY_SHOP => 'PS_OS_CANCELED',
        self::REJECTED => 'PS_OS_CANCELED',
    ];

    const ADD_ORDER_STATUSES = [
        self::COMFINO_CREATED => 'Order created (comfino)',
        self::COMFINO_WAITING_FOR_FILLING => 'Waiting for form\'s filling (comfino)',
        self::COMFINO_WAITING_FOR_CONFIRMATION => 'Waiting for form\'s confirnmation (comfino)',
        self::COMFINO_WAITING_FOR_PAYMENT => 'Waiting for payment (comfino)',
        self::COMFINO_ACCEPTED => 'Credit granted (comfino)',
        self::COMFINO_PAID => 'Paid (comfino)',
        self::COMFINO_REJECTED => 'Credit rejected (comfino)',
        self::COMFINO_CANCELLED_BY_SHOP => 'Cancelled by shop (comfino)',
        self::COMFINO_CANCELLED => 'Cancelled (comfino)',
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
        ]
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
     * @param array $data
     *
     * @return bool
     * @throws PrestaShopException
     */
    public static function createOrder($data)
    {
        $order = new self();
        $order->id_comfino = $data['id_comfino'];
        $order->id_customer = $data['id_customer'];
        $order->order_status = $data['order_status'];
        $order->legalize_link = $data['legalize_link'];
        $order->self_link = $data['self_link'];
        $order->cancel_link = $data['cancel_link'];

        return $order->save();
    }

    public static function getAllOrders()
    {
        $sql = sprintf("SELECT * FROM %scomfino_orders", _DB_PREFIX_);
        if ($row = Db::getInstance()->executeS($sql)) {
            $result = [];

            foreach ($row as $item) {
                $customer = new Customer($item['id_customer']);

                $customer_email = "";
                if ($customer != null) {
                    $customer_email = $customer->email;
                }

                $order = new Order($item['id_comfino']);

                $status = $order->getCurrentOrderState();
                $context = Context::getContext();

                $result[] = [
                    'id_comfino' => $item['id_comfino'],
                    'customer' => $customer_email,
                    'order_status' => $status->name[$context->language->id],
                    'self_link' => $item['self_link'],
                    'cancel_link' => $item['cancel_link'],
                    'legalize_link' => $item['legalize_link']
                ];
            }
            return $result;
        }

        return [];
    }

    /**
     * @param $order_id
     * @param $key
     * @param $value
     *
     * @return bool
     */
    public static function updateOrder($order_id, $key, $value)
    {
        $sql = sprintf(
            'UPDATE %scomfino_orders SET %s=\'%s\' WHERE id_comfino=\'%s\'',
            _DB_PREFIX_,
            $key,
            $value,
            $order_id
        );
        if ($row = Db::getInstance()->execute($sql)) {
            if ($row == null) {
                return false;
            }

            return true;
        }

        return false;
    }

    public static function getState($state)
    {
        $state = Tools::strtoupper($state);

        if (in_array($state, array_keys(self::STATUSES))) {
            return self::STATUSES[$state];
        }

        return 'PS_OS_ERROR';
    }

    public static function processState($orderId, $status)
    {
        $order = new OrderCore($orderId);

        if (!ValidateCore::isLoadedObject($order)) {
            throw new \Exception(sprintf('Order not found by id: %s', $orderId));
        }

        $order->setCurrentState(Configuration::get(self::getState($status)));
        $order->save();

        self::setSecondState($status, $order);
    }

    private static function setSecondState(string $status, OrderCore $order): void
    {
        if (!isset(self::CHANGE_STATUS_MAP[$status])) {
            return;
        }

        if (self::wasSecondStatusSetInHistory($status, $order)){
            return;
        }

        $order->setCurrentState(ConfigurationCore::get(self::CHANGE_STATUS_MAP[$status]));
        $order->save();
    }

    private static function wasSecondStatusSetInHistory(string $status, OrderCore $order): bool
    {
        $idOrderState = ConfigurationCore::get(self::CHANGE_STATUS_MAP[$status]);
        foreach ($order->getHistory(0) as $historyElement) {
            if ($historyElement['id_order_state'] === $idOrderState) {
                return true;
            }
        }

        return false;
    }
}
