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

use PrestaShop\PrestaShop\Core\Domain\Address\Query\GetRequiredFieldsForAddress;

if (!defined('_PS_VERSION_')) {
    return;
}

class ComfinoOfferModuleFrontController extends ModuleFrontController
{

    public function postProcess()
    {
        parent::postProcess();

        if (Tools::getIsset('type')) {
            echo $this->getContent();
            exit();
        }

        $cookie = Context::getContext()->cookie;
        $cookie->loan_amount = Tools::getValue('loan_amount');
        $cookie->loan_type = Tools::getValue('loan_type');
        $cookie->loan_term = Tools::getValue('loan_term');
        $cookie->write();

        echo json_encode([
            'status' => 'OK',
            'amount' => $cookie->loan_amount,
            'type' => $cookie->loan_type,
            'term' => $cookie->loan_term
        ]);
        exit();
    }

    private function getContent()
    {
        $cart = $this->context->cart;
        $total = $cart->getOrderTotal(true) * 100;
        $loanTerm = Configuration::get('COMFINO_LOAN_TERM');
        $result = ComfinoApi::getOffer($loanTerm, $total);
        $result = json_decode($result, true);
        $payment_infos = [];


        $set = false;
        if (is_array($result)) {
            foreach ($result as $item) {
                $loanAmount = round(((float)$item['instalmentAmount']) * ((float)$loanTerm) / 100, 2);
                if ($loanAmount < ($total / 100)) {
                    $loanAmount = round($total / 100, 2);
                }

                if (!$set) {
                    $cookie = Context::getContext()->cookie;
                    $cookie->loan_amount = $loanAmount;
                    $cookie->loan_type = $item['type'];
                    $cookie->loan_term = Configuration::get('COMFINO_LOAN_TERM');
                    $cookie->write();

                    $set = true;
                }

                $installmentAmount =  ((float)$item['instalmentAmount']) / 100;
                $rrso = ((float)$item['rrso']) * 100;
                $toPay =  ((float)$item['toPay']) / 100;
                $payment_infos[] = [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'icon' => str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $item['icon']),
                    'type' => $item['type'],
                    'sumAmount' => number_format($loanAmount, 2, ',', ' '),
                    'representativeExample' => $item['representativeExample'],
                    'rrso' => number_format($rrso, 2, ',', ' '),
                    'loanTerm' => Configuration::get('COMFINO_LOAN_TERM'),
                    'instalmentAmount' => number_format($installmentAmount, 2, ',', ' '),
                    'toPay' => number_format($toPay, 2, ',', ' '),
                ];
            }
        }

        return json_encode($payment_infos);
    }
}
