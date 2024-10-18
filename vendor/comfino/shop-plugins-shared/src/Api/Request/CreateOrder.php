<?php

namespace Comfino\Api\Request;

use Comfino\Api\Request;
use Comfino\Shop\Order\CartTrait;
use Comfino\Shop\Order\OrderInterface;

/**
 * Loan application creation request.
 */
class CreateOrder extends Request
{
    use CartTrait;

    /**
     * @param OrderInterface $order Full order data (cart, loan details)
     */
    public function __construct(private readonly OrderInterface $order)
    {
        $this->setRequestMethod('POST');
        $this->setApiEndpointPath('orders');
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): array
    {
        $customer = $this->order->getCustomer();

        return array_filter(
            [
                // Basic order data
                'notifyUrl' => $this->order->getNotifyUrl(),
                'returnUrl' => $this->order->getReturnUrl(),
                'orderId' => $this->order->getId(),

                // Payment data
                'loanParameters' => array_filter(
                    [
                        'amount' => $this->order->getLoanParameters()->getAmount(),
                        'term' => $this->order->getLoanParameters()->getTerm(),
                        'type' => $this->order->getLoanParameters()->getType(),
                        'allowedProductTypes' => $this->order->getLoanParameters()->getAllowedProductTypes(),
                    ],
                    static fn ($value): bool => $value !== null
                ),

                // Cart with list of products
                'cart' => $this->getCartAsArray($this->order->getCart()),

                // Customer data (mandatory)
                'customer' => array_filter(
                    [
                        'firstName' => $customer->getFirstName(),
                        'lastName' => $customer->getLastName(),
                        'email' => $customer->getEmail(),
                        'phoneNumber' => $customer->getPhoneNumber(),
                        'taxId' => $customer->getTaxId(),
                        'ip' => $customer->getIp(),
                        'regular' => $customer->isRegular(),
                        'logged' => $customer->isLogged(),

                        // Customer address (optional)
                        'address' => count(
                            $address = array_filter(
                                [
                                    'street' => $customer->getAddress()?->getStreet(),
                                    'buildingNumber' => $customer->getAddress()?->getBuildingNumber(),
                                    'apartmentNumber' => $customer->getAddress()?->getApartmentNumber(),
                                    'postalCode' => $customer->getAddress()?->getPostalCode(),
                                    'city' => $customer->getAddress()?->getCity(),
                                    'countryCode' => $customer->getAddress()?->getCountryCode(),
                                ],
                                static fn ($value): bool => $value !== null
                            )
                        ) ? $address : null,
                    ],
                    static fn ($value): bool => $value !== null
                ),

                // Seller data (optional)
                'seller' => count(
                    $seller = array_filter(
                        ['taxId' => $this->order->getSeller()?->getTaxId()],
                        static fn ($value): bool => $value !== null
                    )
                ) ? $seller : null,

                // Extra data (optional)
                'accountNumber' => $this->order->getAccountNumber(),
                'transferTitle' => $this->order->getTransferTitle(),
            ],
            static fn ($value): bool => $value !== null
        );
    }
}
