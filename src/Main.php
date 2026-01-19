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

use Comfino;
use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Common\Backend\FileUtils;
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
    private const INSTALL_LOG_PATH = _PS_MODULE_DIR_ . COMFINO_MODULE_NAME . '/var/log/install.log';
    private const UNINSTALL_LOG_PATH = _PS_MODULE_DIR_ . COMFINO_MODULE_NAME . '/var/log/uninstall.log';
    private const UPGRADE_LOG_PATH = _PS_MODULE_DIR_ . COMFINO_MODULE_NAME . '/var/log/upgrade.log';

    private const HOOKS = [
        'paymentOptions',
        'paymentReturn',
        'displayBackofficeComfinoForm',
        'actionOrderStatusPostUpdate',
        'header',
        'actionAdminControllerSetMedia',
        'displayBackOfficeHeader',
    ];

    private const PS16_HOOKS = [
        'payment',
        'displayPaymentEU',
    ];

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

    public static function install(\Comfino $module): bool
    {
        ErrorLogger::init();

        $resultStats = [
            'statuses_created' => 0,
            'statuses_updated' => 0,
            'statuses_create_failed' => 0,
            'statuses_update_failed' => 0,
            'hooks_registered' => 0,
            'hooks_failed' => 0,
            'operations' => [],
        ];

        if (ConfigManager::initConfigurationValues()) {
            $resultStats['operations'][] = ['name' => 'init_configuration_options', 'success' => true];
        } else {
            $resultStats['operations'][] = ['name' => 'init_configuration_options', 'success' => false];
        }

        $customStatusStats = ShopStatusManager::addCustomOrderStatuses($module);
        $resultStats = array_merge($resultStats, array_diff_key($customStatusStats, ['operations' => []]));
        $resultStats['operations'] = array_merge($resultStats['operations'], $customStatusStats['operations']);

        $hookStats = self::registerHooks($module);
        $resultStats = array_merge($resultStats, array_diff_key($hookStats, ['operations' => []]));
        $resultStats['operations'] = array_merge($resultStats['operations'], $hookStats['operations']);

        self::createInstallLog(print_r($resultStats, true));

        return $resultStats['statuses_create_failed'] === 0
            && $resultStats['statuses_update_failed'] === 0
            && $resultStats['hooks_failed'] === 0;
    }

    public static function uninstall(\Comfino $module): bool
    {
        ErrorLogger::init();

        $resultStats = [
            'statuses_removed' => 0,
            'statuses_updated' => 0,
            'statuses_remove_failed' => 0,
            'statuses_update_failed' => 0,
            'hooks_unregistered' => 0,
            'hooks_failed' => 0,
            'operations' => [],
        ];

        $resultStats['operations'][] = [
            'name' => 'hooks_registration',
            'success' => $resultStats['hooks_failed'] === 0,
            'registered' => $resultStats['hooks_registered'],
            'failed' => $resultStats['hooks_failed'],
        ];

        if (ConfigManager::deleteConfigurationValues()) {
            $resultStats['operations'][] = ['name' => 'configuration_options_delete', 'success' => true];
        } else {
            $resultStats['operations'][] = ['name' => 'configuration_options_delete', 'success' => false];
        }

        $customStatusStats = ShopStatusManager::removeCustomOrderStatuses();
        $resultStats = array_merge($resultStats, array_diff_key($customStatusStats, ['operations' => []]));
        $resultStats['operations'] = array_merge($resultStats['operations'], $customStatusStats['operations']);

        $hookStats = self::unregisterHooks($module);
        $resultStats = array_merge($resultStats, array_diff_key($hookStats, ['operations' => []]));
        $resultStats['operations'] = array_merge($resultStats['operations'], $hookStats['operations']);

        if (ApiClient::getInstance()->notifyPluginRemoval()) {
            $resultStats['operations'][] = ['name' => 'uninstall_notification_sent' , 'success' => true];
        } else {
            $resultStats['operations'][] = ['name' => 'uninstall_notification_sent' , 'success' => false];
        }

        self::createUninstallLog(print_r($resultStats, true));

        return $resultStats['statuses_remove_failed'] === 0
            && $resultStats['statuses_update_failed'] === 0
            && $resultStats['hooks_failed'] === 0;
    }

    /**
     * Resets module to initial state without uninstalling:
     * - Adds missing configuration options.
     * - Re-registers all PrestaShop hooks.
     * - Recreates custom order statuses.
     * - Clears configuration and frontend cache.
     *
     * @return array Reset operation statistics
     */
    public static function reset(\Comfino $module): array
    {
        ErrorLogger::init();

        $resultStats = [
            'config_repaired' => 0,
            'config_failed' => 0,
            'hooks_registered' => 0,
            'hooks_failed' => 0,
            'statuses_created' => 0,
            'statuses_updated' => 0,
            'statuses_create_failed' => 0,
            'statuses_update_failed' => 0,
            'operations' => [],
        ];

        // 1. Repair missing configuration options.
        try {
            $repairStats = ConfigManager::repairMissingConfigurationOptions();

            $resultStats['config_repaired'] = $repairStats['repaired'];
            $resultStats['config_failed'] = $repairStats['failed'];
            $resultStats['operations'][] = [
                'name' => 'configuration_repair',
                'success' => $repairStats['failed'] === 0,
                'details' => $repairStats,
            ];
        } catch (\Exception $e) {
            $resultStats['operations'][] = [
                'name' => 'configuration_repair',
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // 2. Re-register all PrestaShop hooks.
        $hookStats = self::registerHooks($module);
        $resultStats = array_merge($resultStats, array_diff_key($hookStats, ['operations' => []]));
        $resultStats['operations'] = array_merge($resultStats['operations'], $hookStats['operations']);

        $resultStats['operations'][] = [
            'name' => 'hooks_registration',
            'success' => $resultStats['hooks_failed'] === 0,
            'registered' => $resultStats['hooks_registered'],
            'failed' => $resultStats['hooks_failed'],
        ];

        // 3. Reinitialize, repair and recreate custom order statuses.
        $reinitStats = ShopStatusManager::reinitializeCustomOrderStatuses($module);
        $resultStats = array_merge($resultStats, array_diff_key($reinitStats, ['operations' => []]));
        $resultStats['operations'] = array_merge($resultStats['operations'], $reinitStats['operations']);

        // 4. Clear configuration and frontend cache.
        CacheManager::getCachePool()->clear();

        return $resultStats;
    }

    /**
     * Returns module instance.
     */
    public static function getModule(): Comfino
    {
        static $module = null;

        if ($module === null) {
            /** @var Comfino $module */
            $module = \Module::getInstanceByName(COMFINO_MODULE_NAME);
        }

        return $module;
    }

    public static function translate(string $string): string
    {
        return self::getModule()->l($string);
    }

    /**
     * Returns base module directory as absolute path.
     */
    public static function getModuleDir(): string
    {
        return _PS_MODULE_DIR_ . COMFINO_MODULE_NAME;
    }

    /**
     * Returns base module directory as relative path.
     */
    public static function getModuleRelativeDir(): string
    {
        return _MODULE_DIR_ . COMFINO_MODULE_NAME;
    }

    /**
     * Returns full absolute path for given path within module directory.
     */
    public static function getModulePath(string $path = ''): string
    {
        return self::getModuleDir() . '/' . ltrim($path, '/');
    }

    /**
     * Returns relative path for given path within module directory.
     */
    public static function getModuleRelativePath(string $path = ''): string
    {
        return self::getModuleRelativeDir() . '/' . ltrim($path, '/');
    }

    /**
     * Renders configuration form.
     */
    public static function getContent(): string
    {
        return TemplateManager::renderModuleView('configuration', 'admin', SettingsForm::processForm());
    }

    /**
     * Renders Comfino iframe with payment form and returns string with iframe HTML for PrestaShop 1.6 or array with
     * \PrestaShop\PrestaShop\Core\Payment\PaymentOption object for PrestaShop 1.7+. Returns empty string/array in
     * case of errors.
     *
     * @return \PrestaShop\PrestaShop\Core\Payment\PaymentOption[]|string
     */
    public static function renderPaywallIframe(\Comfino $module, array $params)
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

        $tools = new Tools(\Context::getContext());

        try {
            $total = round($cart->getOrderTotal(), 2);
            $totalFormatted = $tools->formatPrice($total, $cart->id_currency);
        } catch (\Exception $e) {
            FrontendManager::processError('Paywall rendering error', $e);

            return COMFINO_PS_17 ? [] : '';
        }

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
                'cartTotalFormatted' => $totalFormatted,
                'productDetailsApiPath' => ApiService::getControllerPath('paywallitemdetails', [], false),
            ],
            'is_ps_16' => !COMFINO_PS_17,
            'comfino_logo_url' => ConfigManager::getPaywallLogoUrl(),
            'comfino_label' => ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'),
            'comfino_redirect_url' => ApiService::getControllerUrl('payment'),
        ];

        $paywallIframe = TemplateManager::renderModuleView('payment', 'front', $templateVariables);

        if (COMFINO_PS_17) {
            // PrestaShop 1.7+ uses object of class PaymentOption to represent a payment method.
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

    public static function paymentIsAvailable(\Comfino $module, \Cart $cart): bool
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

        $loanAmount = (int) filter_var(\Context::getContext()->cookie->loan_amount, FILTER_VALIDATE_INT);
        $priceModifier = (int) filter_var(\Context::getContext()->cookie->price_modifier, FILTER_VALIDATE_INT);

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

    public static function getRequestUri(): string
    {
        return isset($_SERVER['REQUEST_URI']) ? \Tools::safeOutput($_SERVER['REQUEST_URI']) : '';
    }

    public static function getCacheRootPath(): string
    {
        return dirname(__DIR__) . '/var';
    }

    public static function getCachePath(): string
    {
        return CacheManager::getCacheFullPath();
    }

    public static function processFinishedPaymentTransaction(\Comfino $module, array $params): string
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

        return TemplateManager::renderModuleView('payment-return', 'front', $tplVariables);
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

    public static function updateUpgradeLog(string $logContents): void
    {
        if (FileUtils::isWritable(dirname(self::UPGRADE_LOG_PATH))) {
            FileUtils::append(date('Y-m-d H:i:s') .  ': ' . self::UPGRADE_LOG_PATH, "$logContents\n");
        }
    }

    public static function readUpgradeLog(): string
    {
        if (FileUtils::isReadable(self::UPGRADE_LOG_PATH)) {
            return FileUtils::read(self::UPGRADE_LOG_PATH);
        }

        return '';
    }

    public static function readInstallLog(): string
    {
        if (FileUtils::isReadable(self::INSTALL_LOG_PATH)) {
            return FileUtils::read(self::INSTALL_LOG_PATH);
        }

        return '';
    }

    public static function readUninstallLog(): string
    {
        if (FileUtils::isReadable(self::UNINSTALL_LOG_PATH)) {
            return FileUtils::read(self::UNINSTALL_LOG_PATH);
        }

        return '';
    }

    private static function createInstallLog(string $logContents): void
    {
        if (FileUtils::isWritable(dirname(self::INSTALL_LOG_PATH))) {
            FileUtils::write(self::INSTALL_LOG_PATH, date('Y-m-d H:i:s') . "\n$logContents");
        }
    }

    private static function createUninstallLog(string $logContents): void
    {
        if (FileUtils::isWritable(dirname(self::UNINSTALL_LOG_PATH))) {
            FileUtils::write(self::UNINSTALL_LOG_PATH, date('Y-m-d H:i:s') . "\n$logContents");
        }
    }

    private static function registerHooks(\Comfino $module): array
    {
        $hooks = self::HOOKS;

        // Add PrestaShop 1.6 specific hooks.
        if (!COMFINO_PS_17) {
            $hooks = array_merge($hooks, self::PS16_HOOKS);
        }

        $resultStats = [
            'hooks_registered' => 0,
            'hooks_failed' => 0,
            'operations' => [],
        ];

        foreach ($hooks as $hookName) {
            try {
                if ($module->registerHook($hookName)) {
                    $resultStats['hooks_registered']++;
                    $resultStats['operations'][] = [
                        'name' => 'hook_registration',
                        'success' => true,
                        'hook' => $hookName,
                    ];
                } else {
                    $resultStats['hooks_failed']++;
                    $resultStats['operations'][] = [
                        'name' => 'hook_registration',
                        'success' => false,
                        'hook' => $hookName,
                    ];
                }
            } catch (\Exception $e) {
                $resultStats['hooks_failed']++;
                $resultStats['operations'][] = [
                    'name' => 'hook_registration',
                    'success' => false,
                    'hook' => $hookName,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $resultStats;
    }

    private static function unregisterHooks(\Comfino $module): array
    {
        $hooks = self::HOOKS;

        // Add PrestaShop 1.6 specific hooks.
        if (!COMFINO_PS_17) {
            $hooks = array_merge($hooks, self::PS16_HOOKS);
        }

        $resultStats = [
            'hooks_unregistered' => 0,
            'hooks_failed' => 0,
            'operations' => [],
        ];

        foreach ($hooks as $hookName) {
            try {
                if ($module->unregisterHook($hookName)) {
                    ++$resultStats['hooks_unregistered'];
                } else {
                    ++$resultStats['hooks_failed'];
                }
            } catch (\Exception $e) {
                ++$resultStats['hooks_failed'];
                $resultStats['operations'][] = [
                    'name' => 'hook_unregistration',
                    'success' => false,
                    'hook' => $hookName,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $resultStats;
    }
}
