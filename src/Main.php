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
use Comfino\View\SettingsForm;
use Comfino\View\TemplateManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class Main
{
    /** @var bool */
    private static $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        try {
            // Initialize cache system.
            CacheManager::init(self::getCacheRootPath());

            // Register module API endpoints.
            ApiService::init();
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        self::$initialized = true;
    }

    public static function install(\PaymentModule $module): bool
    {
        ErrorLogger::init();

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
        $module->registerHook('header');
        $module->registerHook('actionAdminControllerSetMedia');

        return true;
    }

    public static function uninstall(): bool
    {
        ConfigManager::deleteConfigurationValues();

        ErrorLogger::init();
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
     * @return \PrestaShop\PrestaShop\Core\Payment\PaymentOption[]|string
     */
    public static function renderPaywallIframe(\PaymentModule $module, array $params)
    {
        /** @var \Cart $cart */
        $cart = $params['cart'];

        if (!self::paymentIsAvailable($module, $cart)) {
            DebugLogger::logEvent(
                '[PAYWALL]',
                'renderPaywallIframe: paymentIsAvailable=FALSE or preparePaywallIframe=NULL'
            );

            return COMFINO_PS_17 ? [] : '';
        }

        $total = round($cart->getOrderTotal(), 2);
        $tools = new Tools(\Context::getContext());

        $templateVariables = [
            'paywall_url' => ApiService::getControllerUrl('paywall', [], false),
            'payment_state_url' => ApiService::getControllerUrl('paymentstate', [], false),
            'paywall_options' => [
                'platform' => 'prestashop',
                'platformName' => 'PrestaShop',
                'platformVersion' => _PS_VERSION_,
                'platformDomain' => \Tools::getShopDomain(),
                'pluginVersion' => COMFINO_VERSION,
                'language' => $tools->getLanguageIsoCode($cart->id_lang),
                'currency' => $tools->getCurrencyIsoCode($cart->id_currency),
                'cartTotal' => $total,
                'cartTotalFormatted' => $tools->formatPrice($total, $cart->id_currency),
                'productDetailsApiPath' => ApiService::getControllerPath('paywallitemdetails', [], false),
            ],
            'is_ps_16' => !COMFINO_PS_17,
            'comfino_logo_url' => ConfigManager::getPaywallLogoUrl(),
            'comfino_label' => ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'),
            'comfino_redirect_url' => ApiService::getControllerUrl('payment'),
        ];

        $paywallIframe = TemplateManager::renderModuleView($module, 'payment', 'front', $templateVariables);

        if (COMFINO_PS_17) {
            $comfinoPaymentOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $comfinoPaymentOption->setModuleName($module->name)
                ->setAction(ApiService::getControllerUrl('payment'))
                ->setCallToActionText(ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'))
                ->setLogo(ConfigManager::getPaywallLogoUrl())
                ->setAdditionalInformation($paywallIframe);

            return [$comfinoPaymentOption];
        }

        return $paywallIframe;
    }

    public static function paymentIsAvailable(\PaymentModule $module, \Cart $cart): bool
    {
        if (ConfigManager::isServiceMode()) {
            if (isset($_COOKIE['COMFINO_SERVICE_SESSION']) && $_COOKIE['COMFINO_SERVICE_SESSION'] === 'ACTIVE') {
                DebugLogger::logEvent('[PAYWALL]', 'paymentIsAvailable: service mode is active.');
            } else {
                return false;
            }
        }

        if (!$module->active || !OrderManager::checkCartCurrency($module, $cart) || empty(ConfigManager::getApiKey())) {
            DebugLogger::logEvent('[PAYWALL]', 'paymentIsAvailable - plugin disabled or incomplete configuration.');

            return false;
        }

        ErrorLogger::init();

        $loanAmount = (int) \Context::getContext()->cookie->loan_amount;
        $priceModifier = (int) \Context::getContext()->cookie->price_modifier;

        $shopCart = OrderManager::getShopCart($cart, $priceModifier);
        $allowedProductTypes = SettingsManager::getAllowedProductTypes(
            ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
            $shopCart
        );
        $paymentIsAvailable = ($allowedProductTypes !== []);

        DebugLogger::logEvent(
            '[PAYWALL]',
            sprintf('paymentIsAvailable: (paywall iframe is %s)', $paymentIsAvailable ? 'visible' : 'invisible'),
            [
                '$paymentIsAvailable' => $paymentIsAvailable,
                '$allowedProductTypes' => $allowedProductTypes,
                '$loanAmount' => $loanAmount,
                '$priceModifier' => $priceModifier,
                '$cartTotalValue' => $shopCart->getTotalValue(),
            ]
        );

        return $paymentIsAvailable;
    }

    public static function getCacheRootPath(): string
    {
        return dirname(__DIR__) . '/var';
    }

    public static function getCachePath(): string
    {
        return CacheManager::getCacheFullPath();
    }

    public static function processFinishedPaymentTransaction(\PaymentModule $module, array $params): string
    {
        if (!COMFINO_PS_17 || !$module->active) {
            return '';
        }

        ErrorLogger::init();

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

    public static function addStyleLink(string $id, $styleUrl): void
    {
        if (COMFINO_PS_17) {
            \Context::getContext()->controller->registerStylesheet($id, $styleUrl, ['server' => 'remote']);
        } else {
            \Context::getContext()->controller->addCSS($styleUrl);
        }
    }
}
