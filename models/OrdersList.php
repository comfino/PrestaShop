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
        $comfinoStates = [
            'CREATED',
            'WAITING_FOR_FILLING',
            'WAITING_FOR_CONFIRMATION',
            //'WAITING_FOR_PAYMENT',
            //'ACCEPTED',
            'REJECTED',
        ];
        $state = Tools::strtoupper($state);

        if (in_array($state, $comfinoStates)) {
            return Configuration::get("COMFINO_$state");
        }

        if ($state == 'CANCELLED') {
            return Configuration::get('PS_OS_CANCELED');
        }

        if ($state == 'CANCELLED_BY_SHOP') {
            return Configuration::get('PS_OS_CANCELED');
        }

        if ($state == 'ACCEPTED' || $state == 'WAITING_FOR_PAYMENT' || $state == 'PAID') {
            return Configuration::get('PS_OS_WS_PAYMENT');
        }

        return Configuration::get('PS_OS_ERROR');
    }
}
