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

namespace Comfino;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Order\OrderManager;
use Comfino\Order\ShopStatusManager;
use Comfino\PluginShared\CacheManager;
use Comfino\View\FrontendManager;
use Comfino\View\SettingsForm;
use Comfino\View\TemplateManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class Main
{
    /** @var Common\Backend\ErrorLogger */
    private static $errorLogger;
    /** @var string */
    private static $debugLogFilePath;

    public static function init(\PaymentModule $module): void
    {
        self::$errorLogger = ErrorLogger::getLoggerInstance($module);
        self::$debugLogFilePath = _PS_MODULE_DIR_ . $module->name . '/var/log/debug.log';

        // Initialize cache system.
        CacheManager::init(_PS_MODULE_DIR_ . $module->name . '/var');

        // Register module API endpoints.
        ApiService::init($module);
    }

    public static function install(\PaymentModule $module): bool
    {
        ErrorLogger::init($module);

        ConfigManager::initConfigurationValues();
        ShopStatusManager::addCustomOrderStatuses();

        if (!COMFINO_PS_17) {
            $module->registerHook('payment');
            $module->registerHook('displayPaymentEU');
        }

        $module->registerHook('paymentOptions');
        $module->registerHook('paymentReturn');
        $module->registerHook('displayBackofficeComfinoForm');
        $module->registerHook('actionOrderStatusPostUpdate');
        $module->registerHook('actionValidateCustomerAddressForm');
        $module->registerHook('header');
        $module->registerHook('actionAdminControllerSetMedia');

        return true;
    }

    public static function uninstall(\PaymentModule $module): bool
    {
        ConfigManager::deleteConfigurationValues();

        ErrorLogger::init($module);
        ApiClient::getInstance()->notifyPluginRemoval();

        return true;
    }

    /**
     * Renders configuration form.
     */
    public static function getContent(\PaymentModule $module): string
    {
        return TemplateManager::renderModuleView($module, 'configuration', 'admin', SettingsForm::processForm($module));
    }

    /**
     * @return \PrestaShop\PrestaShop\Core\Payment\PaymentOption[]|string|void
     */
    public static function renderPaywallIframe(\PaymentModule $module, array $params)
    {
        /** @var \Cart $cart */
        $cart = $params['cart'];

        if (!self::paymentIsAvailable($module, $cart)
            || ($paywallIframe = self::preparePaywallIframe($module, $cart)) === null
        ) {
            self::debugLog('[PAYWALL]', 'renderPaywallIframe - paymentIsAvailable=FALSE or preparePaywallIframe=NULL');

            return;
        }

        if (COMFINO_PS_17) {
            $comfinoPaymentOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $comfinoPaymentOption->setModuleName($module->name)
                ->setAction(ApiService::getControllerUrl($module, 'payment'))
                ->setCallToActionText(ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'))
                ->setLogo(ApiClient::getPaywallLogoUrl($module))
                ->setAdditionalInformation($paywallIframe);

            return [$comfinoPaymentOption];
        }

        return $paywallIframe;
    }

    public static function processFinishedPaymentTransaction(\PaymentModule $module, array $params): string
    {
        if (!COMFINO_PS_17 || !$module->active) {
            return '';
        }

        ErrorLogger::init($module);

        if (in_array($params['order']->getCurrentState(), [
            (int) \Configuration::get('COMFINO_CREATED'),
            (int) \Configuration::get('PS_OS_OUTOFSTOCK'),
            (int) \Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'),
        ], true)) {
            $tplVariables = [
                'shop_name' => \Context::getContext()->shop->name,
                'status' => 'ok',
                'id_order' => $params['order']->id,
            ];

            if (isset($params['order']->reference) && !empty($params['order']->reference)) {
                $tplVariables['reference'] = $params['order']->reference;
            }
        } else {
            $tplVariables['status'] = 'failed';
        }

        return TemplateManager::renderModuleView($module, 'payment-return', 'front', $tplVariables);
    }

    public static function addScriptLink(
        string $id,
        string $scriptUrl,
        string $position = 'bottom',
        ?string $loadStrategy = null
    ): void {
        if (COMFINO_PS_17) {
            \Context::getContext()->controller->registerJavascript(
                $id,
                $scriptUrl,
                array_merge(
                    ['server' => 'remote', 'position' => $position],
                    $loadStrategy !== null ? ['attributes' => $loadStrategy] : []
                )
            );
        } else {
            \Context::getContext()->controller->addJS($scriptUrl, false);
        }
    }

    public static function debugLog(string $debugPrefix, string $debugMessage, ?array $parameters = null): void
    {
        if ((!isset($_COOKIE['COMFINO_SERVICE_SESSION']) || $_COOKIE['COMFINO_SERVICE_SESSION'] !== 'ACTIVE') && ConfigManager::isServiceMode()) {
            return;
        }

        if (ConfigManager::isDebugMode()) {
            if (!empty($parameters)) {
                $preparedParameters = [];

                foreach ($parameters as $name => $value) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    } elseif (is_bool($value)) {
                        $value = ($value ? 'true' : 'false');
                    }

                    $preparedParameters[] = "$name=$value";
                }

                $debugMessage .= (($debugMessage !== '' ? ': ' : '') . implode(', ', $preparedParameters));
            }

            @file_put_contents(
                self::$debugLogFilePath,
                '[' . date('Y-m-d H:i:s') . "] $debugPrefix: $debugMessage\n",
                FILE_APPEND
            );
        }
    }

    public static function getDebugLog(int $numLines): string
    {
        return self::$errorLogger->getErrorLog(self::$debugLogFilePath, $numLines);
    }

    public static function paymentIsAvailable(\PaymentModule $module, \Cart $cart): bool
    {
        if (ConfigManager::isServiceMode()) {
            if (isset($_COOKIE['COMFINO_SERVICE_SESSION']) && $_COOKIE['COMFINO_SERVICE_SESSION'] === 'ACTIVE') {
                self::debugLog('[PAYWALL]', 'paymentIsAvailable: service mode is active.');
            } else {
                return false;
            }
        }

        if (!$module->active || !OrderManager::checkCartCurrency($module, $cart) || empty(ConfigManager::getApiKey())) {
            self::debugLog('[PAYWALL]', 'paymentIsAvailable - plugin disabled or incomplete configuration.');

            return false;
        }

        ErrorLogger::init($module);

        return SettingsManager::getAllowedProductTypes(
            ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
            OrderManager::getShopCart($cart, (int) \Context::getContext()->cookie->loan_amount)
        ) !== [];
    }

    private static function preparePaywallIframe(\PaymentModule $module, \Cart $cart): ?string
    {
        $total = $cart->getOrderTotal();
        $tools = new Tools(\Context::getContext());

        try {
            return TemplateManager::renderModuleView(
                $module,
                'payment',
                'front',
                [
                    'paywall_iframe' => FrontendManager::getPaywallIframeRenderer($module)
                        ->renderPaywallIframe(ApiService::getControllerUrl($module, 'paywall')),
                    'payment_state_url' => ApiService::getControllerUrl($module, 'paymentstate', [], false),
                    'paywall_options' => [
                        'platform' => 'prestashop',
                        'platformName' => 'PrestaShop',
                        'platformVersion' => _PS_VERSION_,
                        'platformDomain' => \Tools::getShopDomain(),
                        'pluginVersion' => COMFINO_VERSION,
                        'language' => $tools->getLanguageIsoCode($cart->id_lang),
                        'currency' => $tools->getCurrencyIsoCode($cart->id_currency),
                        'cartTotal' => (float) $total,
                        'cartTotalFormatted' => $tools->formatPrice($total, $cart->id_currency),
                        'productDetailsApiPath' => ApiService::getControllerPath($module, 'paywallitemdetails'),
                    ],
                    'is_ps_16' => !COMFINO_PS_17,
                    'comfino_logo_url' => ApiClient::getPaywallLogoUrl($module),
                    'comfino_label' => ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'),
                    'comfino_redirect_url' => ApiService::getControllerUrl($module, 'payment'),
                ]
            );
        } catch (\Throwable $e) {
            ApiClient::processApiError('Paywall error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e);
        }

        return null;
    }
}
