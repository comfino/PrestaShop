<?php

namespace Comfino\Order;

use Comfino\Common\Shop\OrderStatusAdapterInterface;

class StatusAdapter implements OrderStatusAdapterInterface
{

    /**
     * @inheritDoc
     */
    public function setStatus($orderId, $status): void
    {
        // TODO: Implement setStatus() method.
    }
}
