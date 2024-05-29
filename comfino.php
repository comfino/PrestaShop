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

use Comfino\ApiClient;
use Comfino\ApiService;
use Comfino\Common\Frontend\IframeRenderer;
use Comfino\ConfigManager;
use Comfino\ErrorLogger;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\FormManager;
use Comfino\OrderManager;
use Comfino\SettingsForm;
use Comfino\SettingsManager;
use Comfino\ShopStatusManager;
use Comfino\TemplateManager;

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

        // Register module API endpoints.
        ApiService::init($this, $this->context);
    }

    /**
     * @return bool
     */
    public function install(): bool
    {
        ErrorLogger::init($this);

        if (!parent::install()) {
            return false;
        }

        $config_manager = new ConfigManager();
        $config_manager->initConfigurationValues();
        ShopStatusManager::addCustomOrderStatuses();

        if (!COMFINO_PS_17) {
            $this->registerHook('payment');
            $this->registerHook('displayPaymentEU');
        }

        $this->registerHook('paymentOptions');
        $this->registerHook('paymentReturn');
        $this->registerHook('displayBackofficeComfinoForm');
        $this->registerHook('actionOrderStatusPostUpdate');
        $this->registerHook('actionValidateCustomerAddressForm');
        $this->registerHook('header');
        $this->registerHook('actionAdminControllerSetMedia');

        return true;
    }

    /**
     * @return bool
     */
    public function uninstall(): bool
    {
        if (parent::uninstall()) {
            ConfigManager::deleteConfigurationValues();

            if (!COMFINO_PS_17) {
                $this->unregisterHook('payment');
                $this->unregisterHook('displayPaymentEU');
            }

            $this->unregisterHook('paymentOptions');
            $this->unregisterHook('paymentReturn');
            $this->unregisterHook('displayBackofficeComfinoForm');
            $this->unregisterHook('actionOrderStatusPostUpdate');
            $this->unregisterHook('actionValidateCustomerAddressForm');
            $this->unregisterHook('header');
            $this->unregisterHook('actionAdminControllerSetMedia');

            ErrorLogger::init($this);
            ApiClient::getInstance()->notifyPluginRemoval();

            return true;
        }

        return false;
    }

    /**
     * Renders configuration form.
     */
    public function getContent(): string
    {
        return TemplateManager::renderModuleView($this, $this->smarty, 'configuration', 'admin', SettingsForm::processForm($this));
    }

    /**
     * Renders Comfino paywall iframe at payment methods list compatible with PrestaShop 1.6.*.
     *
     * @return string|void
     */
    public function hookPayment(array $params)
    {
        if (!$this->paymentIsAvailable($params)) {
            return;
        }

        return $this->preparePaywallIframe();
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
        if (!$this->paymentIsAvailable($params)) {
            return;
        }

        $comfino_payment_option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $comfino_payment_option->setModuleName($this->name)
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true))
            ->setCallToActionText(ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'))
            ->setLogo(ApiClient::getPaywallLogoUrl())
            ->setAdditionalInformation($this->preparePaywallIframe());

        return [$comfino_payment_option];
    }

    /**
     * @throws PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function hookPaymentReturn(array $params): string
    {
        if (!$this->active) {
            return '';
        }

        ErrorLogger::init($this);

        $config_manager = new ConfigManager();

        if (COMFINO_PS_17) {
            $state = $params['order']->getCurrentState();
            $rest_to_paid = $params['order']->getOrdersTotalPaid() - $params['order']->getTotalPaid();

            if (in_array($state, [
                $config_manager->getConfigurationValue('COMFINO_CREATED'),
                $config_manager->getConfigurationValue('PS_OS_OUTOFSTOCK'),
                $config_manager->getConfigurationValue('PS_OS_OUTOFSTOCK_UNPAID'),
            ], true)) {
                $this->smarty->assign(
                    [
                        'total_to_pay' => (new Comfino\Tools($this->context))->formatPrice(
                            $rest_to_paid,
                            $params['order']->id_currency
                        ),
                        'shop_name' => $this->context->shop->name,
                        'status' => 'ok',
                        'id_order' => $params['order']->id,
                    ]
                );

                if (isset($params['order']->reference) && !empty($params['order']->reference)) {
                    $this->smarty->assign('reference', $params['order']->reference);
                }
            } else {
                $this->smarty->assign('status', 'failed');
            }

            return $this->fetch('module:comfino/views/templates/hook/payment_return.tpl');
        }

        return '';
    }

    /**
     * Renders settings form in administration panel.
     */
    public function hookDisplayBackofficeComfinoForm(array $params): string
    {
        return FormManager::getSettingsForm($this, $this->smarty, $params);
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
            $current_order_state_id = (int) $order->getCurrentState();
            $canceled_order_state_id = (int) ConfigManager::getConfigurationValue('PS_OS_CANCELED');

            if ($new_order_state_id !== $current_order_state_id && $new_order_state_id === $canceled_order_state_id) {
                ErrorLogger::init($this);

                try {
                    ApiClient::getInstance()->cancelOrder($params['id_order']);
                } catch (Throwable $e) {
                    ApiClient::processApiError(
                        'Order cancellation error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                        $e
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

        if ($controller === 'product') {
            if (ConfigManager::isWidgetEnabled()) {
                // Widget initialization script
                $product = $this->context->controller->getProduct();
                $allowed_product_types = SettingsManager::getAllowedProductTypes(
                    ProductTypesListTypeEnum::LIST_TYPE_WIDGET,
                    OrderManager::getShopCartFromProduct($product)
                );

                if ($allowed_product_types === []) {
                    // Filters active - all product types disabled.
                    return;
                }

                $config_crc = ''; // crc32(implode($widget_settings));
                $this->addScriptLink(
                    'comfino-widget',
                    $this->context->link->getModuleLink(
                        $this->name, 'script', ['product_id' => $product->id, 'crc' => $config_crc], true
                    ),
                    'bottom',
                    'defer'
                );
            }
        } elseif (preg_match('/order|cart|checkout/', $controller)) {
            $this->addStyleLink('comfino-paywall-frontend-style', ApiClient::getPaywallFrontendStyleUrl());
        }
    }

    /**
     * Page header script/style section renderer for backoffice admin pages.
     */
    public function hookActionAdminControllerSetMedia(array $params): void
    {
        $this->context->controller->addJS(_MODULE_DIR_ . $this->name . '/views/js/tree.min.js');
    }

    /**
     * @param string $id
     * @param string $script_url
     * @param string $position
     * @return void
     */
    private function addScriptLink($id, $script_url, $position = 'bottom', $load_strategy = null)
    {
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

    /**
     * @param string $id
     * @param string $style_url
     * @return void
     */
    private function addStyleLink($id, $style_url)
    {
        if (COMFINO_PS_17) {
            $this->context->controller->registerStylesheet($id, $style_url, ['server' => 'remote']);
        } else {
            $this->context->controller->addCSS($style_url);
        }
    }

    private function paymentIsAvailable(array $params): bool
    {
        /** @var Cart $cart */
        $cart = $params['cart'];

        if (!$this->active || !OrderManager::checkCartCurrency($this, $cart) || empty(ConfigManager::getApiKey())) {
            return false;
        }

        ErrorLogger::init($this);

        return SettingsManager::getAllowedProductTypes(
            ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
            OrderManager::getShopCart($cart, (int) $this->context->cookie->loan_amount)
        ) !== [];
    }

    private function preparePaywallIframe(): string
    {
        return (new IframeRenderer('PrestaShop', _PS_VERSION_))->renderPaywallIframe(
            $this->context->link->getModuleLink($this->name, 'availableoffertypes', [], true)
        );
    }
}
