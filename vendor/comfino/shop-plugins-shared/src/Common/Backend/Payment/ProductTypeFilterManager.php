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

namespace Comfino\Common\Backend\Payment;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Shop\Cart;

final class ProductTypeFilterManager
{
    /**
     * @var $this|null
     */
    private static $instance;

    /** @var ProductTypeFilterInterface[] */
    private $filters = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function addFilter(ProductTypeFilterInterface $filter): void
    {
        $this->filters[] = $filter;
    }

    /**
     * @return ProductTypeFilterInterface[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function filtersActive(): bool
    {
        return count($this->filters) > 0;
    }

    /**
     * @param LoanTypeEnum[] $availableProductTypes
     * @return LoanTypeEnum[]
     */
    public function getAllowedProductTypes(array $availableProductTypes, Cart $cart): array
    {
        if (empty($this->filters)) {
            return $availableProductTypes;
        }

        $allowedProductTypes = [];

        foreach ($this->filters as $filter) {
            $allowedProductTypes[] = $filter->getAllowedProductTypes($availableProductTypes, $cart);
        }

        return array_intersect($availableProductTypes, ...$allowedProductTypes);
    }
}
