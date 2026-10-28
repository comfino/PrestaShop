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
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Order\OrderManager;
use Comfino\Order\ShopStatusManager;
use Comfino\PluginShared\CacheManager;
use Comfino\Extended\Auth\PaywallAuthTokenGenerator;
use Comfino\Telemetry\ShopEnvironmentReporter;
use Comfino\View\FrontendManager;
use Comfino\View\PaywallCartSerializer;
use Comfino\View\SettingsForm;
use Comfino\View\TemplateManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class Main
{
    private const INSTALL_LOG_FILENAME = 'install.log';
    private const UPGRADE_LOG_FILENAME = 'upgrade.log';
    private const UNINSTALL_LOG_FILENAME = 'uninstall.log';

    private const HOOKS = [
        'paymentOptions',
        'paymentReturn',
        'displayBackofficeComfinoForm',
        'actionOrderStatusPostUpdate',
        'header',
        'actionFrontControllerSetMedia',
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
            /* Isolate cached API responses (creditors, product/widget types) per shop when multistore is active -
               a single module installation can serve shops with different API keys/environments, and the cache must
               not leak data between them. */
            $cacheScope = \Shop::isFeatureActive() ? (string) \Shop::getContextShopID() : '';

            // Initialize cache system.
            CacheManager::init(self::getCacheRootPath(), $cacheScope);

            // Register module API endpoints.
            ApiService::init();

            // Refresh error logging access token in advance.
            ConfigManager::refreshErrorLoggingTokenIfNeeded();
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

        // Data minimization: nothing the module collected stays behind (logs, cache, update notice state).
        $resultStats['operations'][] = ['name' => 'module_data_removal', 'success' => self::removeModuleData()];

        return $resultStats['statuses_remove_failed'] === 0 &&
            $resultStats['statuses_update_failed'] === 0 &&
            $resultStats['hooks_failed'] === 0;
    }

    /**
     * Removes logs (current and legacy location), cache and the update check state.
     */
    private static function removeModuleData(): bool
    {
        $result = true;

        foreach (['COMFINO_UPDATE_NOTICE_DISMISSED', 'COMFINO_GITHUB_VERSION_CHECK_TIME', 'COMFINO_GITHUB_VERSION_INFO'] as $optionName) {
            $result &= \Configuration::deleteByName($optionName);
        }

        self::deleteDirectoryContents(self::getLogPath(), []);
        @rmdir(self::getLogPath());
        self::deleteDirectoryContents(self::getVarPath() . '/log', ['index.php']);
        self::deleteDirectoryContents(self::getVarPath() . '/cache', ['index.php']);

        return (bool) $result;
    }

    /**
     * @param string[] $keep File names to keep in the top-level directory.
     */
    private static function deleteDirectoryContents(string $directory, array $keep): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (new \DirectoryIterator($directory) as $item) {
            if ($item->isDot() || in_array($item->getFilename(), $keep, true)) {
                continue;
            }

            if ($item->isDir() && !$item->isLink()) {
                self::deleteDirectoryContents($item->getPathname(), []);
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
    }

    /**
     * Resets module to initial state without uninstalling.
     *
     * This method:
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
            /** @var \Comfino $module */
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
        // Update notice dismissal: an authenticated POST (employee session and AdminModules token checked by PrestaShop).
        if (\Tools::isSubmit('comfino_dismiss_update_notice')) {
            self::dismissUpdateNotice((string) \Tools::getValue('comfino_dismiss_update_notice'));
        }

        return TemplateManager::renderModuleView('configuration', 'admin', SettingsForm::processForm());
    }

    /**
     * Stores the dismissed update version and answers with JSON. Exits.
     */
    private static function dismissUpdateNotice(string $version): void
    {
        $valid = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && preg_match('/^\d+\.\d+\.\d+$/', $version) === 1;

        if ($valid) {
            \Configuration::updateValue('COMFINO_UPDATE_NOTICE_DISMISSED', $version);
        } else {
            http_response_code(400);
        }

        header('Content-Type: application/json');

        exit(json_encode(['success' => $valid]));
    }

    /**
     * Encodes the checkout bootstrap config for a <script type="application/json"> element. Backend-supplied strings
     * (flag attributes) are hex-escaped, so a value containing "</script>" cannot close the element.
     */
    public static function encodeCheckoutConfig(array $comfinoSettings): string
    {
        $json = json_encode(
            $comfinoSettings,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $json !== false ? $json : '{}';
    }

    /**
     * Renders ComfinoPay iframe with a payment form and returns string with iframe HTML for PrestaShop 1.6 or array with
     * \PrestaShop\PrestaShop\Core\Payment\PaymentOption object for PrestaShop 1.7+. Returns empty string/array in
     * case of errors.
     *
     * @return \PrestaShop\PrestaShop\Core\Payment\PaymentOption[]|string
     */
    public static function renderPaywallIframe(\Comfino $module, array $params)
    {
        /* Prevent duplicate render when OPC modules (SuperCheckout, TheCheckout, etc.) invoke the payment hook more
           than once per request. */
        static $rendered = false;

        if ($rendered) {
            return COMFINO_PS_17 ? [] : '';
        }

        /** @var \Cart $cart */
        $cart = $params['cart'];

        if (!self::paymentIsAvailable($module, $cart)) {
            DebugLogger::logEvent(
                '[PAYWALL]',
                'renderPaywallIframe: paymentIsAvailable=FALSE'
            );

            return COMFINO_PS_17 ? [] : '';
        }

        $context = \Context::getContext();

        try {
            // The getOrderTotal(true) already includes all price modifiers; never add priceModifier again.
            $loanAmount = (int) round($cart->getOrderTotal() * 100);

            $authToken = PaywallAuthTokenGenerator::generateAuthToken(
                (string) ConfigManager::getWidgetKey(),
                (string) ConfigManager::getApiKey()
            );

            $loggingToken = PaywallAuthTokenGenerator::generateLoggingToken(
                (string) ConfigManager::getWidgetKey(),
                ConfigManager::getErrorLoggingAccessToken()
            );

            ApiClient::pinCheckoutTrackId();

            $trackId = ApiClient::getInstance()->getTrackId();

            $allowedProductTypes = null;
            $shopCart = null;

            try {
                $shopCart = OrderManager::getShopCart($cart, 0);
                $allowedProductTypes = SettingsManager::getAllowedProductTypes(
                    ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
                    $shopCart
                );
            } catch (\Throwable $e) {
                ErrorLogger::sendError(
                    $e,
                    OperationContext::PaymentProcessing,
                    (string) $e->getCode(),
                    $e->getMessage()
                );
            }

            if ($allowedProductTypes === []) {
                // All product types filtered out — do not render the payment method.
                return COMFINO_PS_17 ? [] : '';
            }
        } catch (\Throwable $e) {
            FrontendManager::processError('Paywall rendering error', $e, null, null, null, '[ERROR]', OperationContext::PaywallRendering);

            return COMFINO_PS_17 ? [] : '';
        }

        $cartPayload = null;

        if ($shopCart !== null) {
            try {
                $cartPayload = PaywallCartSerializer::toArray($shopCart);
            } catch (\Throwable $e) {
                ErrorLogger::sendError($e, OperationContext::OrderCreation, (string) $e->getCode(), $e->getMessage());
            }
        }

        /* Browser-safe shop environment payload — mirrors the shape produced by
           AbstractShopEnvironmentBuilder::buildForFrontend() in php-sdk (used by Magento via
           MagentoShopEnvironmentBuilder). Replaces the deprecated `shopInfo` field — the SDK accepts this directly as
           PaywallOptions.shopEnvironment with no compat shim involved. */
        $shopEnvironment = [
            'platform' => 'prestashop',
            'platformName' => 'PrestaShop',
            'platformDomain' => \Tools::getShopDomain(false, true),
            'theme' => ['family' => 'prestashop'],
            'language' => $context->language->iso_code,
            'currency' => $context->currency->iso_code,
            'pageContext' => ['type' => 'checkout'],
        ];

        $comfinoSettings = [
            'authToken' => $authToken,
            'loggingToken' => $loggingToken,
            'trackId' => $trackId,
            'trackingConsent' => (bool) ConfigManager::getConfigurationValue('COMFINO_PAYWALL_TRACKING_CONSENT'),
            'loanAmount' => $loanAmount,
            'paymentMethodAuth' => ConfigManager::getPaywallLogoAuthHash(),
            'paymentMethodLabel' => ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT') ?: null,
            'environment' => ConfigManager::isSandboxMode() ? 'sandbox' : 'production',
            'sdkScriptUrl' => ConfigManager::getSdkScriptUrl(),
            'productTypes' => $allowedProductTypes !== null
                ? array_map('strval', $allowedProductTypes)
                : null,
            'allowedProductsConfig' => SettingsManager::getAllowedProductsConfigForFrontend(),
            'cart' => $cartPayload,
            'paywallSettings' => [
                'language' => $context->language->iso_code,
                'currency' => $context->currency->iso_code,
                'customPaywallCss' => ConfigManager::getConfigurationValue('COMFINO_PAYWALL_CUSTOM_CSS_URL') ?: null,
            ],
            'shopEnvironment' => $shopEnvironment,
            'creditors' => SettingsManager::getCreditors() ?: null,
            'productTypeNames' => SettingsManager::getProductTypes(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL) ?: null,
            'flags' => ConfigManager::getRemoteFlags() ?: null,
            'flagAttributes' => ConfigManager::getCheckoutFlagAttributes() ?: null,
            'directRedirect' => (bool) ConfigManager::getConfigurationValue('COMFINO_PAYWALL_DIRECT_REDIRECT'),
            'selectionUrl' => ApiService::getControllerUrl('selection'),
            'selectionToken' => \Tools::getToken(false),
            'scriptNonce' => self::getScriptNonce(),
        ];

        $templateVariables = [
            'loan_amount' => $loanAmount,
            'comfino_settings_json' => self::encodeCheckoutConfig($comfinoSettings),
            'checkout_script_url' => ConfigManager::getCheckoutScriptUrl(),
            'script_nonce' => self::getScriptNonce(),
            'is_ps_16' => !COMFINO_PS_17,
            'comfino_label' => ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'),
            'comfino_default_logo_url' => ConfigManager::getDefaultLogoUrl(),
            'comfino_redirect_url' => ApiService::getControllerUrl('payment'),
        ];

        $rendered = true;

        $paywallIframe = TemplateManager::renderModuleView('payment', 'front', $templateVariables);

        if (COMFINO_PS_17) {
            $comfinoPaymentOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $comfinoPaymentOption->setModuleName($module->name)
                ->setAction(ApiService::getControllerUrl('payment'))
                ->setCallToActionText(ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'))
                ->setLogo(ConfigManager::getDefaultLogoUrl())
                /* PS core submits ONLY the form it renders from these inputs (`#pay-with-{option.id}-form`) on
                   "place order" — it never submits setAdditionalInformation()'s markup (our own payment.tpl,
                   #payment-option-1-additional-information), which is display-only. Without these, the loan
                   type/term the shopper picked in the paywall iframe never reaches payment.php, which silently
                   falls back to a default financial product. PrestaShopAdapter.updatePaymentState() keeps these
                   values live as the shopper interacts with the iframe (matched by `name`, since PS core's
                   template renders no `id` attribute for $option.inputs). */
                ->setInputs([
                    ['type' => 'hidden', 'name' => 'comfino_loan_type', 'value' => ''],
                    ['type' => 'hidden', 'name' => 'comfino_loan_term', 'value' => ''],
                    ['type' => 'hidden', 'name' => 'comfino_promo_code', 'value' => ''],
                ])
                ->setAdditionalInformation($paywallIframe);

            return [$comfinoPaymentOption];
        }

        return $paywallIframe;
    }

    /**
     * Returns CSP nonce for the dynamic SDK script element. Empty string when no nonce
     * source is configured — shops that need strict CSP can populate via a custom hook.
     */
    public static function getScriptNonce(): string
    {
        return '';
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

        try {
            $shopCart = OrderManager::getShopCart($cart, 0);
            $allowedProductTypes = SettingsManager::getAllowedProductTypes(
                ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
                $shopCart
            );
        } catch (\Throwable $e) {
            // Availability cannot be determined (cart or API failure): hide the payment option instead of failing the checkout.
            FrontendManager::processError(
                'Payment availability check error',
                $e,
                null,
                null,
                null,
                '[PAYWALL]',
                OperationContext::PaywallRendering
            );

            return false;
        }

        $paymentIsAvailable = ($allowedProductTypes !== []);

        DebugLogger::logEvent(
            '[PAYWALL]',
            sprintf('paymentIsAvailable: (paywall iframe is %s)', $paymentIsAvailable ? 'visible' : 'invisible'),
            [
                '$paymentIsAvailable' => $paymentIsAvailable,
                '$allowedProductTypes' => $allowedProductTypes,
                '$cartTotalValue' => $shopCart->getTotalValue(),
            ]
        );

        return $paymentIsAvailable;
    }

    public static function getRequestUri(): string
    {
        return isset($_SERVER['REQUEST_URI']) ? \Tools::safeOutput($_SERVER['REQUEST_URI']) : '';
    }

    public static function getVarPath(): string
    {
        return _PS_MODULE_DIR_ . COMFINO_MODULE_NAME . DIRECTORY_SEPARATOR . 'var';
    }

    /**
     * Log directory outside the module (and, on PS 1.7+, outside the document root's static files):
     * <root>/var/logs/comfino on PrestaShop 1.7+, <root>/log/comfino on 1.6.
     */
    public static function getLogPath(): string
    {
        return rtrim(_PS_ROOT_DIR_, '/\\') . (COMFINO_PS_17 ? '/var/logs/comfino' : '/log/comfino');
    }

    /**
     * Creates the log directory with a deny-all .htaccess (both Apache syntaxes) and an index.php stub.
     */
    public static function ensureLogPath(): bool
    {
        $logPath = self::getLogPath();

        if (!is_dir($logPath) && !@mkdir($logPath, 0755, true) && !is_dir($logPath)) {
            return false;
        }

        if (!file_exists("$logPath/.htaccess")) {
            @file_put_contents(
                "$logPath/.htaccess",
                "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n"
            );
        }

        if (!file_exists("$logPath/index.php")) {
            @copy(self::getVarPath() . '/index.php', "$logPath/index.php");
        }

        return is_writable($logPath);
    }

    /**
     * Moves log files from the legacy <module>/var/log directory into the current log directory (existing files at the
     * destination are kept), leaving the old directory empty and denied.
     *
     * @return int Number of moved files.
     */
    public static function migrateLegacyLogs(): int
    {
        $legacyPath = self::getVarPath() . '/log';

        if (!is_dir($legacyPath) || !self::ensureLogPath()) {
            return 0;
        }

        $moved = 0;

        foreach (glob($legacyPath . '/*') ?: [] as $file) {
            if (!is_file($file) || basename($file) === 'index.php') {
                continue;
            }

            $destination = self::getLogPath() . '/' . basename($file);

            if (!file_exists($destination) && @rename($file, $destination)) {
                $moved++;
            } elseif (file_exists($destination)) {
                // Never overwrite a newer log; the legacy copy is dropped so nothing readable stays behind.
                @unlink($file);
            }
        }

        if (!file_exists("$legacyPath/.htaccess")) {
            @file_put_contents($legacyPath . '/.htaccess', "Require all denied\n");
        }

        return $moved;
    }

    public static function getCacheRootPath(): string
    {
        return self::getVarPath();
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

    public static function addLocalStyleLink(string $id, string $styleUrl): void
    {
        if (COMFINO_PS_17) {
            \Context::getContext()->controller->registerStylesheet($id, $styleUrl, ['server' => 'local']);
        } else {
            \Context::getContext()->controller->addCSS($styleUrl);
        }
    }

    public static function updateUpgradeLog(string $logContents): void
    {
        self::appendLog(self::UPGRADE_LOG_FILENAME, $logContents);
    }

    public static function readUpgradeLog(): string
    {
        return self::readLog(self::UPGRADE_LOG_FILENAME);
    }

    public static function readInstallLog(): string
    {
        return self::readLog(self::INSTALL_LOG_FILENAME);
    }

    public static function readUninstallLog(): string
    {
        return self::readLog(self::UNINSTALL_LOG_FILENAME);
    }

    private static function createInstallLog(string $logContents): void
    {
        self::writeLog(self::INSTALL_LOG_FILENAME, $logContents);
    }

    private static function readLog(string $fileName): string
    {
        $logPath = FileUtils::buildPathFromComponents([self::getLogPath(), $fileName]);

        if (FileUtils::isReadable($logPath)) {
            return FileUtils::read($logPath);
        }

        return '';
    }

    private static function writeLog(string $fileName, string $logContents): void
    {
        $logPath = FileUtils::buildPathFromComponents([self::getLogPath(), $fileName]);

        if (self::ensureLogPath() && FileUtils::isWritable(dirname($logPath))) {
            FileUtils::write($logPath, gmdate('Y-m-d H:i:s') . "\n$logContents");
        }
    }

    private static function appendLog(string $fileName, string $logContents): void
    {
        $logPath = FileUtils::buildPathFromComponents([self::getLogPath(), $fileName]);

        if (FileUtils::isWritable($logPath)) {
            FileUtils::append($logPath, gmdate('Y-m-d H:i:s') . "\n$logContents");
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
