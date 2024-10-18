<?php

namespace Comfino\Api\Request;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\Request;
use Comfino\Shop\Order\CartInterface;
use Comfino\Shop\Order\CartTrait;

class GetPaywallItemDetails extends Request
{
    use CartTrait;

    public function __construct(int $loanAmount, LoanTypeEnum $loanType, private readonly CartInterface $cart)
    {
        $this->setRequestMethod('POST');
        $this->setApiEndpointPath('shop-plugin-paywall-product-details');
        $this->setRequestParams(['loanAmount' => $loanAmount, 'loanTypeSelected' => (string) $loanType]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return $this->getCartAsArray($this->cart);
    }
}
