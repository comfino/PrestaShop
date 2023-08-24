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

use Comfino\Api;
use Comfino\ErrorLogger;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Api.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ErrorLogger.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Tools.php';

class ComfinoOfferModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        Api::init();
        ErrorLogger::init();

        parent::postProcess();

        if (Tools::getIsset('type')) {
            echo $this->getContent();
            exit;
        }

        $cookie = (new \Comfino\Tools($this->context))->getCookie();
        $cookie->loan_amount = Tools::getValue('loan_amount');
        $cookie->loan_type = Tools::getValue('loan_type');
        $cookie->loan_term = Tools::getValue('loan_term');
        $cookie->write();

        echo json_encode([
            'status' => 'OK',
            'amount' => (float) $cookie->loan_amount,
            'type' => $cookie->loan_type,
            'term' => (int) $cookie->loan_term,
        ]);

        exit;
    }

    private function getContent()
    {
        $cart = $this->context->cart;
        $tools = new \Comfino\Tools($this->context);

        $total = $cart->getOrderTotal() * 100;
        $offers = Api::getOffers($total);
        $payment_offers = [];
        $set = false;

        if (is_array($offers) && !isset($offers['errors'])) {
            foreach ($offers as $offer) {
                $loan_amount = round(((float) $offer['instalmentAmount']) * ((float) $offer['loanTerm']) / 100, 2);

                if ($loan_amount < ($total / 100)) {
                    $loan_amount = round($total / 100, 2);
                }

                if (!$set) {
                    $cookie = $tools->getCookie();
                    $cookie->loan_amount = $loan_amount;
                    $cookie->loan_type = $offer['type'];
                    $cookie->write();

                    $set = true;
                }

                $payment_offers[] = [
                    'name' => $offer['name'],
                    'description' => $offer['description'],
                    'icon' => str_ireplace('<?xml version="1.0" encoding="UTF-8"?>', '', $offer['icon']),
                    'type' => $offer['type'],
                    'representativeExample' => $offer['representativeExample'],
                    'loanTerm' => $offer['loanTerm'],
                    'instalmentAmount' => ((float) $offer['instalmentAmount']) / 100,
                    'instalmentAmountFormatted' => $tools->formatPrice(
                        ((float) $offer['instalmentAmount']) / 100,
                        $cart->id_currency
                    ),
                    'sumAmount' => $total / 100,
                    'sumAmountFormatted' => $tools->formatPrice($total / 100, $cart->id_currency),
                    'toPay' => ((float) $offer['toPay']) / 100,
                    'toPayFormatted' => $tools->formatPrice(((float) $offer['toPay']) / 100, $cart->id_currency),
                    'commission' => ((int) $offer['toPay'] - $total) / 100,
                    'commissionFormatted' => $tools->formatPrice(((int) $offer['toPay'] - $total) / 100, $cart->id_currency),
                    'rrso' => round((float) $offer['rrso'] * 100, 2),
                    'loanParameters' => array_map(static function ($loan_params) use ($total, $tools, $cart) {
                        return [
                            'loanTerm' => $loan_params['loanTerm'],
                            'instalmentAmount' => ((float) $loan_params['instalmentAmount']) / 100,
                            'instalmentAmountFormatted' => $tools->formatPrice(
                                ((float) $loan_params['instalmentAmount']) / 100,
                                $cart->id_currency
                            ),
                            'sumAmount' => $total / 100,
                            'sumAmountFormatted' => $tools->formatPrice($total / 100, $cart->id_currency),
                            'toPay' => ((float) $loan_params['toPay']) / 100,
                            'toPayFormatted' => $tools->formatPrice(
                                ((float) $loan_params['toPay']) / 100,
                                $cart->id_currency
                            ),
                            'commission' => ((int) $loan_params['toPay'] - $total) / 100,
                            'commissionFormatted' => $tools->formatPrice(((int) $loan_params['toPay'] - $total) / 100, $cart->id_currency),
                            'rrso' => round((float) $loan_params['rrso'] * 100, 2),
                        ];
                    }, $offer['loanParameters']),
                ];
            }
        }

        return json_encode($payment_offers);
    }
}
