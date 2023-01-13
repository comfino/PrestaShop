<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/ErrorLogger.php';

class ComfinoOfferModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        ErrorLogger::init();

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
            'term' => (int) $cookie->loan_term,
        ]);

        exit;
    }

    private function getContent()
    {
        $cart = $this->context->cart;
        $total = $cart->getOrderTotal() * 100;
        $offers = ComfinoApi::getOffers($total);
        $payment_offers = [];
        $set = false;

        $locale = $this->context->currentLocale;
        $currency_iso = (new Currency($cart->id_currency))->iso_code;

        if (is_array($offers) && !isset($offers['errors'])) {
            foreach ($offers as $offer) {
                $loan_amount = round(((float) $offer['instalmentAmount']) * ((float) $offer['loanTerm']) / 100, 2);

                if ($loan_amount < ($total / 100)) {
                    $loan_amount = round($total / 100, 2);
                }

                if (!$set) {
                    $cookie = Context::getContext()->cookie;
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
                    'sumAmount' => $total / 100,
                    'sumAmountFormatted' => $locale->formatPrice($total / 100, $currency_iso),
                    'representativeExample' => $offer['representativeExample'],
                    'rrso' => round((float) $offer['rrso'] * 100, 2),
                    'loanTerm' => $offer['loanTerm'],
                    'instalmentAmount' => ((float) $offer['instalmentAmount']) / 100,
                    'instalmentAmountFormatted' => $locale->formatPrice(
                        ((float) $offer['instalmentAmount']) / 100,
                        $currency_iso
                    ),
                    'toPay' => ((float) $offer['toPay']) / 100,
                    'toPayFormatted' => $locale->formatPrice(((float) $offer['toPay']) / 100, $currency_iso),
                    'loanParameters' => array_map(static function ($loan_params) use ($total, $locale, $currency_iso) {
                        return [
                            'loanTerm' => $loan_params['loanTerm'],
                            'instalmentAmount' => ((float) $loan_params['instalmentAmount']) / 100,
                            'instalmentAmountFormatted' => $locale->formatPrice(
                                ((float) $loan_params['instalmentAmount']) / 100,
                                $currency_iso
                            ),
                            'toPay' => ((float) $loan_params['toPay']) / 100,
                            'toPayFormatted' => $locale->formatPrice(
                                ((float) $loan_params['toPay']) / 100,
                                $currency_iso
                            ),
                            'sumAmount' => $total / 100,
                            'sumAmountFormatted' => $locale->formatPrice($total / 100, $currency_iso),
                            'rrso' => round((float) $loan_params['rrso'] * 100, 2),
                        ];
                    }, $offer['loanParameters']),
                ];
            }
        }

        return json_encode($payment_offers);
    }
}
