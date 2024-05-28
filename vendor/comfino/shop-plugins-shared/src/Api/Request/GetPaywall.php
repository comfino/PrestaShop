<?php

namespace Comfino\Api\Request;

use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Request;
use Comfino\Paywall\PaywallViewTypeEnum;

class GetPaywall extends Request
{
    public function __construct(LoanQueryCriteria $queryCriteria, ?PaywallViewTypeEnum $viewType = null)
    {
        $this->setRequestMethod('GET');
        $this->setApiEndpointPath('shop-plugin-paywall');
        $this->setRequestParams(array_filter([
            'loanAmount' => $queryCriteria->loanAmount,
            'loanTerm' => $queryCriteria->loanTerm,
            'loanType' => $queryCriteria->loanType,
            'productTypes' => $queryCriteria->productTypes,
            'taxId' => $queryCriteria->taxId,
            'viewType' => ($viewType !== null ? (string) $viewType : null),
        ], static fn ($value): bool => $value !== null));
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return null;
    }
}
