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

namespace Comfino\Api\Response;

use Comfino\Api\Dto\Payment\FinancialProduct;
use Comfino\Api\Dto\Payment\LoanParameters;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\Exception\ResponseValidationError;

class GetFinancialProducts extends Base
{
    /** @var FinancialProduct[]
     * @readonly */
    public $financialProducts;

    /**
     * @inheritDoc
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        $financialProducts = [];

        foreach ($deserializedResponseBody as $financialProduct) {
            $financialProducts[] = new FinancialProduct($financialProduct['name'], LoanTypeEnum::from($financialProduct['type']), $financialProduct['description'], $financialProduct['icon'], $financialProduct['instalmentAmount'], $financialProduct['toPay'], $financialProduct['loanTerm'], $financialProduct['rrso'], $financialProduct['representativeExample'], $financialProduct['remarks'], array_map(
                static function (array $loanParams) : LoanParameters {
                    return new LoanParameters(
                        $loanParams['instalmentAmount'],
                        $loanParams['toPay'],
                        $loanParams['loanTerm'],
                        $loanParams['rrso']
                    );
                },
                $financialProduct['loanParameters']
            ));
        }

        $this->financialProducts = $financialProducts;
    }
}
