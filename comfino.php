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

        if (is_readable(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }

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
        return Comfino\Main::processFinishedPaymentTransaction($this, $params);
    }

    /**
     * Renders settings form in administration panel.
     */
    public function hookDisplayBackofficeComfinoForm(array $params): string
    {
        return Comfino\View\FormManager::getSettingsForm($this, $params);
    }

    /**
     * Order status update event handler.
     */
    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        Comfino\Order\ShopStatusManager::orderStatusUpdateEventHandler($this, $params);
    }

    /**
     * Customer address validation event handler for order placement process.
     */
    public function hookActionValidateCustomerAddressForm(array $params): string
    {
        return Comfino\Order\OrderManager::validateCustomerData($this, $params);
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

        if (($controller === 'product') && Comfino\Configuration\ConfigManager::isWidgetEnabled()) {
            // Widget initialization script
            $product = $this->context->controller->getProduct();
            $allowedProductTypes = Comfino\Configuration\SettingsManager::getAllowedProductTypes(
                Comfino\FinancialProduct\ProductTypesListTypeEnum::LIST_TYPE_WIDGET,
                Comfino\Order\OrderManager::getShopCartFromProduct($product)
            );

            if ($allowedProductTypes === []) {
                // Filters active - all product types disabled.
                Comfino\Main::debugLog('[WIDGET]', 'Filters active - all product types disabled.');

                return;
            }

            $this->addScriptLink(
                'comfino-widget',
                Comfino\Api\ApiService::getControllerUrl($this, 'script', ['product_id' => $product->id]),
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
        string $scriptUrl,
        string $position = 'bottom',
        $loadStrategy = null
    ): void {
        if (COMFINO_PS_17) {
            $this->context->controller->registerJavascript(
                $id,
                $scriptUrl,
                array_merge(
                    ['server' => 'remote', 'position' => $position],
                    $loadStrategy !== null ? ['attributes' => $loadStrategy] : []
                )
            );
        } else {
            $this->context->controller->addJS($scriptUrl, false);
        }
    }
}
