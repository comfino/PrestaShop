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

use Comfino\Api\Dto\Order\Cart;
use Comfino\Api\Dto\Order\Customer;
use Comfino\Api\Dto\Order\LoanParameters;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\Exception\ResponseValidationError;

class GetOrder extends Base
{
    /** @var string
     * @readonly */
    public $orderId;
    /** @var string
     * @readonly */
    public $status;
    /** @var \DateTime|null
     * @readonly */
    public $createdAt;
    /** @var string
     * @readonly */
    public $applicationUrl;
    /** @var string
     * @readonly */
    public $notifyUrl;
    /** @var string
     * @readonly */
    public $returnUrl;
    /** @var LoanParameters
     * @readonly */
    public $loanParameters;
    /** @var Cart
     * @readonly */
    public $cart;
    /** @var Customer
     * @readonly */
    public $customer;

    /**
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        try {
            $createdAt = new \DateTime($deserializedResponseBody['createdAt']);
        } catch (\Exception $exception)  {
            $createdAt = null;
        }

        $this->orderId = $deserializedResponseBody['orderId'];
        $this->status = $deserializedResponseBody['status'];
        $this->createdAt = $createdAt;
        $this->applicationUrl = $deserializedResponseBody['applicationUrl'];
        $this->notifyUrl = $deserializedResponseBody['notifyUrl'];
        $this->returnUrl = $deserializedResponseBody['returnUrl'];

        $this->loanParameters = new LoanParameters(
            $deserializedResponseBody['loanParameters']['amount'],
            $deserializedResponseBody['loanParameters']['maxAmount'],
            $deserializedResponseBody['loanParameters']['term'],
            LoanTypeEnum::from($deserializedResponseBody['loanParameters']['type']),
            $deserializedResponseBody['loanParameters']['allowedProductTypes'] !== null ? array_map(
                static function (string $productType) : LoanTypeEnum {
                    return LoanTypeEnum::from($productType);
                },
                $deserializedResponseBody['loanParameters']['allowedProductTypes']
            ) : null
        );

        $this->cart = new Cart(
            $deserializedResponseBody['cart']['totalAmount'],
            $deserializedResponseBody['cart']['deliveryCost'],
            $deserializedResponseBody['cart']['category'],
            array_map(
                static function (array $cartItem) : Cart\CartItem {
                    return new Cart\CartItem(
                        $cartItem['name'],
                        $cartItem['price'],
                        $cartItem['quantity'],
                        $cartItem['externalId'],
                        $cartItem['photoUrl'],
                        $cartItem['ean'],
                        $cartItem['category']
                    );
                },
                $deserializedResponseBody['cart']['products']
            )
        );

        $this->customer = new Customer(
            $deserializedResponseBody['customer']['firstName'],
            $deserializedResponseBody['customer']['lastName'],
            $deserializedResponseBody['customer']['email'],
            $deserializedResponseBody['customer']['phoneNumber'],
            $deserializedResponseBody['customer']['ip'],
            $deserializedResponseBody['customer']['taxId'],
            $deserializedResponseBody['customer']['regular'],
            $deserializedResponseBody['customer']['logged'],
            $deserializedResponseBody['customer']['address'] !== null ? new Customer\Address(
                $deserializedResponseBody['customer']['address']['street'],
                $deserializedResponseBody['customer']['address']['buildingNumber'],
                $deserializedResponseBody['customer']['address']['apartmentNumber'],
                $deserializedResponseBody['customer']['address']['postalCode'],
                $deserializedResponseBody['customer']['address']['city'],
                $deserializedResponseBody['customer']['address']['countryCode']
            ) : null
        );
    }
}
