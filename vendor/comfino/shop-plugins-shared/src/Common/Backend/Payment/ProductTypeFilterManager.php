<?php

namespace Comfino\Common\Backend\Payment;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Shop\Cart;

final class ProductTypeFilterManager
{
    private static ?self $instance = null;

    /** @var ProductTypeFilterInterface[] */
    private array $filters = [];

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
