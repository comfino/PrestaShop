<?php

namespace Comfino\Common\Frontend;

readonly class PaywallItemDetails
{
    /**
     * @param string $productDetails
     * @param string $listItemData
     */
    public function __construct(public string $productDetails, public string $listItemData)
    {
    }
}
