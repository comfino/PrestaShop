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

require_once _PS_MODULE_DIR_ . 'comfino/src/Api.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ErrorLogger.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ConfigManager.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Tools.php';
require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';

if (!defined('COMFINO_PS_17')) {
    define('COMFINO_PS_17', version_compare(_PS_VERSION_, '1.7', '>='), false);
}

if (!defined('COMFINO_VERSION')) {
    define('COMFINO_VERSION', '3.5.4', false);
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
        $this->version = '3.5.4';
        $this->author = 'Comfino';
        $this->module_key = '3d3e14c65281e816da083e34491d5a7f';

        $this->bootstrap = true;
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->controllers = [
            'payment', 'offer', 'notify', 'error', 'script', 'configuration', 'availableoffertypes'
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
        \Comfino\ErrorLogger::init();

        if (!parent::install()) {
            return false;
        }

        include 'sql/install.php';

        $config_manager = new \Comfino\ConfigManager($this);
        $config_manager->initConfigurationValues();
        $config_manager->addCustomOrderStatuses();

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
        include 'sql/uninstall.php';

        if (parent::uninstall()) {
            \Comfino\Api::init($this);

            $this->deleteConfigurationValues();

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

            \Comfino\Api::notifyPluginRemoval();

            return true;
        }

        return false;
    }

    /**
     * @param Cart $cart
     * @return bool
     */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        \Comfino\Api::init($this);
        \Comfino\ErrorLogger::init();

        $config_manager = new \Comfino\ConfigManager($this);

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

            foreach (\Comfino\ConfigManager::COMFINO_SETTINGS_OPTIONS[$active_tab] as $option_name) {
                if ($option_name !== 'COMFINO_WIDGET_KEY') {
                    $configuration_options[$option_name] = Tools::getValue($option_name);
                }
            }

            switch ($active_tab) {
                case 'payment_settings':
                case 'developer_settings':
                    if ($active_tab === 'payment_settings') {
                        $is_sandbox_mode = (bool) $config_manager->getConfigurationValue('COMFINO_IS_SANDBOX');

                        $api_host = $is_sandbox_mode
                            ? \Comfino\Api::getApiHost(false, \Comfino\Api::COMFINO_SANDBOX_HOST)
                            : \Comfino\Api::COMFINO_PRODUCTION_HOST;

                        $api_key = $is_sandbox_mode
                            ? $config_manager->getConfigurationValue('COMFINO_SANDBOX_API_KEY')
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

                        $api_host = $is_sandbox_mode
                            ? \Comfino\Api::getApiHost(false, \Comfino\Api::COMFINO_SANDBOX_HOST)
                            : \Comfino\Api::COMFINO_PRODUCTION_HOST;

                        $api_key = $is_sandbox_mode
                            ? Tools::getValue('COMFINO_SANDBOX_API_KEY')
                            : $config_manager->getConfigurationValue('COMFINO_API_KEY');
                    }

                    if (!empty($api_key) && !count($output)) {
                        // Update widget key.
                        \Comfino\Api::setSandboxMode($is_sandbox_mode);
                        \Comfino\Api::setApiHost($api_host);
                        \Comfino\Api::setApiKey($api_key);

                        if (!\Comfino\Api::isApiKeyValid()) {
                            $output[] = sprintf($this->l('API key %s is not valid.'), $api_key);
                        } else {
                            $widget_key = \Comfino\Api::getWidgetKey();

                            if ($widget_key === false) {
                                $output = array_merge($output, \Comfino\Api::getLastErrors());
                                $output_type = 'warning';
                                $widget_key_error = true;
                                $widget_key = '';
                            }
                        }
                    }

                    break;

                case 'sale_settings':
                    $product_categories = array_keys($config_manager->getAllProductCategories());
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
                            ? \Comfino\Api::getApiHost(false, \Comfino\Api::COMFINO_SANDBOX_HOST)
                            : \Comfino\Api::COMFINO_PRODUCTION_HOST;

                        $api_key = $is_sandbox_mode
                            ? $config_manager->getConfigurationValue('COMFINO_SANDBOX_API_KEY')
                            : $config_manager->getConfigurationValue('COMFINO_API_KEY');

                        if (!empty($api_key)) {
                            // Update widget key.
                            \Comfino\Api::setSandboxMode($is_sandbox_mode);
                            \Comfino\Api::setApiHost($api_host);
                            \Comfino\Api::setApiKey($api_key);

                            if (!\Comfino\Api::isApiKeyValid()) {
                                $output[] = sprintf($this->l('API key %s is not valid.'), $api_key);
                            } else {
                                $widget_key = \Comfino\Api::getWidgetKey();

                                if ($widget_key === false) {
                                    $output = array_merge($output, \Comfino\Api::getLastErrors());
                                    $output_type = 'warning';
                                    $widget_key_error = true;
                                    $widget_key = '';
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
        } elseif (Tools::isSubmit('submit_registration')) {
            $shop_data = Tools::getValue('register');
            $error_empty_msg = $this->l("Field '%s' can not be empty.");

            if (Tools::isEmpty($shop_data['name'])) {
                $output[] = sprintf($error_empty_msg, $this->l('Name'));
            }
            if (Tools::isEmpty($shop_data['surname'])) {
                $output[] = sprintf($error_empty_msg, $this->l('Surname'));
            }
            if (Tools::isEmpty($shop_data['email'])) {
                $output[] = sprintf($error_empty_msg, $this->l('E-mail address'));
            }
            if (Tools::isEmpty($shop_data['phone'])) {
                $output[] = sprintf($error_empty_msg, $this->l('Phone number'));
            }
            if (Tools::isEmpty($shop_data['url'])) {
                $output[] = sprintf(
                    $error_empty_msg,
                    $this->l('Website address where the Comfino payment will be installed')
                );
            }

            $selected_agreements = [];
            $agreements = \Comfino\Api::getShopAccountAgreements();

            if ($agreements !== false && count($agreements)) {
                if (Tools::isEmpty($shop_data['agreements'])) {
                    $output[] = $this->l('No required consents.');
                } else {
                    foreach ($agreements as &$agreement) {
                        if (isset($shop_data['agreements'][$agreement['id']])) {
                            $selected_agreements[] = $agreement['id'];
                            $agreement['checked'] = true;
                        } elseif ($agreement['required']) {
                            $output[] = sprintf(
                                $this->l("'%s' consent is required."),
                                preg_replace(
                                    '/<a\s+href="[^"]*"[^>]*>([^<\/]+)<\/a>/mU',
                                    '$1',
                                    strip_tags($agreement['content'], '<a>')
                                )
                            );
                        }
                    }

                    unset($agreement);
                }
            }

            // Update form fields with submitted values.
            $this->context->smarty->assign([
                'register_form' => [
                    'name' => $shop_data['name'],
                    'surname' => $shop_data['surname'],
                    'email' => $shop_data['email'],
                    'phone' => $shop_data['phone'],
                    'url' => $shop_data['url'],
                ],
                'agreements' => $agreements !== false ? $agreements : [],
            ]);

            if (count($output)) {
                $output_type = 'warning';
            } else {
                // Send request to the registration API endpoint.
                $result = \Comfino\Api::registerShopAccount(
                    $config_manager->getConfigurationValue('PS_SHOP_NAME'),
                    $shop_data['url'],
                    $shop_data['name'] . ' ' . $shop_data['surname'],
                    $shop_data['email'],
                    $shop_data['phone'],
                    $selected_agreements
                );

                if ($result === false || isset($result['errors'])) {
                    if (isset($result['errors'])) {
                        if (count($result['errors'])) {
                            $output = array_merge($output, $result['errors']);
                        } else {
                            $output[] = $this->l('Comfino registration error.');
                        }
                    } else {
                        $output = array_merge($output, \Comfino\Api::getLastErrors());
                    }

                    $output_type = 'danger';
                } else {
                    if ($config_manager->getConfigurationValue('COMFINO_IS_SANDBOX')) {
                        $config_manager->setConfigurationValue('COMFINO_SANDBOX_API_KEY', $result['apiKey']);
                        $config_manager->setConfigurationValue('COMFINO_SANDBOX_REGISTERED_AT', date('Y-m-d H:i:s'));
                    } else {
                        $config_manager->setConfigurationValue('COMFINO_API_KEY', $result['apiKey']);
                        $config_manager->setConfigurationValue('COMFINO_REGISTERED_AT', date('Y-m-d H:i:s'));
                    }

                    $config_manager->setConfigurationValue('COMFINO_WIDGET_KEY', $result['widgetKey']);
                }
            }
        }

        if ($config_manager->getConfigurationValue('COMFINO_IS_SANDBOX')) {
            $registered_at = $config_manager->getConfigurationValue('COMFINO_SANDBOX_REGISTERED_AT');
            $api_key = $config_manager->getConfigurationValue('COMFINO_SANDBOX_API_KEY');
        } else {
            $registered_at = $config_manager->getConfigurationValue('COMFINO_REGISTERED_AT');
            $api_key = $config_manager->getConfigurationValue('COMFINO_API_KEY');
        }

        $this->context->smarty->assign([
            'active_tab' => $active_tab,
            'output' => $output,
            'output_type' => $output_type,
            'logo_url' => \Comfino\Api::getLogoUrl(),
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
            'registration_available' => empty($registered_at) && empty($api_key),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }

    /**
     * PrestaShop 1.6.* compatibility.
     *
     * @param $params
     * @return string|void
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function hookPayment($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart']) || !$this->checkConfiguration()) {
            return;
        }

        \Comfino\ErrorLogger::init();
        \Comfino\Api::init($this);

        $config_manager = new \Comfino\ConfigManager($this);
        $min_cart_value = (float) $config_manager->getConfigurationValue('COMFINO_MINIMAL_CART_AMOUNT');

        if ($this->context->cart->getOrderTotal() < $min_cart_value) {
            return;
        }

        $product_types_filter = $this->getProductTypesFilter($this->context->cart, $config_manager);

        if ($product_types_filter === []) {
            // Filters active - all product types disabled.
            return;
        }

        return $this->preparePaywallIframe(
            (int) ($this->context->cart->getOrderTotal() * 100),
            $product_types_filter,
            $config_manager->getConfigurationValue('COMFINO_WIDGET_KEY')
        );
    }

    /**
     * PrestaShop 1.7.* compatibility.
     *
     * @param array $params
     * @return \PrestaShop\PrestaShop\Core\Payment\PaymentOption[]|void
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart']) || !$this->checkConfiguration()) {
            return;
        }

        \Comfino\ErrorLogger::init();
        \Comfino\Api::init($this);

        $config_manager = new \Comfino\ConfigManager($this);
        $min_cart_value = (float) $config_manager->getConfigurationValue('COMFINO_MINIMAL_CART_AMOUNT');

        if ($this->context->cart->getOrderTotal() < $min_cart_value) {
            return;
        }

        $product_types_filter = $this->getProductTypesFilter($this->context->cart, $config_manager);

        if ($product_types_filter === []) {
            // Filters active - all product types disabled.
            return;
        }

        $comfino_payment_option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $comfino_payment_option->setModuleName($this->name)
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true))
            ->setCallToActionText($config_manager->getConfigurationValue('COMFINO_PAYMENT_TEXT'))
            ->setLogo(\Comfino\Api::getPaywallLogoUrl())
            ->setAdditionalInformation(
                $this->preparePaywallIframe(
                    (int) ($this->context->cart->getOrderTotal() * 100),
                    $product_types_filter,
                    $config_manager->getConfigurationValue('COMFINO_WIDGET_KEY')
                )
            );

        return [$comfino_payment_option];
    }

    /**
     * @param array $params
     * @return string
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return '';
        }

        \Comfino\ErrorLogger::init();

        $config_manager = new \Comfino\ConfigManager($this);

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

    /**
     * @param array $params
     * @return string
     */
    public function hookDisplayBackofficeComfinoForm($params)
    {
        return $this->displayForm($params);
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
            $canceled_order_state_id = (int) (new \Comfino\ConfigManager($this))->getConfigurationValue('PS_OS_CANCELED');

            if ($new_order_state_id !== $current_order_state_id && $new_order_state_id === $canceled_order_state_id) {
                \Comfino\Api::init($this);
                \Comfino\ErrorLogger::init();
                \Comfino\Api::cancelOrder($params['id_order']);
            }
        }
    }

    /**
     * @param array $params
     * @return string
     */
    public function hookActionValidateCustomerAddressForm($params)
    {
        $vat_number = $params['form']->getField('vat_number');

        if (!empty($vat_number->getValue()) && !$this->isValidTaxId($vat_number->getValue())) {
            $vat_number->addError($this->l('Invalid VAT number.'));

            return '0';
        }

        return '1';
    }

    /**
     * @return void
     */
    public function hookHeader()
    {
        if (stripos(get_class($this->context->controller), 'cart') !== false) {
            $controller = 'cart';
        } elseif (empty($controller = $this->context->controller->php_self)) {
            $controller = isset($this->context->controller->name) ? $this->context->controller->name : '';
        }

        if (empty($controller)) {
            return;
        }

        if ($controller === 'product') {
            $config_manager = new \Comfino\ConfigManager($this);

            // Widget initialization script
            if ($config_manager->getConfigurationValue('COMFINO_WIDGET_ENABLED')) {
                $widget_settings = $config_manager->getConfigurationValues('widget_settings');

                // Check product category filters.
                $product_type = $widget_settings['COMFINO_WIDGET_OFFER_TYPE'];
                $product = $this->context->controller->getProduct();
                $products = [$product->getFields()];

                if ($config_manager->isFinancialProductAvailable($product_type, $products)) {
                    $config_crc = crc32(implode($widget_settings));
                    $this->addScriptLink(
                        'comfino-widget',
                        $this->context->link->getModuleLink(
                            $this->name, 'script', ['product_id' => $product->id, 'crc' => $config_crc], true
                        ),
                        'bottom',
                        'defer'
                    );
                }
            }
        } elseif (preg_match('/order|cart|checkout/', $controller)) {
            \Comfino\Api::init($this);
            $this->addStyleLink('comfino-paywall-frontend-style', \Comfino\Api::getPaywallFrontendStyleUrl());
        }
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        $this->context->controller->addJS(_MODULE_DIR_ . $this->name . '/views/js/tree.min.js');
    }

    /**
     * @param array $params
     * @return string
     */
    private function displayForm($params)
    {
        $config_tab = isset($params['config_tab']) ? $params['config_tab'] : '';
        $form_name = isset($params['form_name']) ? $params['form_name'] : 'submit_configuration';

        if ($form_name === 'submit_registration') {
            $form_template_dir = '';
            $form_template = 'registration_form.tpl';
        } else {
            $form_template_dir = null;
            $form_template = null;
        }

        $helper = $this->getHelperForm($form_name, $form_template_dir, $form_template);
        $helper->fields_value['active_tab'] = $config_tab;

        $config_manager = new \Comfino\ConfigManager($this);

        foreach (\Comfino\ConfigManager::COMFINO_SETTINGS_OPTIONS as $options) {
            foreach ($options as $option_name) {
                $helper->fields_value[$option_name] = $config_manager->getConfigurationValue($option_name);
            }
        }

        $helper->fields_value['COMFINO_WIDGET_ERRORS_LOG'] = \Comfino\ErrorLogger::getErrorLog(self::ERROR_LOG_NUM_LINES);

        $messages = [];

        switch ($config_tab) {
            case 'registration':
                $registration_available = true;
                $user_active = false;
                $api_error = false;
                $agreements = [];

                if ($config_manager->getConfigurationValue('COMFINO_IS_SANDBOX')) {
                    $registered_at = $config_manager->getConfigurationValue('COMFINO_SANDBOX_REGISTERED_AT');
                    $api_key = $config_manager->getConfigurationValue('COMFINO_SANDBOX_API_KEY');
                } else {
                    $registered_at = $config_manager->getConfigurationValue('COMFINO_REGISTERED_AT');
                    $api_key = $config_manager->getConfigurationValue('COMFINO_API_KEY');
                }

                if (!empty($registered_at) || !empty($api_key)) {
                    $registration_available = false;
                }

                if (!empty($api_key)) {
                    $user_active = \Comfino\Api::isShopAccountActive();

                    if (!$user_active) {
                        if (count(\Comfino\Api::getLastErrors())) {
                            $api_error = true;
                            $messages['error'] = implode('<br />', \Comfino\Api::getLastErrors());
                        }
                    }
                }

                if ($registration_available) {
                    $agreements = \Comfino\Api::getShopAccountAgreements();

                    if ($agreements === false) {
                        $messages['error'] = implode('<br />', \Comfino\Api::getLastErrors());
                        $registration_available = false;
                    } else {
                        foreach ($agreements as &$agreement) {
                            $agreement['content'] = preg_replace_callback(
                                '/<a(\s+href="[^"]*"[^>]*)>[^<\/]+<\/a>/mU',
                                static function (array $matches) {
                                    return stripos($matches[1], 'target=') === false
                                        ? str_replace($matches[1], $matches[1] . ' target="_blank"', $matches[0])
                                        : $matches[0];
                                },
                                strip_tags($agreement['content'], '<a>')
                            );
                        }

                        unset($agreement);
                    }
                }

                $params['form_fields'] = [
                    'register_form' => [
                        'name' => $this->context->employee->firstname,
                        'surname' => $this->context->employee->lastname,
                        'email' => $this->context->employee->email,
                        'url' => _PS_BASE_URL_,
                    ],
                    'agreements' => $agreements !== false ? $agreements : [],
                    'registration_available' => $registration_available,
                    'user_active' => $user_active,
                    'api_error' => $api_error,
                ];
                break;

            case 'payment_settings':
                if ($config_manager->getConfigurationValue('COMFINO_IS_SANDBOX')) {
                    $messages['warning'] = $this->l('Developer mode is active. You are using test environment.');
                }

                break;

            case 'sale_settings':
            case 'widget_settings':
                if (!isset($params['offer_types'][$config_tab])) {
                    $params['offer_types'][$config_tab] = $config_manager->getOfferTypes($config_tab);
                }
                if (!isset($params['widget_types'])) {
                    $params['widget_types'] = $config_manager->getWidgetTypes();
                }

                break;

            case 'plugin_diagnostics':
                $info_messages = [];
                $success_messages = [];
                $warning_messages = [];
                $error_messages = [];

                if (COMFINO_PS_17 && class_exists('\Symfony\Component\HttpKernel\Kernel')) {
                    $info_messages[] = sprintf(
                        'PrestaShop Comfino %s, PrestaShop %s, Symfony %s, PHP %s, web server %s, database %s',
                        COMFINO_VERSION,
                        _PS_VERSION_,
                        \Symfony\Component\HttpKernel\Kernel::VERSION,
                        PHP_VERSION,
                        $_SERVER['SERVER_SOFTWARE'],
                        Db::getInstance()->getVersion()
                    );
                } else {
                    $info_messages[] = sprintf(
                        'PrestaShop Comfino %s, PrestaShop %s, PHP %s, web server %s, database %s',
                        COMFINO_VERSION,
                        _PS_VERSION_,
                        PHP_VERSION,
                        $_SERVER['SERVER_SOFTWARE'],
                        Db::getInstance()->getVersion()
                    );
                }

                if ($config_manager->getConfigurationValue('COMFINO_IS_SANDBOX')) {
                    $warning_messages[] = $this->l('Developer mode is active. You are using test environment.');

                    if (!empty(\Comfino\Api::getApiKey())) {
                        if (\Comfino\Api::isShopAccountActive()) {
                            $success_messages[] = $this->l('Test account is active.');
                        } else {
                            if (count(\Comfino\Api::getLastErrors())) {
                                $error_messages = array_merge($error_messages, \Comfino\Api::getLastErrors());

                                if (\Comfino\Api::getLastResponseCode() === 401) {
                                    $error_messages[] = $this->l('Invalid test API key.');
                                }
                            } else {
                                $warning_messages[] = $this->l('Test account is not active.');
                            }
                        }
                    } else {
                        $error_messages[] = $this->l('Test API key not present.');
                    }
                } elseif (!empty(\Comfino\Api::getApiKey())) {
                    $success_messages[] = $this->l('Production mode is active.');

                    if (\Comfino\Api::isShopAccountActive()) {
                        $success_messages[] = $this->l('Production account is active.');
                    } else {
                        if (count(\Comfino\Api::getLastErrors())) {
                            $error_messages = array_merge($error_messages, \Comfino\Api::getLastErrors());

                            if (\Comfino\Api::getLastResponseCode() === 401) {
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

        return $helper->generateForm($this->getFormFields($params));
    }

    /**
     * @param string $submit_action
     * @param string $form_template_dir
     * @param string $form_template
     * @return HelperForm
     */
    private function getHelperForm($submit_action, $form_template_dir = null, $form_template = null)
    {
        $helper = new HelperForm();
        $language = (int) (new \Comfino\ConfigManager($this))->getConfigurationValue('PS_LANG_DEFAULT');

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $language;
        $helper->allow_employee_form_lang = $language;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true; // false -> remove toolbar
        $helper->toolbar_scroll = true; // yes - > Toolbar is always visible at the top of the screen.
        $helper->submit_action = $submit_action;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                          '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list'),
            ],
        ];

        if ($form_template !== null && $form_template_dir !== null) {
            $helper->base_folder = $form_template_dir;
            $helper->base_tpl = $form_template;
        }

        return $helper;
    }

    /**
     * @param array $params
     * @return array
     */
    private function getFormFields($params)
    {
        $fields = [];
        $config_tab = isset($params['config_tab']) ? $params['config_tab'] : '';
        $form_name = isset($params['form_name']) ? $params['form_name'] : 'submit_configuration';
        $form_fields = isset($params['form_fields']) ? $params['form_fields'] : [];

        switch ($config_tab) {
            case 'registration':
                $fields['registration']['form'] = [];

                if (isset($params['messages'])) {
                    // Messages list in the form header (type => message): description, warning, success, error
                    $fields['registration']['form'] = array_merge(
                        $fields['registration']['form'],
                        $params['messages']
                    );
                }

                // Initialize form fields - default values for input elements
                foreach ($form_fields as $field_name => $field_value) {
                    if ($this->context->smarty->getTemplateVars($field_name) === null) {
                        $this->context->smarty->assign($field_name, $field_value);
                    }
                }

                break;

            case 'payment_settings':
                $fields['payment_settings']['form'] = [];

                if (isset($params['messages'])) {
                    // Messages list in the form header (type => message): description, warning, success, error
                    $fields['payment_settings']['form'] = array_merge(
                        $fields['payment_settings']['form'],
                        $params['messages']
                    );
                }

                $fields['payment_settings']['form'] = array_merge(
                    $fields['payment_settings']['form'],
                    [
                        'input' => [
                            [
                                'type' => 'hidden',
                                'name' => 'active_tab',
                                'required' => false,
                            ],
                            [
                                'type' => 'text',
                                'label' => $this->l('Production environment API key'),
                                'name' => 'COMFINO_API_KEY',
                                'required' => true,
                                'placeholder' => $this->l('Please enter the key provided during registration'),
                            ],
                            [
                                'type' => 'text',
                                'label' => $this->l('Payment text'),
                                'name' => 'COMFINO_PAYMENT_TEXT',
                                'required' => true,
                            ],
                            [
                                'type' => 'text',
                                'label' => $this->l('Minimal amount in cart'),
                                'name' => 'COMFINO_MINIMAL_CART_AMOUNT',
                                'required' => true,
                            ],
                        ],
                        'submit' => [
                            'title' => $this->l('Save'),
                            'class' => 'btn btn-default pull-right',
                            'name' => $form_name,
                        ],
                    ]
                );
                break;

            case 'sale_settings':
                $config_manager = new \Comfino\ConfigManager($this);
                $product_categories = $config_manager->getAllProductCategories();
                $product_category_filters = $config_manager->getProductCategoryFilters();
                $prod_types = $params['offer_types']['sale_settings'];

                $product_category_filter_inputs = [
                    [
                        'type' => 'hidden',
                        'name' => 'active_tab',
                        'required' => false,
                    ],
                ];

                $cat_filter_avail_prod_types = $config_manager->getCatFilterAvailProdTypes($prod_types);

                foreach ($cat_filter_avail_prod_types as $prod_type_code => $prod_type_name) {
                    $product_category_filter_inputs[] = [
                        'type' => 'html',
                        'name' => $prod_type_code . '_label',
                        'required' => false,
                        'html_content' => '<h3>' . $prod_type_name . '</h3>',
                    ];

                    if (isset($product_category_filters[$prod_type_code])) {
                        $selected_categories = array_diff(
                            array_keys($product_categories),
                            $product_category_filters[$prod_type_code]
                        );
                    } else {
                        $selected_categories = array_keys($product_categories);
                    }

                    $product_category_filter_inputs[] = [
                        'type' => 'html',
                        'name' => 'product_category_filter[' . $prod_type_code . ']',
                        'required' => false,
                        'html_content' => $this->renderCategoryTree(
                            'product_categories',
                            $prod_type_code,
                            $selected_categories
                        ),
                    ];
                }

                $fields['sale_settings_category_filter']['form'] = [
                    'legend' => ['title' => $this->l('Rules for the availability of financial products')],
                    'input' => $product_category_filter_inputs,
                    'submit' => [
                        'title' => $this->l('Save'),
                        'class' => 'btn btn-default pull-right',
                        'name' => $form_name,
                    ],
                ];
                break;

            case 'widget_settings':
                $fields['widget_settings_basic']['form'] = ['legend' => ['title' => $this->l('Basic settings')]];

                if (isset($params['messages'])) {
                    // Messages list in the form header (type => message): description, warning, success, error
                    $fields['widget_settings_basic']['form'] = array_merge(
                        $fields['widget_settings_basic']['form'],
                        $params['messages']
                    );
                }

                $fields['widget_settings_basic']['form'] = array_merge(
                    $fields['widget_settings_basic']['form'],
                    [
                        'input' => [
                            [
                                'type' => 'hidden',
                                'name' => 'active_tab',
                                'required' => false,
                            ],
                            [
                                'type' => 'switch',
                                'label' => $this->l('Widget is active?'),
                                'name' => 'COMFINO_WIDGET_ENABLED',
                                'values' => [
                                    [
                                        'id' => 'widget_enabled',
                                        'value' => true,
                                        'label' => $this->l('Enabled'),
                                    ],
                                    [
                                        'id' => 'widget_disabled',
                                        'value' => false,
                                        'label' => $this->l('Disabled'),
                                    ],
                                ],
                            ],
                            [
                                'type' => 'hidden',
                                'label' => $this->l('Widget key'),
                                'name' => 'COMFINO_WIDGET_KEY',
                                'required' => false,
                            ],
                            [
                                'type' => 'select',
                                'label' => $this->l('Widget type'),
                                'name' => 'COMFINO_WIDGET_TYPE',
                                'required' => false,
                                'options' => [
                                    'query' => $params['widget_types'],
                                    'id' => 'key',
                                    'name' => 'name',
                                ],
                            ],
                            [
                                'type' => 'select',
                                'label' => $this->l('Offer type'),
                                'name' => 'COMFINO_WIDGET_OFFER_TYPE',
                                'required' => false,
                                'options' => [
                                    'query' => $params['offer_types']['widget_settings'],
                                    'id' => 'key',
                                    'name' => 'name',
                                ],
                                'desc' => $this->l(
                                    'Other payment methods (Installments 0%, Buy now, pay later, Installments for ' .
                                    'Companies) available after consulting a Comfino advisor (kontakt@comfino.pl).'
                                ),
                            ],
                        ],
                        'submit' => [
                            'title' => $this->l('Save'),
                            'class' => 'btn btn-default pull-right',
                            'name' => $form_name,
                        ],
                    ]
                );

                $fields['widget_settings_advanced']['form'] = [
                    'legend' => ['title' => $this->l('Advanced settings')],
                    'input' => [
                        [
                            'type' => 'text',
                            'label' => $this->l('Widget price element selector'),
                            'name' => 'COMFINO_WIDGET_PRICE_SELECTOR',
                            'required' => false,
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Widget anchor element selector'),
                            'name' => 'COMFINO_WIDGET_TARGET_SELECTOR',
                            'required' => false,
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Price change detection - container selector'),
                            'name' => 'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
                            'required' => false,
                            'desc' => $this->l(
                                'Selector of observed parent element which contains price element.'
                            ),
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Price change detection - container hierarchy level'),
                            'name' => 'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
                            'required' => false,
                            'desc' => $this->l(
                                'Hierarchy level of observed parent element relative to the price element.'
                            ),
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Embedding method'),
                            'name' => 'COMFINO_WIDGET_EMBED_METHOD',
                            'required' => false,
                            'options' => [
                                'query' => [
                                    ['key' => 'INSERT_INTO_FIRST', 'name' => 'INSERT_INTO_FIRST'],
                                    ['key' => 'INSERT_INTO_LAST', 'name' => 'INSERT_INTO_LAST'],
                                    ['key' => 'INSERT_BEFORE', 'name' => 'INSERT_BEFORE'],
                                    ['key' => 'INSERT_AFTER', 'name' => 'INSERT_AFTER'],
                                ],
                                'id' => 'key',
                                'name' => 'name',
                            ],
                        ],
                        [
                            'type' => 'textarea',
                            'label' => $this->l('Widget initialization code'),
                            'name' => 'COMFINO_WIDGET_CODE',
                            'required' => false,
                            'rows' => 15,
                            'cols' => 60,
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save'),
                        'class' => 'btn btn-default pull-right',
                        'name' => $form_name,
                    ],
                ];
                break;

            case 'developer_settings':
                $fields['developer_settings']['form'] = [];

                if (isset($params['messages'])) {
                    // Messages list in the form header (type => message): description, warning, success, error
                    $fields['developer_settings']['form'] = array_merge(
                        $fields['developer_settings']['form'],
                        $params['messages']
                    );
                }

                $fields['developer_settings']['form'] = array_merge(
                    $fields['developer_settings']['form'],
                    [
                        'input' => [
                            [
                                'type' => 'hidden',
                                'name' => 'active_tab',
                                'required' => false,
                            ],
                            [
                                'type' => 'switch',
                                'label' => $this->l('Use test environment'),
                                'name' => 'COMFINO_IS_SANDBOX',
                                'values' => [
                                    [
                                        'id' => 'sandbox_enabled',
                                        'value' => true,
                                        'label' => $this->l('Enabled'),
                                    ],
                                    [
                                        'id' => 'sandbox_disabled',
                                        'value' => false,
                                        'label' => $this->l('Disabled'),
                                    ],
                                ],
                                'desc' => $this->l(
                                    'The test environment allows the store owner to get acquainted with the ' .
                                    'functionality of the Comfino module. This is a Comfino simulator, thanks ' .
                                    'to which you can get to know all the advantages of this payment method. ' .
                                    'The use of the test mode is free (there are also no charges for orders).'
                                ),
                            ],
                            [
                                'type' => 'text',
                                'label' => $this->l('Test environment API key'),
                                'name' => 'COMFINO_SANDBOX_API_KEY',
                                'required' => false,
                                'desc' => $this->l(
                                    'Ask the supervisor for access to the test environment (key, login, password, ' .
                                    'link). Remember, the test key is different from the production key.'
                                ),
                            ],
                        ],
                        'submit' => [
                            'title' => $this->l('Save'),
                            'class' => 'btn btn-default pull-right',
                            'name' => $form_name,
                        ],
                    ]
                );
                break;

            case 'plugin_diagnostics':
                $fields['plugin_diagnostics']['form'] = [];

                if (isset($params['messages'])) {
                    // Messages list in the form header (type => message): description, warning, success, error
                    $fields['plugin_diagnostics']['form'] = array_merge(
                        $fields['plugin_diagnostics']['form'],
                        $params['messages']
                    );
                }

                $fields['plugin_diagnostics']['form'] = array_merge(
                    $fields['plugin_diagnostics']['form'],
                    [
                        'input' => [
                            [
                                'type' => 'textarea',
                                'label' => $this->l('Errors log'),
                                'name' => 'COMFINO_WIDGET_ERRORS_LOG',
                                'required' => false,
                                'readonly' => true,
                                'rows' => 20,
                                'cols' => 60,
                            ],
                        ],
                    ]
                );
                break;

            default:
        }

        return $fields;
    }

    /**
     * @return bool
     */
    private function checkConfiguration()
    {
        return (new \Comfino\ConfigManager($this))->getConfigurationValue('COMFINO_API_KEY') !== null;
    }

    /**
     * @return array
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    private function getTemplateVars()
    {
        $config_manager = new \Comfino\ConfigManager($this);

        return [
            'pay_with_comfino_text' => $config_manager->getConfigurationValue('COMFINO_PAYMENT_TEXT'),
            'logo_url' => \Comfino\Api::getPaywallLogoUrl(),
            'go_to_payment_url' => $this->context->link->getModuleLink($this->name, 'payment', [], true),
            'paywall_options' => $this->getPaywallOptions(),
            'paywall_script_url' => \Comfino\Api::getPaywallFrontendScriptUrl(),
            'offers_url' => $this->context->link->getModuleLink($this->name, 'offer', [], true),
        ];
    }

    /**
     * @return bool
     */
    private function deleteConfigurationValues()
    {
        $result = true;

        foreach (\Comfino\ConfigManager::COMFINO_SETTINGS_OPTIONS as $options) {
            foreach ($options as $option_name) {
                $result &= Configuration::deleteByName($option_name);
            }
        }

        $result &= Configuration::deleteByName('COMFINO_REGISTERED_AT');
        $result &= Configuration::deleteByName('COMFINO_SANDBOX_REGISTERED_AT');

        return $result;
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
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
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
     * @param bool $leafs_only
     * @param array $sub_categories
     * @param int $position
     * @return array
     */
    private function getNestedCategories($leafs_only = false, $sub_categories = [], $position = 0)
    {
        static $categories = null;

        if ($categories === null) {
            $categories = Category::getNestedCategories();
        }

        if ($leafs_only) {
            $filtered_categories = [];
            $child_categories = [];

            foreach (count($sub_categories) ? $sub_categories : $categories as $category) {
                if (isset($category['children'])) {
                    $child_categories[] = $this->getNestedCategories(
                        true, $category['children'], count($filtered_categories) + $position
                    );
                    $position += count($child_categories[count($child_categories) - 1]);
                } else {
                    $category['position'] += $position;
                    $filtered_categories[] = $category;
                }
            }

            $filtered_categories = array_merge($filtered_categories, ...$child_categories);

            usort(
                $filtered_categories,
                static function ($val1, $val2) { return $val1['position'] - $val2['position']; }
            );

            return $filtered_categories;
        }

        return $categories;
    }

    /**
     * @param array $categories
     * @param int[] $selected_categories
     * @return array
     */
    private function buildCategoriesTree($categories, $selected_categories)
    {
        $cat_tree = [];

        foreach ($categories as $category) {
            $tree_node = ['id' => (int) $category['id_category'], 'text' => $category['name']];

            if (isset($category['children'])) {
                $tree_node['children'] = $this->buildCategoriesTree($category['children'], $selected_categories);
            } elseif (in_array($tree_node['id'], $selected_categories, true)) {
                $tree_node['checked'] = true;
            }

            $cat_tree[] = $tree_node;
        }

        return $cat_tree;
    }

    /**
     * @param string $tree_id
     * @param string $product_type
     * @param int[] $selected_categories
     * @return string
     */
    private function renderCategoryTree($tree_id, $product_type, $selected_categories)
    {
        $this->smarty->assign([
            'tree_id' => $tree_id,
            'tree_nodes' => json_encode($this->buildCategoriesTree($this->getNestedCategories(), $selected_categories)),
            'close_depth' => 3,
            'product_type' => $product_type,
        ]);

        if (method_exists($this, 'fetch')) {
            $cat_tree = $this->fetch('module:comfino/views/templates/admin/_configure/product_category_filter.tpl');
        } else {
            $cat_tree = $this->display(__FILE__, 'views/templates/admin/_configure/product_category_filter.tpl');
        }

        return $cat_tree;
    }

    /**
     * @param Cart $cart
     * @param \Comfino\ConfigManager $config_manager
     * @return array|null
     */
    private function getProductTypesFilter($cart, $config_manager)
    {
        if (empty($config_manager->getConfigurationValue('COMFINO_PRODUCT_CATEGORY_FILTERS'))) {
            // Product category filters not set.
            return null;
        }

        $available_product_types = array_map(
            static function (array $offer_type) { return $offer_type['key']; },
            $config_manager->getOfferTypes()
        );

        $filtered_product_types = [];

        // Check product category filters.
        foreach ($available_product_types as $product_type) {
            if ($config_manager->isFinancialProductAvailable($product_type, $cart->getProducts())) {
                $filtered_product_types[] = $product_type;
            }
        }

        return $filtered_product_types;
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

    /**
     * @param int $loan_amount
     * @param array|null $product_types_filter
     * @param string $widget_key
     * @return string
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    private function preparePaywallIframe($loan_amount, $product_types_filter, $widget_key)
    {
        if (is_array($product_types_filter)) {
            if (count($product_types_filter)) {
                // Filters active - product types available conditionally.
                $product_types = implode(',', $product_types_filter);
                $product_types_length = strlen($product_types);
            } else {
                // Filters active - all product types disabled.
                $product_types = "\0";
                $product_types_length = 1;
            }
        } else {
            // No active filters - all product types available unconditionally.
            $product_types = '';
            $product_types_length = 0;
        }

        $request_data = $loan_amount . $product_types . $widget_key;
        $request_params = pack('V', $loan_amount) . pack('v', $product_types_length) . $product_types . $widget_key;

        $hash = hash_hmac(current(Comfino\Api::getHashAlgos()), $request_data, \Comfino\Api::getApiKey(), true);
        $auth = urlencode(base64_encode($request_params . $hash));
        $paywall_api_url = \Comfino\Api::getPaywallApiHost() . '/v1/paywall?auth=' . $auth;

        $this->smarty->assign(array_merge($this->getTemplateVars(), ['paywall_api_url' => $paywall_api_url]));

        if (COMFINO_PS_17) {
            $iframeTemplate = $this->fetch('module:comfino/views/templates/front/payment.tpl');
        } else {
            $iframeTemplate = $this->display(__FILE__, 'payment.tpl');
        }

        return $iframeTemplate;
    }
}
