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

class ComfinoOfferModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        parent::postProcess();

        if (Tools::getIsset('type')) {
            echo $this->getContent();
            exit;
        }

        $cookie = Context::getContext()->cookie;
        $cookie->loan_amount = Tools::getValue('loan_amount');
        $cookie->loan_type = Tools::getValue('loan_type');
        $cookie->loan_term = Tools::getValue('loan_term');
        $cookie->write();

        echo json_encode([
            'status' => 'OK',
            'amount' => (float) $cookie->loan_amount,
            'type' => $cookie->loan_type,
            'term' => (int) $cookie->loan_term
        ]);

        exit;
    }

    private function getContent()
    {
        $cart = $this->context->cart;
        $total = $cart->getOrderTotal() * 100;
        $offers = json_decode(ComfinoApi::getOffer($total), true);
        $paymentOffers = [];
        $set = false;

        if (is_array($offers)) {
            foreach ($offers as $offer) {
                $loanAmount = round(((float) $offer['instalmentAmount']) * ((float) $offer['loanTerm']) / 100, 2);

                if ($loanAmount < ($total / 100)) {
                    $loanAmount = round($total / 100, 2);
                }

                if (!$set) {
                    $cookie = Context::getContext()->cookie;
                    $cookie->loan_amount = $loanAmount;
                    $cookie->loan_type = $offer['type'];
                    $cookie->write();

                    $set = true;
                }

                $paymentOffers[] = [
                    'name' => $offer['name'],
                    'description' => $offer['description'],
                    'icon' => str_ireplace('<?xml version="1.0" encoding="UTF-8"?>', '', $offer['icon']),
                    'type' => $offer['type'],
                    'sumAmount' => $total / 100,
                    'sumAmountFormatted' => Tools::displayPrice($total / 100),
                    'representativeExample' => $offer['representativeExample'],
                    'rrso' => ((float) $offer['rrso']) * 100,
                    'loanTerm' => $offer['loanTerm'],
                    'instalmentAmount' => ((float) $offer['instalmentAmount']) / 100,
                    'instalmentAmountFormatted' => Tools::displayPrice(((float) $offer['instalmentAmount']) / 100),
                    'toPay' => ((float) $offer['toPay']) / 100,
                    'toPayFormatted' => Tools::displayPrice(((float) $offer['toPay']) / 100),
                    'loanParameters' => array_map(static function ($loanParams) use ($total) {
                        return [
                            'loanTerm' => $loanParams['loanTerm'],
                            'instalmentAmount' => ((float) $loanParams['instalmentAmount']) / 100,
                            'instalmentAmountFormatted' => Tools::displayPrice(
                                ((float) $loanParams['instalmentAmount']) / 100
                            ),
                            'toPay' => ((float) $loanParams['toPay']) / 100,
                            'toPayFormatted' => Tools::displayPrice(((float) $loanParams['toPay']) / 100),
                            'sumAmount' => $total / 100,
                            'sumAmountFormatted' => Tools::displayPrice($total / 100),
                            'rrso' => ((float) $loanParams['rrso']) * 100,
                        ];
                    }, $offer['loanParameters']),
                ];
            }
        }

        return json_encode($paymentOffers);
    }
}
