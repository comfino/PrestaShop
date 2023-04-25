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

require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/PresentationType.php';

class ConfigManager
{
    private $last_widget_code_hash = 'bde49851ffc0fd8239eb5d086c8165d4';

    /**
     * @return void
     */
    public function initConfigurationValues()
    {
        if (Configuration::hasKey('COMFINO_API_KEY')) {
            // Avoid overwriting of existing configuration if plugin is reinstalled/upgraded.
            return;
        }

        $initial_config_values = [
            'COMFINO_PAYMENT_PRESENTATION' => ComfinoPresentationType::ICON_AND_TEXT,
            'COMFINO_PAYMENT_TEXT' => '(Raty | Kup Teraz, Zapłać Póżniej | Finansowanie dla Firm)',
            'COMFINO_MINIMAL_CART_AMOUNT' => 30,
            'COMFINO_WIDGET_ENABLED' => false,
            'COMFINO_WIDGET_KEY' => '',
            'COMFINO_WIDGET_PRICE_SELECTOR' => COMFINO_PS_17 ? 'span.current-price-value' : 'span[itemprop=price]',
            'COMFINO_WIDGET_TARGET_SELECTOR' => 'div.product-actions',
            'COMFINO_WIDGET_TYPE' => 'with-modal',
            'COMFINO_WIDGET_OFFER_TYPE' => 'CONVENIENT_INSTALLMENTS',
            'COMFINO_WIDGET_EMBED_METHOD' => 'INSERT_INTO_LAST',
            'COMFINO_WIDGET_CODE' => $this->getInitialWidgetCode(),
        ];

        foreach ($initial_config_values as $opt_name => $opt_value) {
            Configuration::updateValue($opt_name, $opt_value);
        }
    }

    /**
     * @return bool
     */
    public function addCustomOrderStatuses()
    {
        $languages = Language::getLanguages(false);

        foreach (OrdersList::CUSTOM_ORDER_STATUSES as $status_code => $status_details) {
            $comfino_status_id = Configuration::get($status_code);

            if (!empty($comfino_status_id) && Validate::isInt($comfino_status_id)) {
                $order_status = new OrderState($comfino_status_id);

                if (Validate::isLoadedObject($order_status)) {
                    // Update existing status definition.
                    $order_status->color = $status_details['color'];
                    $order_status->paid = $status_details['paid'];
                    $order_status->deleted = $status_details['deleted'];

                    $order_status->update();

                    continue;
                }
            } elseif ($status_details['deleted']) {
                // Ignore deleted statuses in first time plugin installations.
                continue;
            }

            // Add a new status definition.
            $order_status = new OrderState();
            $order_status->send_email = false;
            $order_status->invoice = false;
            $order_status->color = $status_details['color'];
            $order_status->unremovable = false;
            $order_status->logable = false;
            $order_status->module_name = $this->name;
            $order_status->paid = $status_details['paid'];

            foreach ($languages as $language) {
                $status_name = $language['iso_code'] === 'pl' ? $status_details['name_pl'] : $status_details['name'];
                $order_status->name[$language['id_lang']] = $status_name;
            }

            if ($order_status->add()) {
                Configuration::updateValue($status_code, $order_status->id);
            }
        }

        return true;
    }

    /**
     * @return void
     */
    public function updateOrderStatuses()
    {
        $languages = Language::getLanguages(false);

        foreach (OrdersList::CUSTOM_ORDER_STATUSES as $status_code => $status_details) {
            $comfino_status_id = Configuration::get($status_code);

            if (!empty($comfino_status_id) && Validate::isInt($comfino_status_id)) {
                $order_status = new OrderState($comfino_status_id);

                if (Validate::isLoadedObject($order_status)) {
                    // Update existing status definition.
                    foreach ($languages as $language) {
                        $status_name = $language['iso_code'] === 'pl' ? $status_details['name_pl'] : $status_details['name'];
                        $order_status->name[$language['id_lang']] = $status_name;
                    }

                    $order_status->color = $status_details['color'];
                    $order_status->paid = $status_details['paid'];
                    $order_status->deleted = $status_details['deleted'];

                    $order_status->save();
                }
            }
        }
    }

    /**
     * @return void
     */
    public function updateWidgetCode()
    {
        $initial_widget_code = $this->getInitialWidgetCode();
        $current_widget_code = trim(Configuration::get('COMFINO_WIDGET_CODE'));

        if (md5($current_widget_code) === $this->last_widget_code_hash) {
            // Widget code not changed since last installed version - safely replace with new one.
            Configuration::updateValue('COMFINO_WIDGET_CODE', $initial_widget_code);
        }
    }

    /**
     * @return string
     */
    public function getInitialWidgetCode()
    {
        return trim("
var script = document.createElement('script');
script.onload = function () {
    ComfinoProductWidget.init({
        widgetKey: '{WIDGET_KEY}',
        priceSelector: '{WIDGET_PRICE_SELECTOR}',
        widgetTargetSelector: '{WIDGET_TARGET_SELECTOR}',
        priceObserverSelector: null,        
        type: '{WIDGET_TYPE}',
        offerType: '{OFFER_TYPE}',
        embedMethod: '{EMBED_METHOD}',
        numOfInstallments: 0,
        priceObserverLevel: 0,
        price: null,
        callbackBefore: function () {},
        callbackAfter: function () {},
        onOfferRendered: function (jsonResponse, widgetTarget, widgetNode) { },
        onGetPriceElement: function (priceSelector, priceObserverSelector) { return null; }
    });
};
script.src = '{WIDGET_SCRIPT_URL}';
script.async = true;
document.getElementsByTagName('head')[0].appendChild(script);
");
    }
}
