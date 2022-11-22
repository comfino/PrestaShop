<?php
/**
 * 2007-2022 PrestaShop
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
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2022 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'comfino_orders`';
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'comfino_orders` (
    `id_comfino_orders` int(55) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `id_comfino` int(55) NOT NULL,
    `id_customer` int(55) NOT NULL,
    `order_status` varchar(255) NOT NULL,
    `legalize_link` varchar(255) NOT NULL,
    `self_link` varchar(255) NOT NULL,
    `cancel_link` varchar(255) NOT NULL
) ENGINE=' . _MYSQL_ENGINE_ . ' COLLATE utf8mb4_bin;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
