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
use Comfino\Common\Frontend\IframeRenderer;
use Comfino\ConfigManager;
use Comfino\ErrorLogger;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\FormManager;
use Comfino\OrderManager;
use Comfino\SettingsForm;
use Comfino\SettingsManager;
use Comfino\ShopStatusManager;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

if (!defined('COMFINO_PS_17')) {
    define('COMFINO_PS_17', version_compare(_PS_VERSION_, '1.7', '>='), false);
}

if (!defined('COMFINO_VERSION')) {
    define('COMFINO_VERSION', '4.0.0', false);
}

class Comfino extends PaymentModule
{
    const ERROR_LOG_NUM_LINES = 100;
    const COMFINO_SUPPORT_EMAIL = 'pomoc@comfino.pl';
    const COMFINO_SUPPORT_PHONE = '887-106-027';

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
            'payment', 'offer', 'paywall', 'notify', 'error', 'script', 'configuration', 'availableoffertypes'
        ];

        parent::__construct();

        $this->displayName = $this->l('Comfino payments');
        $this->confirmUninstall = $this->l('Are you sure to uninstall Comfino payments?');

        $this->description = $this->l(
            'Comfino is an innovative payment method for customers of e-commerce stores! ' .
            'These are installment payments, deferred (buy now, pay later) and corporate ' .
            'payments available on one platform with the help of quick integration. Grow your business with Comfino!'
        );
    }

    /**
     * @return bool
     */
    public function install()
    {
        ErrorLogger::init();

        if (!parent::install()) {
            return false;
        }

        $config_manager = new ConfigManager($this);
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
    public function uninstall()
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

    private function checkCurrency(Cart $cart): bool
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id === $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getContent(): string
    {
        ApiClient::init();
        ErrorLogger::init($this);

        $config_manager = new ConfigManager();

        $active_tab = 'payment_settings';
        $output = [];
        $output_type = 'success';

        if (Tools::isSubmit('submit_configuration')) {
            $active_tab = Tools::getValue('active_tab');
            $widget_key_error = false;
            $widget_key = '';

            $error_empty_msg = $this->l("Field '%s' can not be empty.");
            $error_numeric_format_msg = $this->l("Field '%s' has wrong numeric format.");

            $configuration_options = [];

            foreach (ConfigManager::CONFIG_OPTIONS[$active_tab] as $option_name) {
                if ($option_name !== 'COMFINO_WIDGET_KEY') {
                    $configuration_options[$option_name] = Tools::getValue($option_name);
                }
            }

            switch ($active_tab) {
                case 'payment_settings':
                case 'developer_settings':
                    if ($active_tab === 'payment_settings') {
                        $is_sandbox_mode = ConfigManager::isSandboxMode();

                        $api_host = $is_sandbox_mode
                            ? ApiClient::getApiHost(false, ApiClient::COMFINO_SANDBOX_HOST)
                            : ApiClient::COMFINO_PRODUCTION_HOST;

                        $api_key = $is_sandbox_mode
                            ? ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY')
                            : Tools::getValue('COMFINO_API_KEY');

                        if (Tools::isEmpty(Tools::getValue('COMFINO_API_KEY'))) {
                            $output[] = sprintf($error_empty_msg, $this->l('Production environment API key'));
                        }
                        if (Tools::isEmpty(Tools::getValue('COMFINO_PAYMENT_TEXT'))) {
                            $output[] = sprintf($error_empty_msg, $this->l('Payment text'));
                        }
                        if (Tools::isEmpty(Tools::getValue('COMFINO_MINIMAL_CART_AMOUNT'))) {
                            $output[] = sprintf($error_empty_msg, $this->l('Minimal amount in cart'));
                        } elseif (!is_numeric(Tools::getValue('COMFINO_MINIMAL_CART_AMOUNT'))) {
                            $output[] = sprintf($error_numeric_format_msg, $this->l('Minimal amount in cart'));
                        }
                    } else {
                        $is_sandbox_mode = (bool) Tools::getValue('COMFINO_IS_SANDBOX');
                        $api_key = $is_sandbox_mode
                            ? Tools::getValue('COMFINO_SANDBOX_API_KEY')
                            : ConfigManager::getConfigurationValue('COMFINO_API_KEY');
                    }

                    if (!empty($api_key) && !count($output)) {
                        // Update widget key.
/*                        ApiClient::setSandboxMode($is_sandbox_mode);
                        ApiClient::setApiHost($api_host);
                        ApiClient::setApiKey($api_key);*/

                        if (!ApiClient::isApiKeyValid()) {
                            $output[] = sprintf($this->l('API key %s is not valid.'), $api_key);
                        } else {
                            try {
                                $widget_key = ApiClient::getInstance($is_sandbox_mode, $api_key)->getWidgetKey();
                            } catch (\Throwable $e) {
                                ApiClient::processApiError(
                                    ($active_tab === 'payment_settings' ? 'Payment' : 'Developer') .
                                    ' settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                                    $e
                                );

                                $output[] = $e->getMessage();
                                $output_type = 'warning';
                                $widget_key_error = true;
                            }
                        }
                    }

                    $configuration_options['COMFINO_WIDGET_KEY'] = $widget_key;
                    break;

                case 'sale_settings':
                    $product_categories = array_keys(ConfigManager::getAllProductCategories());
                    $product_category_filters = [];

                    foreach (Tools::getValue('product_categories') as $product_type => $category_ids) {
                        $product_category_filters[$product_type] = array_values(array_diff(
                            $product_categories,
                            explode(',', $category_ids)
                        ));
                    }

                    $configuration_options['COMFINO_PRODUCT_CATEGORY_FILTERS'] = json_encode($product_category_filters);
                    break;

                case 'widget_settings':
                    if (!is_numeric(Tools::getValue('COMFINO_WIDGET_PRICE_OBSERVER_LEVEL'))) {
                        $output[] = sprintf(
                            $error_numeric_format_msg,
                            $this->l('Price change detection - container hierarchy level')
                        );
                    }

                    if (!count($output)) {
                        $is_sandbox_mode = (bool) $config_manager->getConfigurationValue('COMFINO_IS_SANDBOX');

                        $api_host = $is_sandbox_mode
                            ? ApiClient::getApiHost(false, ApiClient::COMFINO_SANDBOX_HOST)
                            : ApiClient::COMFINO_PRODUCTION_HOST;

                        $api_key = $is_sandbox_mode
                            ? $config_manager->getConfigurationValue('COMFINO_SANDBOX_API_KEY')
                            : $config_manager->getConfigurationValue('COMFINO_API_KEY');

                        if (!empty($api_key)) {
                            // Update widget key.
                            ApiClient::setSandboxMode($is_sandbox_mode);
                            ApiClient::setApiHost($api_host);
                            ApiClient::setApiKey($api_key);

                            if (!ApiClient::isApiKeyValid()) {
                                $output[] = sprintf($this->l('API key %s is not valid.'), $api_key);
                            } else {
                                try {
                                    $widget_key = ApiClient::getInstance($this)->getWidgetKey();
                                } catch (\Throwable $e) {
                                    ApiClient::processApiError(
                                        'Widget settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                                        $e
                                    );

                                    $output[] = $e->getMessage();
                                    $output_type = 'warning';
                                    $widget_key_error = true;
                                }
                            }
                        }
                    }

                    $configuration_options['COMFINO_WIDGET_KEY'] = $widget_key;
                    break;
            }

            if (!$widget_key_error && count($output)) {
                $output_type = 'warning';
                $output[] = $this->l('Settings not updated.');
            } else {
                // Update plugin configuration.
                $config_manager->updateConfiguration($configuration_options, false);

                $output[] = $this->l('Settings updated.');
            }
        }

        $this->context->smarty->assign([
            'active_tab' => $active_tab,
            'output' => $output,
            'output_type' => $output_type,
            'logo_url' => ApiClient::getLogoUrl(),
            'support_email_address' => self::COMFINO_SUPPORT_EMAIL,
            'support_email_subject' => sprintf(
                $this->l('PrestaShop %s Comfino %s - question'),
                _PS_VERSION_, COMFINO_VERSION
            ),
            'support_email_body' => sprintf(
                'PrestaShop %s Comfino %s, PHP %s',
                _PS_VERSION_, COMFINO_VERSION, PHP_VERSION
            ),
            'contact_msg1' => $this->l('Do you want to ask about something? Write to us at'),
            'contact_msg2' => sprintf(
                $this->l(
                    'or contact us by phone. We are waiting on the number: %s. We will answer all your questions!'
                ),
                self::COMFINO_SUPPORT_PHONE
            ),
            'plugin_version' => $this->version,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }

    /**
     * PrestaShop 1.6.* compatibility.
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
     * PrestaShop 1.7.* amd 8.* compatibility.
     *
     * @return PaymentOption[]|void
     * @throws LocalizationException
     */
    public function hookPaymentOptions(array $params)
    {
        if (!$this->paymentIsAvailable($params)) {
            return;
        }

        $comfino_payment_option = new PaymentOption();
        $comfino_payment_option->setModuleName($this->name)
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true))
            ->setCallToActionText(ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'))
            ->setLogo(ApiClient::getPaywallLogoUrl())
            ->setAdditionalInformation($this->preparePaywallIframe());

        return [$comfino_payment_option];
    }

    /**
     * @throws LocalizationException
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
                        'total_to_pay' => (new \Comfino\Tools($this->context))->formatPrice(
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

    public function hookDisplayBackofficeComfinoForm(array $params): string
    {
        $config_tab = $params['config_tab'] ?? '';
        $form_name = $params['form_name'] ?? 'submit_configuration';

        $helper = FormManager::getHelperForm($this, $form_name);
        $helper->fields_value['active_tab'] = $config_tab;

        $config_manager = new ConfigManager($this);

        foreach (ConfigManager::CONFIG_OPTIONS as $options) {
            foreach ($options as $option_name) {
                $helper->fields_value[$option_name] = $config_manager->getConfigurationValue($option_name);
            }
        }

        $helper->fields_value['COMFINO_WIDGET_ERRORS_LOG'] = ErrorLogger::getErrorLog(self::ERROR_LOG_NUM_LINES);

        $messages = [];

        switch ($config_tab) {
            case 'payment_settings':
                if (ConfigManager::isSandboxMode()) {
                    $messages['warning'] = $this->l('Developer mode is active. You are using test environment.');
                }

                break;

            case 'sale_settings':
            case 'widget_settings':
                if (!isset($params['offer_types'][$config_tab])) {
                    $params['offer_types'][$config_tab] = SettingsManager::getProductTypesSelectList(
                        $config_tab === 'sale_settings'
                            ? ProductTypesListTypeEnum::LIST_TYPE_PAYWALL
                            : ProductTypesListTypeEnum::LIST_TYPE_WIDGET
                    );
                }
                if (!isset($params['widget_types'])) {
                    $params['widget_types'] = SettingsManager::getWidgetTypesSelectList();
                }

                break;

            case 'plugin_diagnostics':
                $info_messages = [];
                $success_messages = [];
                $warning_messages = [];
                $error_messages = [];

                $info_messages[] = sprintf(
                    'PrestaShop Comfino %s, PrestaShop %s, Symfony %s, PHP %s, web server %s, database %s',
                    ...ConfigManager::getEnvironmentInfo([
                        'plugin_version',
                        'shop_version',
                        'symfony_version',
                        'php_version',
                        'server_software',
                        'database_version'
                    ])
                );

                if (ConfigManager::isSandboxMode()) {
                    $warning_messages[] = $this->l('Developer mode is active. You are using test environment.');

                    if (!empty(ApiClient::getApiKey())) {
                        if (ApiClient::isShopAccountActive()) {
                            $success_messages[] = $this->l('Test account is active.');
                        } else {
                            if (count(ApiClient::getLastErrors())) {
                                $error_messages = array_merge($error_messages, ApiClient::getLastErrors());

                                if (ApiClient::getLastResponseCode() === 401) {
                                    $error_messages[] = $this->l('Invalid test API key.');
                                }
                            } else {
                                $warning_messages[] = $this->l('Test account is not active.');
                            }
                        }
                    } else {
                        $error_messages[] = $this->l('Test API key not present.');
                    }
                } elseif (!empty(ApiClient::getApiKey())) {
                    $success_messages[] = $this->l('Production mode is active.');

                    if (ApiClient::isShopAccountActive()) {
                        $success_messages[] = $this->l('Production account is active.');
                    } else {
                        if (count(ApiClient::getLastErrors())) {
                            $error_messages = array_merge($error_messages, ApiClient::getLastErrors());

                            if (ApiClient::getLastResponseCode() === 401) {
                                $error_messages[] = $this->l('Invalid production API key.');
                            }
                        } else {
                            $warning_messages[] = $this->l('Production account is not active.');
                        }
                    }
                } else {
                    $error_messages[] = $this->l('Production API key not present.');
                }

                if (count($info_messages)) {
                    $messages['description'] = implode('<br />', $info_messages);
                }
                if (count($success_messages)) {
                    $messages['success'] = implode('<br />', $success_messages);
                }
                if (count($warning_messages)) {
                    $messages['warning'] = implode('<br />', $warning_messages);
                }
                if (count($error_messages)) {
                    $messages['error'] = implode('<br />', $error_messages);
                }

                break;
        }

        if (count($messages)) {
            $params['messages'] = $messages;
        }

        return $helper->generateForm(SettingsForm::getFormFields($this, $params));
    }

    /**
     * @param array $params
     * @return void
     */
    public function hookActionOrderStatusPostUpdate($params)
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
                } catch (\Throwable $e) {
                    ApiClient::processApiError(
                        'Order cancellation error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                        $e
                    );
                }
            }
        }
    }

    public function hookActionValidateCustomerAddressForm(array $params): string
    {
        $vat_number = $params['form']->getField('vat_number');

        if (!empty($vat_number->getValue()) && !$this->isValidTaxId($vat_number->getValue())) {
            $vat_number->addError($this->l('Invalid VAT number.'));

            return '0';
        }

        return '1';
    }

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

                $config_crc = '';//crc32(implode($widget_settings));
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
            ApiClient::init();
            $this->addStyleLink('comfino-paywall-frontend-style', ApiClient::getPaywallFrontendStyleUrl());
        }
    }

    public function hookActionAdminControllerSetMedia(array $params): void
    {
        $this->context->controller->addJS(_MODULE_DIR_ . $this->name . '/views/js/tree.min.js');
    }

    private function checkConfiguration(): bool
    {
        return !empty(ConfigManager::getConfigurationValue('COMFINO_API_KEY'));
    }

    /**
     * @return array
     * @throws LocalizationException
     */
    private function getTemplateVars()
    {
        $config_manager = new ConfigManager($this);

        return [
            'pay_with_comfino_text' => $config_manager->getConfigurationValue('COMFINO_PAYMENT_TEXT'),
            'logo_url' => ApiClient::getPaywallLogoUrl(),
            'go_to_payment_url' => $this->context->link->getModuleLink($this->name, 'payment', [], true),
            'paywall_options' => $this->getPaywallOptions(),
            'paywall_script_url' => ApiClient::getPaywallFrontendScriptUrl(),
            'offers_url' => $this->context->link->getModuleLink($this->name, 'offer', [], true),
        ];
    }

    /**
     * @param string $tax_id
     * @return bool
     */
    private function isValidTaxId($tax_id)
    {
        if (empty($tax_id) || strlen($tax_id) !== 10 || !preg_match('/^\d+$/', $tax_id)) {
            return false;
        }

        $arr_steps = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $int_sum = 0;

        for ($i = 0; $i < 9; ++$i) {
            $int_sum += $arr_steps[$i] * $tax_id[$i];
        }

        $int = $int_sum % 11;

        return ($int === 10 ? 0 : $int) === (int) $tax_id[9];
    }

    /**
     * @return array
     * @throws LocalizationException
     */
    private function getPaywallOptions()
    {
        $cart = $this->context->cart;
        $total = $cart->getOrderTotal();

        $tools = new \Comfino\Tools($this->context);

        return [
            'platform' => 'prestashop',
            'platformName' => 'PrestaShop',
            'platformVersion' => _PS_VERSION_,
            'platformDomain' => \Tools::getShopDomain(),
            'pluginVersion' => COMFINO_VERSION,
            'language' => $tools->getLanguageIsoCode($cart->id_lang),
            'currency' => $tools->getCurrencyIsoCode($cart->id_currency),
            'cartTotal' => (float) $total,
            'cartTotalFormatted' => $tools->formatPrice($total, $cart->id_currency),
        ];
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

        if (!$this->active || !$this->checkCurrency($cart) || !$this->checkConfiguration()) {
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
            $this->context->link->getModuleLink($this->module->name, 'availableoffertypes', [], true)
        );
    }
}
