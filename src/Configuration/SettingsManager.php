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

namespace Comfino\Configuration;

use Comfino\Api\ApiClient;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Backend\Payment\ProductTypeFilter\FilterByCartValueLowerLimit;
use Comfino\Common\Backend\Payment\ProductTypeFilter\FilterByExcludedCategory;
use Comfino\Common\Backend\Payment\ProductTypeFilter\FilterByProductType;
use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Backend\Payment\ProductTypeFilterManager;
use Comfino\Common\Shop\Cart;
use Comfino\Common\Shop\Product\CategoryFilter;
use Comfino\DebugLogger;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\PluginShared\CacheManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class SettingsManager
{
    /** @var ProductTypeFilterManager */
    private static $filterManager;

    public static function getProductTypesSelectList(string $listType): array
    {
        $productTypes = self::getProductTypes($listType, true);

        if (isset($productTypes['error'])) {
            $productTypesList = [['key' => 'error', 'name' => $productTypes['error']]];
        } else {
            $productTypesList = [];

            foreach ($productTypes as $productTypeCode => $productTypeName) {
                $productTypesList[] = ['key' => $productTypeCode, 'name' => $productTypeName];
            }
        }

        return $productTypesList;
    }

    public static function getWidgetTypesSelectList(): array
    {
        $widgetTypes = self::getWidgetTypes(true);

        if (isset($widgetTypes['error'])) {
            $widgetTypesList = [['key' => 'error', 'name' => $widgetTypes['error']]];
        } else {
            $widgetTypesList = [];

            foreach ($widgetTypes as $widgetTypeCode => $widgetTypeName) {
                $widgetTypesList[] = ['key' => $widgetTypeCode, 'name' => $widgetTypeName];
            }
        }

        return $widgetTypesList;
    }

    /**
     * @return string[]
     */
    public static function getProductTypes(string $listType, bool $returnErrors = false): array
    {
        $language = \Context::getContext()->language->iso_code;
        $cacheKey = "product_types.$listType.$language";
        $listTypeEnum = new ProductTypesListTypeEnum($listType);

        if (($productTypes = CacheManager::get($cacheKey)) !== null) {
            return $productTypes;
        }

        try {
            $productTypes = ApiClient::getInstance()->getProductTypes($listTypeEnum);
            $productTypesList = $productTypes->productTypesWithNames;
            $cacheTtl = (int) $productTypes->getHeader('Cache-TTL', '0');

            CacheManager::set($cacheKey, $productTypesList, $cacheTtl, ['admin_product_types']);

            return $productTypesList;
        } catch (\Throwable $e) {
            ApiClient::processApiError('Settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e);

            if ($returnErrors) {
                return ['error' => $e->getMessage()];
            }
        }

        return [];
    }

    /**
     * @return string[]
     */
    public static function getProductTypesStrings(string $listType): array
    {
        return array_keys(self::getProductTypes($listType));
    }

    /**
     * @return LoanTypeEnum[]
     */
    public static function getProductTypesEnums(string $listType): array
    {
        return array_map(
            static function (string $productType): LoanTypeEnum { return new LoanTypeEnum($productType); },
            array_keys(self::getProductTypes($listType))
        );
    }

    /**
     * @return string[]
     */
    public static function getWidgetTypes(bool $returnErrors = false): array
    {
        $language = \Context::getContext()->language->iso_code;
        $cacheKey = "widget_types.$language";

        if (($widgetTypes = CacheManager::get($cacheKey)) !== null) {
            return $widgetTypes;
        }

        $useNewApi = ConfigManager::getConfigurationValue('COMFINO_NEW_WIDGET_ACTIVE', false);

        try {
            $widgetTypes = ApiClient::getInstance()->getWidgetTypes($useNewApi);
            $widgetTypesList = $widgetTypes->widgetTypesWithNames;
            $cacheTtl = (int) $widgetTypes->getHeader('Cache-TTL', '0');

            CacheManager::set($cacheKey, $widgetTypesList, $cacheTtl, ['admin_widget_types']);

            return $widgetTypesList;
        } catch (\Throwable $e) {
            ApiClient::processApiError('Settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e);

            if ($returnErrors) {
                return ['error' => $e->getMessage()];
            }
        }

        return [];
    }

    public static function isProductTypeAllowed(string $listType, LoanTypeEnum $productType, Cart $cart): bool
    {
        if (($allowedProductTypes = self::getAllowedProductTypes($listType, $cart)) === null) {
            return true;
        }

        return in_array($productType, $allowedProductTypes, true);
    }

    /**
     * @return LoanTypeEnum[]|null
     */
    public static function getAllowedProductTypes(string $listType, Cart $cart, bool $returnOnlyArray = false): ?array
    {
        $filterManager = self::getFilterManager($listType);

        if (!$filterManager->filtersActive()) {
            return null;
        }

        $availableProductTypes = self::getProductTypesEnums($listType);
        $allowedProductTypes = $filterManager->getAllowedProductTypes($availableProductTypes, $cart);

        if (ConfigManager::isDebugMode()) {
            $activeFilters = array_map(
                static function (ProductTypeFilterInterface $filter): string {
                    return get_class($filter) . ': ' . json_encode($filter->getAsArray());
                },
                $filterManager->getFilters()
            );

            DebugLogger::logEvent(
                '[PAYWALL]',
                'getAllowedProductTypes',
                [
                    '$activeFilters' => $activeFilters,
                    '$availableProductTypes' => $availableProductTypes,
                    '$allowedProductTypes' => $allowedProductTypes,
                ]
            );
        }

        if ($returnOnlyArray) {
            return $allowedProductTypes;
        }

        return count($availableProductTypes) !== count($allowedProductTypes) ? $allowedProductTypes : null;
    }

    public static function getProductCategoryFilters(): array
    {
        return ConfigManager::getConfigurationValue('COMFINO_PRODUCT_CATEGORY_FILTERS', []);
    }

    public static function productCategoryFiltersActive(array $productCategoryFilters): bool
    {
        if (empty($productCategoryFilters)) {
            return false;
        }

        foreach ($productCategoryFilters as $excludedCategoryIds) {
            if (!empty($excludedCategoryIds)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[] [['prodTypeCode' => 'prodTypeName'], ...]
     */
    public static function getCatFilterAvailProdTypes(): array
    {
        $productTypes = self::getProductTypes(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL);
        $categoryFilterAvailProductTypes = [];

        foreach (ConfigManager::getConfigurationValue('COMFINO_CAT_FILTER_AVAIL_PROD_TYPES') as $prod_type) {
            $categoryFilterAvailProductTypes[$prod_type] = null;
        }

        if (empty($availProductTypes = array_intersect_key($productTypes, $categoryFilterAvailProductTypes))) {
            $availProductTypes = $productTypes;
        }

        return $availProductTypes;
    }

    private static function getFilterManager(string $listType): ProductTypeFilterManager
    {
        if (self::$filterManager === null) {
            self::$filterManager = ProductTypeFilterManager::getInstance();

            foreach (self::buildFiltersList($listType) as $filter) {
                self::$filterManager->addFilter($filter);
            }
        }

        return self::$filterManager;
    }

    /**
     * @return ProductTypeFilterInterface[]
     */
    private static function buildFiltersList(string $listType): array
    {
        $filters = [];
        $minAmount = (int) (round(ConfigManager::getConfigurationValue('COMFINO_MINIMAL_CART_AMOUNT', 0), 2) * 100);

        if ($minAmount > 0) {
            $availableProductTypes = self::getProductTypesStrings($listType);
            $filters[] = new FilterByCartValueLowerLimit(
                array_combine($availableProductTypes, array_fill(0, count($availableProductTypes), $minAmount))
            );
        }

        if ($listType === ProductTypesListTypeEnum::LIST_TYPE_WIDGET
            && ConfigManager::getConfigurationValue('COMFINO_WIDGET_TYPE') === 'with-modal'
            && !empty($widgetProductType = ConfigManager::getConfigurationValue('COMFINO_WIDGET_OFFER_TYPE'))
        ) {
            $filters[] = new FilterByProductType([new LoanTypeEnum($widgetProductType)]);
        }

        if (self::productCategoryFiltersActive($productCategoryFilters = self::getProductCategoryFilters())) {
            $filters[] = new FilterByExcludedCategory(
                new CategoryFilter(ConfigManager::getCategoriesTree()),
                $productCategoryFilters
            );
        }

        return $filters;
    }
}
