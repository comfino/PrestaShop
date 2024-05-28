<?php

namespace Comfino\Api\Request;

use Comfino\Api\Request;
use Comfino\Shop\Order\OrderInterface;

/**
 * Loan application creation request.
 */
class CreateOrder extends Request
{
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
        $cart = $this->order->getCart();
        $customer = $this->order->getCustomer();

        $products = [];
        $cartTotal = 0;

        foreach ($cart->getItems() as $cartItem) {
            $products[] = array_filter([
                'name' => $cartItem->getProduct()->getName(),
                'quantity' => $cartItem->getQuantity(),
                'price' => $cartItem->getProduct()->getPrice(),
                'photoUrl' => $cartItem->getProduct()->getPhotoUrl(),
                'ean' => $cartItem->getProduct()->getEan(),
                'externalId' => $cartItem->getProduct()->getId(),
                'category' => $cartItem->getProduct()->getCategory(),
            ], static fn ($value): bool => $value !== null);

            $cartTotal += ($cartItem->getProduct()->getPrice() * $cartItem->getQuantity());
        }

        $cartTotalWithDelivery = $cartTotal + ($cart->getDeliveryCost() ?? 0);

        if ($cartTotalWithDelivery > $cart->getTotalAmount()) {
            // Add discount item to the list - problems with cart items value and order total value inconsistency.
            $products[] = [
                'name' => 'Rabat',
                'quantity' => 1,
                'price' => (int) ($cart->getTotalAmount() - $cartTotalWithDelivery),
                'category' => 'DISCOUNT',
            ];
        } elseif ($cartTotalWithDelivery < $cart->getTotalAmount()) {
            // Add correction item to the list - problems with cart items value and order total value inconsistency.
            $products[] = [
                'name' => 'Korekta',
                'quantity' => 1,
                'price' => (int) ($cart->getTotalAmount() - $cartTotalWithDelivery),
                'category' => 'ADDITIONAL_FEE',
            ];
        }

        return array_filter([
            // Basic order data
            'notifyUrl' => $this->order->getNotifyUrl(),
            'returnUrl' => $this->order->getReturnUrl(),
            'orderId' => $this->order->getId(),

            // Payment data
            'loanParameters' => array_filter([
                'amount' => $this->order->getLoanParameters()->getAmount(),
                'term' => $this->order->getLoanParameters()->getTerm(),
                'type' => $this->order->getLoanParameters()->getType(),
                'allowedProductTypes' => $this->order->getLoanParameters()->getAllowedProductTypes(),
            ], static fn ($value): bool => $value !== null),

            // Cart with list of products
            'cart' => array_filter([
                'products' => $products,
                'totalAmount' => $cart->getTotalAmount(),
                'deliveryCost' => $cart->getDeliveryCost(),
                'category' => $cart->getCategory(),
            ], static fn ($value): bool => $value !== null),

            // Customer data (mandatory)
            'customer' => array_filter([
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'email' => $customer->getEmail(),
                'phoneNumber' => $customer->getPhoneNumber(),
                'taxId' => $customer->getTaxId(),
                'ip' => $customer->getIp(),
                'regular' => $customer->isRegular(),
                'logged' => $customer->isLogged(),

                // Customer address (optional)
                'address' => count($address = array_filter([
                    'street' => $customer->getAddress()?->getStreet(),
                    'buildingNumber' => $customer->getAddress()?->getBuildingNumber(),
                    'apartmentNumber' => $customer->getAddress()?->getApartmentNumber(),
                    'postalCode' => $customer->getAddress()?->getPostalCode(),
                    'city' => $customer->getAddress()?->getCity(),
                    'countryCode' => $customer->getAddress()?->getCountryCode(),
                ], static fn ($value): bool => $value !== null)) ? $address : null,
            ], static fn ($value): bool => $value !== null),

            // Seller data (optional)
            'seller' => count($seller = array_filter([
                'taxId' => $this->order->getSeller()?->getTaxId()
            ], static fn ($value): bool => $value !== null)) ? $seller : null,

            // Extra data (optional)
            'accountNumber' => $this->order->getAccountNumber(),
            'transferTitle' => $this->order->getTransferTitle(),
        ], static fn ($value): bool => $value !== null);
    }
}
