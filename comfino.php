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

if (!defined('COMFINO_PS_17')) {
    define('COMFINO_PS_17', version_compare(_PS_VERSION_, '1.7', '>='));
}

if (!defined('COMFINO_VERSION')) {
    define('COMFINO_VERSION', '4.0.0');
}

class Comfino extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'comfino';
        $this->tab = 'payments_gateways';
        $this->version = '4.0.0';
        $this->author = 'Comfino';
        $this->module_key = '3d3e14c65281e816da083e34491d5a7f';

        $this->bootstrap = true;
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->controllers = [
            'payment',
            'paymentstate',
            'paywall',
            'cacheinvalidate',
            'error',
            'script',
            'transactionstatus',
            'configuration',
            'availableoffertypes',
        ];

        parent::__construct();

        $this->displayName = $this->l('Comfino payments');
        $this->confirmUninstall = $this->l('Are you sure to uninstall Comfino payments?');

        $this->description = $this->l(
            'Comfino is an innovative payment method for customers of e-commerce stores! ' .
            'These are installment payments, deferred (buy now, pay later) and corporate ' .
            'payments available on one platform with the help of quick integration. Grow your business with Comfino!'
        );

        require_once __DIR__ . '/vendor/autoload.php';

        // Initialize Comfino plugin.
        Comfino\Main::init($this);
    }

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        return Comfino\Main::install($this);
    }

    public function uninstall(): bool
    {
        if (parent::uninstall()) {
            return Comfino\Main::uninstall($this);
        }

        return false;
    }

    /**
     * Renders configuration form.
     */
    public function getContent(): string
    {
        return Comfino\Main::getContent($this);
    }

    /**
     * Renders Comfino paywall iframe at payment methods list compatible with PrestaShop 1.6.*.
     *
     * @return string|void
     */
    public function hookPayment(array $params)
    {
        return Comfino\Main::renderPaywallIframe($this, $params);
    }

    /**
     * Renders Comfino paywall iframe at payment methods list compatible with PrestaShop 1.7.* and 8.*.
     *
     * @return PrestaShop\PrestaShop\Core\Payment\PaymentOption[]|void
     *
     * @throws PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function hookPaymentOptions(array $params)
    {
        return Comfino\Main::renderPaywallIframe($this, $params);
    }

    /**
     * @throws PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function hookPaymentReturn(array $params): string
    {
        if (!COMFINO_PS_17 || !$this->active) {
            return '';
        }

        Comfino\ErrorLogger::init($this);

        if (in_array($params['order']->getCurrentState(), [
            (int) Configuration::get('COMFINO_CREATED'),
            (int) Configuration::get('PS_OS_OUTOFSTOCK'),
            (int) Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'),
        ], true)) {
            $tpl_variables = [
                'shop_name' => $this->context->shop->name,
                'status' => 'ok',
                'id_order' => $params['order']->id,
            ];

            if (isset($params['order']->reference) && !empty($params['order']->reference)) {
                $tpl_variables['reference'] = $params['order']->reference;
            }
        } else {
            $tpl_variables['status'] = 'failed';
        }

        return Comfino\TemplateManager::renderModuleView($this, 'payment_return', 'front', $tpl_variables);
    }

    /**
     * Renders settings form in administration panel.
     */
    public function hookDisplayBackofficeComfinoForm(array $params): string
    {
        return Comfino\FormManager::getSettingsForm($this, $params);
    }

    /**
     * Order status update event handler.
     */
    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $order = new Order($params['id_order']);

        if (stripos($order->payment, 'comfino') !== false) {
            // Process orders paid by Comfino only.

            /** @var OrderState $new_order_state */
            $new_order_state = $params['newOrderStatus'];

            $new_order_state_id = (int) $new_order_state->id;
            $canceled_order_state_id = (int) Configuration::get('PS_OS_CANCELED');

            if ($new_order_state_id === $canceled_order_state_id) {
                // Send notification about cancelled order paid by Comfino.
                Comfino\ErrorLogger::init($this);

                try {
                    Comfino\ApiClient::getInstance()->cancelOrder($params['id_order']);
                } catch (Throwable $e) {
                    Comfino\ApiClient::processApiError(
                        'Order cancellation error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e
                    );
                }
            }
        }
    }

    /**
     * Customer address validation event handler for order placement process.
     */
    public function hookActionValidateCustomerAddressForm(array $params): string
    {
        $vat_number = $params['form']->getField('vat_number');
        $tools = new Comfino\Tools($this->context);

        if (!empty($vat_number->getValue()) && !$tools->isValidTaxId($vat_number->getValue())) {
            $vat_number->addError($this->l('Invalid VAT number.'));

            return '0';
        }

        return '1';
    }

    /**
     * Page header section renderer for cart and product pages.
     */
    public function hookHeader(): void
    {
        if (stripos(get_class($this->context->controller), 'cart') !== false) {
            $controller = 'cart';
        } elseif (empty($controller = $this->context->controller->php_self)) {
            $controller = $this->context->controller->name ?? '';
        }

        if (empty($controller)) {
            return;
        }

        if (($controller === 'product') && Comfino\ConfigManager::isWidgetEnabled()) {
            // Widget initialization script
            $product = $this->context->controller->getProduct();
            $allowed_product_types = Comfino\SettingsManager::getAllowedProductTypes(
                Comfino\FinancialProduct\ProductTypesListTypeEnum::LIST_TYPE_WIDGET,
                Comfino\OrderManager::getShopCartFromProduct($product)
            );

            if ($allowed_product_types === []) {
                // Filters active - all product types disabled.
                return;
            }

            $this->addScriptLink(
                'comfino-widget',
                Comfino\ApiService::getControllerUrl(
                    $this,
                    'script',
                    ['product_id' => $product->id]
                ),
                'bottom',
                'defer'
            );
        }
    }

    /**
     * Page header script/style section renderer for backoffice admin pages.
     */
    public function hookActionAdminControllerSetMedia(array $params): void
    {
        $this->context->controller->addJS(_MODULE_DIR_ . $this->name . '/views/js/tree.min.js');
    }

    private function addScriptLink(
        string $id,
        string $script_url,
        string $position = 'bottom',
        $load_strategy = null
    ): void {
        if (COMFINO_PS_17) {
            $this->context->controller->registerJavascript(
                $id,
                $script_url,
                array_merge(
                    ['server' => 'remote', 'position' => $position],
                    $load_strategy !== null ? ['attributes' => $load_strategy] : []
                )
            );
        } else {
            $this->context->controller->addJS($script_url, false);
        }
    }
}
