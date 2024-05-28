<?php

namespace Comfino\Api\Response;

use Comfino\Api\Dto\Order\Cart;
use Comfino\Api\Dto\Order\Customer;
use Comfino\Api\Dto\Order\LoanParameters;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\Exception\ResponseValidationError;

class GetOrder extends Base
{
    /** @var string */
    public readonly string $orderId;
    /** @var string */
    public readonly string $status;
    /** @var \DateTime|null */
    public readonly ?\DateTime $createdAt;
    /** @var string */
    public readonly string $applicationUrl;
    /** @var string */
    public readonly string $notifyUrl;
    /** @var string */
    public readonly string $returnUrl;
    /** @var LoanParameters */
    public readonly LoanParameters $loanParameters;
    /** @var Cart */
    public readonly Cart $cart;
    /** @var Customer */
    public readonly Customer $customer;

    protected function processResponseBody(array|string|bool|null $deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        try {
            $createdAt = new \DateTime($deserializedResponseBody['createdAt']);
        } catch (\Exception)  {
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
                static fn (string $productType): LoanTypeEnum => LoanTypeEnum::from($productType),
                $deserializedResponseBody['loanParameters']['allowedProductTypes']
            ) : null
        );

        $this->cart = new Cart(
            $deserializedResponseBody['cart']['totalAmount'],
            $deserializedResponseBody['cart']['deliveryCost'],
            $deserializedResponseBody['cart']['category'],
            array_map(
                static fn (array $cartItem): Cart\CartItem => new Cart\CartItem(
                    $cartItem['name'],
                    $cartItem['price'],
                    $cartItem['quantity'],
                    $cartItem['externalId'],
                    $cartItem['photoUrl'],
                    $cartItem['ean'],
                    $cartItem['category']
                ),
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
