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

namespace Comfino\Shop\Order;

use Comfino\Api\Dto\Payment\LoanTypeEnum;

class LoanParameters implements LoanParametersInterface
{
    /** @var int */
    private $amount;
    /** @var int|null */
    private $term;
    /** @var LoanTypeEnum|null */
    private $type;
    /** @var LoanTypeEnum[]|null */
    private $allowedProductTypes;

    /**
     * @param int $amount
     * @param int|null $term
     * @param LoanTypeEnum|null $type
     * @param LoanTypeEnum[]|null $allowedProductTypes
     */
    public function __construct(int $amount, ?int $term = null, ?LoanTypeEnum $type = null, ?array $allowedProductTypes = null)
    {
        $this->amount = $amount;
        $this->term = $term;
        $this->type = $type;
        $this->allowedProductTypes = $allowedProductTypes;
    }

    /**
     * @inheritDoc
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * @inheritDoc
     */
    public function getTerm(): ?int
    {
        return $this->term;
    }

    /**
     * @inheritDoc
     */
    public function getType(): ?LoanTypeEnum
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedProductTypes(): ?array
    {
        return $this->allowedProductTypes;
    }
}
