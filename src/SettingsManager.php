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

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Cache\StorageAdapter;
use Comfino\CategoryTree\BuildStrategy;
use Comfino\Common\Backend\Cache\Bucket;
use Comfino\Common\Backend\CacheManager;
use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Backend\Payment\ProductTypeFilter\FilterByCartValueLowerLimit;
use Comfino\Common\Backend\Payment\ProductTypeFilter\FilterByExcludedCategory;
use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Backend\Payment\ProductTypeFilterManager;
use Comfino\Common\Shop\Cart;
use Comfino\Common\Shop\Product\CategoryFilter;
use Comfino\Common\Shop\Product\CategoryTree;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Psr\Http\Client\ClientExceptionInterface;

class SettingsManager
{
    /** @var \PaymentModule */
    private static $module;

    /** @var ConfigurationManager */
    private static $config_manager;

    /** @var CacheManager */
    private static $cache_manager;

    /** @var ProductTypeFilterManager */
    private static $filter_manager;

    public static function init(\PaymentModule $module): void
    {
        self::$module = $module;

        if (self::$config_manager === null) {
            self::$config_manager = ConfigManager::getInstance($module);
        }

        if (self::$cache_manager === null) {
            self::$cache_manager = CacheManager::getInstance();
        }
    }

    public static function getWidgetKey(): string
    {
        return self::$config_manager->getConfigurationValue('COMFINO_WIDGET_KEY');
    }

    public static function getProductTypesSelectList(string $list_type): array
    {
        $product_types = self::getProductTypes($list_type, true);

        if (isset($product_types['error'])) {
            $product_types_list = ['key' => 'error', 'name' => $product_types['error']];
        } else {
            $product_types_list = [];

            foreach ($product_types as $product_type_code => $product_type_name) {
                $product_types_list[] = ['key' => $product_type_code, 'name' => $product_type_name];
            }
        }

        return $product_types_list;
    }

    public static function getWidgetTypesSelectList(): array
    {
        $widget_types = self::getWidgetTypes(true);

        if (isset($widget_types['error'])) {
            $widget_types_list = ['key' => 'error', 'name' => $widget_types['error']];
        } else {
            $widget_types_list = [];

            foreach ($widget_types as $widget_type_code => $widget_type_name) {
                $widget_types_list[] = ['key' => $widget_type_code, 'name' => $widget_type_name];
            }
        }

        return $widget_types_list;
    }

    /**
     * @return string[]
     */
    public static function getProductTypes(string $list_type, bool $return_errors = false): array
    {
        $language = \Context::getContext()->language->iso_code;
        $cache_key = "product_types:$list_type:$language";
        $list_type_enum = new ProductTypesListTypeEnum($list_type);

        if (self::getCache()->has($cache_key)) {
            return self::getCache()->get($cache_key);
        }

        try {
            $product_types = ApiClient::getInstance(self::$module)->getProductTypes($list_type_enum);

            self::getCache()->set($cache_key, $product_types->productTypesWithNames);

            return $product_types->productTypesWithNames;
        } catch (\Throwable $e) {
            ApiClient::processApiError('Settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e);

            if ($return_errors) {
                return ['error' => $e->getMessage()];
            }
        }

        return [];
    }

    /**
     * @return string[]
     */
    public static function getProductTypesStrings(string $list_type): array
    {
        return array_keys(self::getProductTypes($list_type));
    }

    /**
     * @return LoanTypeEnum[]
     */
    public static function getProductTypesEnums(string $list_type): array
    {
        return array_map(
            static function (string $product_type): LoanTypeEnum { return new LoanTypeEnum($product_type); },
            array_keys(self::getProductTypes($list_type))
        );
    }

    /**
     * @return string[]
     */
    public static function getWidgetTypes(bool $return_errors = false): array
    {
        $language = \Context::getContext()->language->iso_code;
        $cache_key = "widget_types:$language";

        if (self::getCache()->has($cache_key)) {
            return self::getCache()->get($cache_key);
        }

        try {
            $widget_types = ApiClient::getInstance(self::$module)->getWidgetTypes()->widgetTypesWithNames;

            self::getCache()->set($cache_key, $widget_types);

            return $widget_types;
        } catch (ClientExceptionInterface $e) {
            ApiClient::processApiError('Settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e);

            if ($return_errors) {
                return ['error' => $e->getMessage()];
            }
        }

        return [];
    }

    public static function isProductTypeAllowed(LoanTypeEnum $product_type, Cart $cart): bool
    {
        if (($allowed_product_types = self::getAllowedProductTypes($cart)) === null) {
            return true;
        }

        return in_array($product_type, $allowed_product_types, true);
    }

    /**
     * @return LoanTypeEnum[]|null
     */
    public static function getAllowedProductTypes(string $list_type, Cart $cart): ?array
    {
        $filter_manager = self::getFilterManager($list_type);

        if (!$filter_manager->filtersActive()) {
            return null;
        }

        return $filter_manager->getAllowedProductTypes(self::getProductTypesEnums($list_type), $cart);
    }

    public static function getProductCategoryFilters(): array
    {
        return self::$config_manager->getConfigurationValue('COMFINO_PRODUCT_CATEGORY_FILTERS') ?? [];
    }

    public static function productCategoryFiltersActive(array $product_category_filters): bool
    {
        if (empty($product_category_filters)) {
            return false;
        }

        foreach ($product_category_filters as $excluded_category_ids) {
            if (!empty($excluded_category_ids)) {
                return true;
            }
        }

        return false;
    }

    public static function getCatFilterAvailProdTypes(): array
    {
        $prod_types = self::getProductTypes(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL);
        $cat_filter_avail_prod_types = [];

        foreach (self::$config_manager->getConfigurationValue('COMFINO_CAT_FILTER_AVAIL_PROD_TYPES') as $prod_type) {
            $cat_filter_avail_prod_types[$prod_type] = null;
        }

        if (empty($avail_prod_types = array_intersect_key($prod_types, $cat_filter_avail_prod_types))) {
            $avail_prod_types = $prod_types;
        }

        return $avail_prod_types;
    }

    public static function clearCache(): void
    {
        self::getCache()->clear();
    }

    private static function getCache(): Bucket
    {
        return self::$cache_manager->getCacheBucket('settings', new StorageAdapter('api'));
    }

    private static function getFilterManager(string $list_type): ProductTypeFilterManager
    {
        if (self::$filter_manager === null) {
            self::$filter_manager = ProductTypeFilterManager::getInstance();

            foreach (self::buildFiltersList($list_type) as $filter) {
                self::$filter_manager->addFilter($filter);
            }
        }

        return self::$filter_manager;
    }

    /**
     * @return ProductTypeFilterInterface[]
     */
    private static function buildFiltersList(string $list_type): array
    {
        $config_manager = ConfigManager::getInstance(null);

        $filters = [];

        if (($min_amount = $config_manager->getConfigurationValue('COMFINO_MINIMAL_CART_AMOUNT')) > 0) {
            $available_product_types = self::getProductTypesStrings($list_type);
            $filters[] = new FilterByCartValueLowerLimit(
                array_combine($available_product_types, array_fill(0, count($available_product_types), $min_amount))
            );
        }

        if (self::productCategoryFiltersActive($product_category_filters = self::getProductCategoryFilters())) {
            $filters[] = new FilterByExcludedCategory(
                new CategoryFilter(new CategoryTree(new BuildStrategy())),
                $product_category_filters
            );
        }

        return $filters;
    }
}
