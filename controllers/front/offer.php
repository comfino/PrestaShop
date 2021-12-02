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
            exit;
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

                $instalmentAmount = ((float) $offer['instalmentAmount']) / 100;
                $rrso = ((float) $offer['rrso']) * 100;
                $toPay = ((float) $offer['toPay']) / 100;

                $paymentOffers[] = [
                    'name' => $offer['name'],
                    'description' => $offer['description'],
                    'icon' => str_ireplace('<?xml version="1.0" encoding="UTF-8"?>', '', $offer['icon']),
                    'type' => $offer['type'],
                    'sumAmount' => number_format($total / 100, 2, ',', ' '),
                    'representativeExample' => $offer['representativeExample'],
                    'rrso' => number_format($rrso, 2, ',', ' '),
                    'loanTerm' => $offer['loanTerm'],
                    'instalmentAmount' => number_format($instalmentAmount, 2, ',', ' '),
                    'toPay' => number_format($toPay, 2, ',', ' '),
                    'loanParameters' => array_map(static function ($loanParams) use ($total) {
                        return [
                            'loanTerm' => $loanParams['loanTerm'],
                            'instalmentAmount' => number_format(((float) $loanParams['instalmentAmount']) / 100, 2, ',', ' '),
                            'toPay' => number_format(((float) $loanParams['toPay']) / 100, 2, ',', ' '),
                            'sumAmount' => number_format($total / 100, 2, ',', ' '),
                        ];
                    }, $offer['loanParameters']),
                ];
            }
        }

        return json_encode($paymentOffers);
    }
}
