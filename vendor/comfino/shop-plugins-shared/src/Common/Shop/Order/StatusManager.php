<?php

namespace Comfino\Common\Shop\Order;

use Comfino\Common\Shop\OrderStatusAdapterInterface;

final class StatusManager
{
    private static ?self $instance = null;

    public static function getInstance(OrderStatusAdapterInterface $orderStatusAdapter): self
    {
        if (self::$instance === null) {
            self::$instance = new self($orderStatusAdapter);
        }

        return self::$instance;
    }

    private function __construct(private readonly OrderStatusAdapterInterface $orderStatusAdapter)
    {
    }

    public function setOrderStatus(string $externalId, string $status): void
    {
        $this->orderStatusAdapter->setStatus($externalId, $status);
    }
}
