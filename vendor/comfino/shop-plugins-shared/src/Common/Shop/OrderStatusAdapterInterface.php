<?php

namespace Comfino\Common\Shop;

interface OrderStatusAdapterInterface
{
    public function setStatus(string $orderId, string $status): void;
}
