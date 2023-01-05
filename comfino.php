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

require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/PresentationType.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Api.php';

if (!defined('COMFINO_PS_17')) {
    define('COMFINO_PS_17', version_compare(_PS_VERSION_, '1.7', '>='));
}

if (!defined('COMFINO_VERSION')) {
    define('COMFINO_VERSION', '2.2.2');
}

class Comfino extends PaymentModule
{
    const WIDGET_SCRIPT_PRODUCTION_URL = '//widget.comfino.pl/comfino.min.js';
    const WIDGET_SCRIPT_SANDBOX_URL = '//widget.craty.pl/comfino.min.js';
    const ERROR_LOG_NUM_LINES = 40;
    const COMFINO_SUPPORT_EMAIL = 'pomoc@comfino.pl';
    const COMFINO_SUPPORT_PHONE = '887-106-027';
    const COMFINO_SETTINGS_OPTIONS = [
        'payment_settings' => [
            'COMFINO_API_KEY',
            'COMFINO_PAYMENT_TEXT',
            'COMFINO_PAYMENT_PRESENTATION',
            'COMFINO_MINIMAL_CART_AMOUNT',
        ],
        'widget_settings' => [
            'COMFINO_WIDGET_ENABLED',
            'COMFINO_WIDGET_KEY',
            'COMFINO_WIDGET_PRICE_SELECTOR',
            'COMFINO_WIDGET_TARGET_SELECTOR',
            'COMFINO_WIDGET_TYPE',
            'COMFINO_WIDGET_OFFER_TYPE',
            'COMFINO_WIDGET_EMBED_METHOD',
            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
            'COMFINO_WIDGET_CODE',
        ],
        'developer_settings' => [
            'COMFINO_IS_SANDBOX',
            'COMFINO_SANDBOX_API_KEY',
        ]
    ];

    public function __construct()
    {
        $this->name = 'comfino';
        $this->tab = 'payments_gateways';
        $this->version = '2.2.2';
        $this->author = 'Comfino';
        $this->module_key = '3d3e14c65281e816da083e34491d5a7f';

        $this->bootstrap = true;
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->controllers = ['payment', 'offer'];

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

        include 'sql/install.php';

        $ps16hooks = true;

        if (!COMFINO_PS_17) {
            $ps16hooks = $this->registerHook('payment') && $this->registerHook('displayPaymentEU');
        }

        return $this->initConfigurationValues() &&
            $this->addOrderStates() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn') &&
            $ps16hooks &&
            $this->registerHook('displayBackofficeComfinoForm') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('actionValidateCustomerAddressForm') &&
            $this->registerHook('header');
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        include 'sql/uninstall.php';

        $ps16hooks = true;

        if (!COMFINO_PS_17) {
            $ps16hooks = $this->unregisterHook('payment') && $this->unregisterHook('displayPaymentEU');
        }

        return parent::uninstall() &&
            $this->deleteConfigurationValues() &&
            $this->unregisterHook('paymentOptions') &&
            $this->unregisterHook('paymentReturn') &&
            $ps16hooks &&
            $this->unregisterHook('displayBackofficeComfinoForm') &&
            $this->unregisterHook('actionOrderStatusPostUpdate') &&
            $this->unregisterHook('actionValidateCustomerAddressForm') &&
            $this->unregisterHook('header');
    }

    /**
     * @return string
     */
    public function getContent()
    {
        ErrorLogger::init();

        $active_tab = 'registration';
        $output = [];
        $output_type = 'success';

        if (Tools::isSubmit('submit_configuration')) {
            $active_tab = Tools::getValue('active_tab');
            $widget_key_error = false;
            $widget_key = '';

            $error_empty_msg = $this->l("Field '%s' can not be empty.");
            $error_numeric_format_msg = $this->l("Field '%s' has wrong numeric format.");

            switch ($active_tab) {
                case 'payment_settings':
                case 'developer_settings':
                    if ($active_tab === 'payment_settings') {
                        $api_host = Configuration::get('COMFINO_IS_SANDBOX')
                            ? ComfinoApi::COMFINO_SANDBOX_HOST
                            : ComfinoApi::COMFINO_PRODUCTION_HOST;

                        $api_key = Configuration::get('COMFINO_IS_SANDBOX')
                            ? Configuration::get('COMFINO_SANDBOX_API_KEY')
                            : Tools::getValue('COMFINO_API_KEY');

                        if (Tools::isEmpty(Tools::getValue('COMFINO_API_KEY'))) {
                            $output[] = sprintf($error_empty_msg, $this->l('API key'));
                        }
                        if (Tools::isEmpty(Tools::getValue('COMFINO_PAYMENT_PRESENTATION'))) {
                            $output[] = sprintf($error_empty_msg, $this->l('Payment presentation'));
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
                        $api_host = Tools::getValue('COMFINO_IS_SANDBOX')
                            ? ComfinoApi::COMFINO_SANDBOX_HOST
                            : ComfinoApi::COMFINO_PRODUCTION_HOST;

                        $api_key = Tools::getValue('COMFINO_IS_SANDBOX')
                            ? Tools::getValue('COMFINO_SANDBOX_API_KEY')
                            : Configuration::get('COMFINO_API_KEY');
                    }

                    if (!count($output)) {
                        if (!empty($api_key)) {
                            ComfinoApi::setApiHost($api_host);
                            ComfinoApi::setApiKey($api_key);

                            if (!ComfinoApi::isApiKeyValid()) {
                                $output[] = sprintf($this->l('API key %s is not valid.'), $api_key);
                            } else {
                                $widget_key = ComfinoApi::getWidgetKey();

                                if (is_array($widget_key)) {
                                    if (isset($widget_key['errors'])) {
                                        $output = array_merge($output, $widget_key['errors']);
                                        $output_type = 'warning';
                                        $widget_key_error = true;
                                    }

                                    $widget_key = '';
                                }
                            }
                        }
                    }

                    break;

                case 'widget_settings':
                    if (!is_numeric(Tools::getValue('COMFINO_WIDGET_PRICE_OBSERVER_LEVEL'))) {
                        $output[] = sprintf($error_numeric_format_msg, $this->l('Price change detection level'));
                    }

                    break;
            }

            if (!$widget_key_error && count($output)) {
                $output_type = 'warning';
                $output[] = $this->l('Settings not updated.');
            } else {
                foreach (self::COMFINO_SETTINGS_OPTIONS[$active_tab] as $option_name) {
                    if ($option_name !== 'COMFINO_WIDGET_KEY') {
                        Configuration::updateValue($option_name, Tools::getValue($option_name));
                    }
                }

                Configuration::updateValue('COMFINO_WIDGET_KEY', $widget_key);

                $output[] = $this->l('Settings updated.');
            }
        }

        $this->context->smarty->assign([
            'active_tab' => $active_tab,
            'output' => $output,
            'output_type' => $output_type,
            'logo_url' => ComfinoApi::getLogoUrl(),
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
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }

    /**
     * Prestashop 1.6.* compatibility.
     *
     * @param $params
     *
     * @return false|string|void
     */
    public function hookPayment($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart']) || !$this->checkConfiguration()) {
            return;
        }

        ErrorLogger::init();

        $this->smarty->assign($this->getTemplateVars());

        $minimal_cart_amount = (float) Configuration::get('COMFINO_MINIMAL_CART_AMOUNT');
        if ($this->context->cart->getOrderTotal() < $minimal_cart_amount) {
            return;
        }

        return $this->display(__FILE__, 'payment.tpl');
    }

    /**
     * @param Cart $cart
     *
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
     * Prestashop 1.7.* compatibility.
     *
     * @param array $params
     *
     * @return PrestaShop\PrestaShop\Core\Payment\PaymentOption[]|void
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart']) || !$this->checkConfiguration()) {
            return;
        }

        ErrorLogger::init();

        if ($this->context->cart->getOrderTotal() < (float) Configuration::get('COMFINO_MINIMAL_CART_AMOUNT')) {
            return;
        }

        $this->smarty->assign($this->getTemplateVars());

        $new_option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $new_option->setModuleName($this->name)
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true))
            ->setAdditionalInformation($this->fetch('module:comfino/views/templates/front/payment.tpl'));

        switch (Configuration::get('COMFINO_PAYMENT_PRESENTATION')) {
            default:
            case ComfinoPresentationType::ICON_AND_TEXT:
                $new_option->setCallToActionText(Configuration::get('COMFINO_PAYMENT_TEXT'));
                $new_option->setLogo('//widget.comfino.pl/image/comfino/ecommerce/prestashop/logo.svg');
                break;

            case ComfinoPresentationType::ONLY_ICON:
                $new_option->setCallToActionText('');
                $new_option->setLogo('//widget.comfino.pl/image/comfino/ecommerce/prestashop/logo.svg');
                break;

            case ComfinoPresentationType::ONLY_TEXT:
                $new_option->setCallToActionText(Configuration::get('COMFINO_PAYMENT_TEXT'));
                break;
        }

        return [$new_option];
    }

    /**
     * @param array $params
     *
     * @return string
     *
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return '';
        }

        ErrorLogger::init();

        if (COMFINO_PS_17) {
            $state = $params['order']->getCurrentState();
            $rest_to_paid = $params['order']->getOrdersTotalPaid() - $params['order']->getTotalPaid();

            if (in_array($state, [
                Configuration::get('COMFINO_CREATED'),
                Configuration::get('PS_OS_OUTOFSTOCK'),
                Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'),
            ])) {
                $this->smarty->assign(
                    [
                        'total_to_pay' => Tools::displayPrice(
                            $rest_to_paid,
                            new Currency($params['order']->id_currency),
                            false
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
     *
     * @return string
     */
    public function hookDisplayBackofficeComfinoForm($params)
    {
        return $this->displayForm($params);
    }

    /**
     * @param array $params
     *
     * @return void
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        ErrorLogger::init();

        /** @var OrderState $order_state */
        $order_state = $params['newOrderStatus'];

        if ($order_state->id == Configuration::get('PS_OS_CANCELED')) {
            ComfinoApi::cancelOrder($params['id_order']);
        }
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function hookActionValidateCustomerAddressForm($params)
    {
        $vat_number = $params['form']->getField('vat_number');

        if (!empty($vat_number->getValue())) {
            if (!$this->isValidTaxId($vat_number->getValue())) {
                $vat_number->addError($this->l('Invalid VAT number.'));

                return '0';
            }
        }

        return '1';
    }

    /**
     * @return void
     */
    public function hookHeader()
    {
        if (Configuration::get('COMFINO_WIDGET_ENABLED')) {
            $configCrc = crc32(
                Configuration::get('COMFINO_WIDGET_KEY') . Configuration::get('COMFINO_WIDGET_PRICE_SELECTOR') .
                Configuration::get('COMFINO_WIDGET_TARGET_SELECTOR') . Configuration::get('COMFINO_WIDGET_TYPE') .
                Configuration::get('COMFINO_WIDGET_OFFER_TYPE') . Configuration::get('COMFINO_WIDGET_EMBED_METHOD') .
                Configuration::get('COMFINO_WIDGET_CODE')
            );

            if (COMFINO_PS_17) {
                $this->context->controller->registerJavascript(
                    'comfino',
                    $this->context->link->getModuleLink($this->name, 'script', ['crc' => $configCrc], true),
                    ['server' => 'remote', 'position' => 'head']
                );
            } else {
                $this->context->controller->addJS(
                    $this->context->link->getModuleLink($this->name, 'script', ['crc' => $configCrc], true),
                    false
                );
            }
        }
    }

    /**
     * @param array $params
     *
     * @return string
     */
    private function displayForm($params)
    {
        $config_tab = isset($params['config_tab']) ? $params['config_tab'] : '';

        $helper = $this->getHelperForm('submit_configuration');
        $helper->fields_value['active_tab'] = $config_tab;

        foreach (self::COMFINO_SETTINGS_OPTIONS as $options) {
            foreach ($options as $option_name) {
                $helper->fields_value[$option_name] = Configuration::get($option_name);
            }
        }

        $helper->fields_value['COMFINO_WIDGET_ERRORS_LOG'] = ErrorLogger::getErrorLog(self::ERROR_LOG_NUM_LINES);

        $messages = [];

        switch ($config_tab) {
            case 'payment_settings':
                if (Configuration::get('COMFINO_IS_SANDBOX')) {
                    $messages['warning'] = $this->l('Developer mode is active. You are using test environment.');
                }

                break;

            case 'plugin_diagnostics':
                if (Configuration::get('COMFINO_IS_SANDBOX')) {
                    $messages['warning'] = $this->l('Developer mode is active. You are using test environment.');
                } else {
                    if (!empty(ComfinoApi::getApiKey())) {
                        $messages['success'] = $this->l('Production mode is active.');
                    } else {
                        $messages['error'] = $this->l('Production API key not present.');
                    }
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
     *
     * @return HelperForm
     */
    private function getHelperForm($submit_action, $form_template_dir = null, $form_template = null)
    {
        $helper = new HelperForm();
        $language = (int) Configuration::get('PS_LANG_DEFAULT');

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
     *
     * @return array
     */
    private function getFormFields($params)
    {
        $fields = [];
        $config_tab = isset($params['config_tab']) ? $params['config_tab'] : '';

        switch ($config_tab) {
            case 'registration':
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
                                'label' => $this->l('API key'),
                                'name' => 'COMFINO_API_KEY',
                                'required' => true,
                                'placeholder' => $this->l('Please enter the key provided during registration'),
                            ],
                            [
                                'type' => 'select',
                                'label' => $this->l('Payment presentation'),
                                'name' => 'COMFINO_PAYMENT_PRESENTATION',
                                'required' => true,
                                'options' => [
                                    'query' => [
                                        ['key' => ComfinoPresentationType::ONLY_ICON, 'name' => $this->l('Only icon')],
                                        ['key' => ComfinoPresentationType::ONLY_TEXT, 'name' => $this->l('Only text')],
                                        [
                                            'key' => ComfinoPresentationType::ICON_AND_TEXT,
                                            'name' => $this->l('Icon and text')
                                        ],
                                    ],
                                    'id' => 'key',
                                    'name' => 'name',
                                ],
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
                            'name' => 'submit_configuration',
                        ],
                    ]
                );

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
                                    'query' => [
                                        ['key' => 'simple', 'name' => $this->l('Textual widget')],
                                        ['key' => 'mixed', 'name' => $this->l('Graphical widget with banner')],
                                        [
                                            'key' => 'with-modal',
                                            'name' => $this->l('Graphical widget with installments calculator'),
                                        ],
                                    ],
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
                                    'query' => [
                                        [
                                            'key' => ComfinoApi::INSTALLMENTS_ZERO_PERCENT,
                                            'name' => $this->l('Zero percent installments'),
                                        ],
                                        [
                                            'key' => ComfinoApi::CONVENIENT_INSTALLMENTS,
                                            'name' => $this->l('Convenient installments'),
                                        ],
                                        ['key' => ComfinoApi::PAY_LATER, 'name' => $this->l('Pay later')],
                                    ],
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
                            'name' => 'submit_configuration',
                        ]
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
                            'type' => 'text',
                            'label' => $this->l('Price change detection level'),
                            'name' => 'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
                            'required' => false,
                            'desc' => $this->l(
                                'Hierarchy level of observed parent element relative to the price element.'
                            ),
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
                        'name' => 'submit_configuration',
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
                            'name' => 'submit_configuration',
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
                        ]
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
    private function addOrderStates()
    {
        $languages = Language::getLanguages(false);

        foreach (OrdersList::ADD_ORDER_STATUSES as $state => $name) {
            $new_state = Configuration::get($state);

            if (empty($new_state) || !Validate::isInt($new_state) ||
                !Validate::isLoadedObject(new OrderState($new_state))
            ) {
                $order_state_object = new OrderState();
                $order_state_object->send_email = 0;
                $order_state_object->invoice = 0;
                $order_state_object->color = '#ffffff';
                $order_state_object->unremovable = false;
                $order_state_object->logable = 0;
                $order_state_object->module_name = $this->name;

                foreach ($languages as $language) {
                    if ($language['iso_code'] === 'pl') {
                        $order_state_object->name[$language['id_lang']] = OrdersList::ADD_ORDER_STATUSES_PL[$state];
                    } else {
                        $order_state_object->name[$language['id_lang']] = $name;
                    }
                }

                if ($order_state_object->add()) {
                    Configuration::updateValue($state, $order_state_object->id);
                }
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    private function checkConfiguration()
    {
        return Configuration::get('COMFINO_API_KEY') !== null;
    }

    /**
     * @return array
     */
    private function getTemplateVars()
    {
        return [
            'set_info_url' => $this->context->link->getModuleLink($this->name, 'offer', [], true),
            'pay_with_comfino_text' => Configuration::get('COMFINO_PAYMENT_TEXT'),
            'logo_url' => '//widget.comfino.pl/image/comfino/ecommerce/prestashop/comfino_logo_icon.svg',
            'presentation_type' => Configuration::get('COMFINO_PAYMENT_PRESENTATION'),
            'go_to_payment_url' => $this->context->link->getModuleLink($this->name, 'payment', [], true),
        ];
    }

    /**
     * @return bool
     */
    private function initConfigurationValues()
    {
        $widget_code = "
var script = document.createElement('script');
script.onload = function () {
    ComfinoProductWidget.init({
        widgetKey: '{WIDGET_KEY}',
        priceSelector: '{WIDGET_PRICE_SELECTOR}',
        widgetTargetSelector: '{WIDGET_TARGET_SELECTOR}',        
        type: '{WIDGET_TYPE}',
        offerType: '{OFFER_TYPE}',
        embedMethod: '{EMBED_METHOD}',
        priceObserverLevel: {PRICE_OBSERVER_LEVEL},
        price: null,
        callbackBefore: function () {},
        callbackAfter: function () {}
    });
};
script.src = '{WIDGET_SCRIPT_URL}';
script.async = true;
document.getElementsByTagName('head')[0].appendChild(script);
";

        return Configuration::updateValue('COMFINO_PAYMENT_PRESENTATION', ComfinoPresentationType::ICON_AND_TEXT) &&
            Configuration::updateValue(
                'COMFINO_PAYMENT_TEXT',
                '(Raty | Kup Teraz, Zapłać Póżniej | Finansowanie dla Firm)'
            ) &&
            Configuration::updateValue('COMFINO_MINIMAL_CART_AMOUNT', 30) &&
            Configuration::updateValue('COMFINO_WIDGET_ENABLED', false) &&
            Configuration::updateValue('COMFINO_WIDGET_KEY', '') &&
            Configuration::updateValue(
                'COMFINO_WIDGET_PRICE_SELECTOR',
                COMFINO_PS_17 ? 'span.current-price-value' : 'span[itemprop=price]'
            ) &&
            Configuration::updateValue('COMFINO_WIDGET_TARGET_SELECTOR', 'div.product-actions') &&
            Configuration::updateValue('COMFINO_WIDGET_TYPE', 'with-modal') &&
            Configuration::updateValue('COMFINO_WIDGET_OFFER_TYPE', 'CONVENIENT_INSTALLMENTS') &&
            Configuration::updateValue('COMFINO_WIDGET_EMBED_METHOD', 'INSERT_INTO_LAST') &&
            Configuration::updateValue('COMFINO_WIDGET_PRICE_OBSERVER_LEVEL', '0') &&
            Configuration::updateValue('COMFINO_WIDGET_CODE', trim($widget_code));
    }

    /**
     * @return bool
     */
    private function deleteConfigurationValues()
    {
        $result = true;

        foreach (self::COMFINO_SETTINGS_OPTIONS as $options) {
            foreach ($options as $option_name) {
                $result &= Configuration::deleteByName($option_name);
            }
        }

        return $result;
    }

    /**
     * @param string $tax_id
     *
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
}
