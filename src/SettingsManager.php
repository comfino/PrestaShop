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
use Comfino\Common\Backend\Payment\ProductTypeFilter\FilterByExcludedCategory;
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

    public static function getProductTypes(ProductTypesListTypeEnum $list_type): array
    {
        if (self::getCache()->has('product_types')) {
            return self::getCache()->get('product_types');
        }

        try {
            $product_types = ApiClient::getInstance(self::$module)->getProductTypes($list_type)->productTypesWithNames;

            self::getCache()->set('product_types', $product_types);

            return $product_types;
        } catch (ClientExceptionInterface $e) {
        }

        return [];
    }

    public static function getWidgetTypes(): array
    {
        if (self::getCache()->has('widget_types')) {
            return self::getCache()->get('widget_types');
        }

        try {
            $widget_types = ApiClient::getInstance(self::$module)->getWidgetTypes()->widgetTypesWithNames;

            self::getCache()->set('widget_types', $widget_types);

            return $widget_types;
        } catch (ClientExceptionInterface $e) {
        }

        return [];
    }

    /**
     * @return LoanTypeEnum[]|null
     */
    public static function getAllowedProductTypes(Cart $cart): ?array
    {
        if (!self::productCategoryFiltersActive($product_category_filters = self::getProductCategoryFilters())) {
            return null;
        }

        self::getFilterManager()->addFilter(
            new FilterByExcludedCategory(
                $cart,
                new CategoryFilter(new CategoryTree(new BuildStrategy())),
                $product_category_filters
            )
        );

        return self::getFilterManager()->getAvailableProductTypes();
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
        $prod_types = self::getProductTypes(new ProductTypesListTypeEnum(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL));
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

    private static function getFilterManager(): ProductTypeFilterManager
    {
        if (self::$filter_manager === null) {
            self::$filter_manager = ProductTypeFilterManager::getInstance(array_map(
                static function (string $product_type): LoanTypeEnum { return LoanTypeEnum::from($product_type); },
                self::getProductTypes(
                    new ProductTypesListTypeEnum(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL)
                )
            ));
        }

        return self::$filter_manager;
    }
}
