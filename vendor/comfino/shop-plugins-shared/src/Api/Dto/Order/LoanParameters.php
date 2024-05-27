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

namespace Comfino\Api\Dto\Order;

use Comfino\Api\Dto\Payment\LoanTypeEnum;

class LoanParameters
{
    /** @var int
     * @readonly */
    public $amount;
    /** @var int|null
     * @readonly */
    public $maxAmount;
    /** @var int
     * @readonly */
    public $term;
    /** @var LoanTypeEnum
     * @readonly */
    public $type;
    /** @var LoanTypeEnum[]|null
     * @readonly */
    public $allowedProductTypes;

    /**
     * @param int $amount
     * @param int|null $maxAmount
     * @param int $term
     * @param LoanTypeEnum $type
     * @param LoanTypeEnum[]|null $allowedProductTypes
     */
    public function __construct(
        int $amount,
        ?int $maxAmount,
        int $term,
        LoanTypeEnum $type,
        ?array $allowedProductTypes
    ) {
        $this->amount = $amount;
        $this->maxAmount = $maxAmount;
        $this->term = $term;
        $this->type = $type;
        $this->allowedProductTypes = $allowedProductTypes;
    }
}
