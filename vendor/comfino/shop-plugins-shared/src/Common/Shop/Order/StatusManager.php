<?php

namespace Comfino\Common\Shop\Order;

use Comfino\Common\Shop\OrderStatusAdapterInterface;

final class StatusManager
{
    /**
     * @readonly
     * @var \Comfino\Common\Shop\OrderStatusAdapterInterface
     */
    private $orderStatusAdapter;
    /**
     * @var $this|null
     */
    private static $instance;

    public static function getInstance(OrderStatusAdapterInterface $orderStatusAdapter): self
    {
        if (self::$instance === null) {
            self::$instance = new self($orderStatusAdapter);
        }

        return self::$instance;
    }

    private function __construct(OrderStatusAdapterInterface $orderStatusAdapter)
    {
        $this->orderStatusAdapter = $orderStatusAdapter;
    }

    public function setOrderStatus(string $externalId, string $status): void
    {
        $this->orderStatusAdapter->setStatus($externalId, $status);
    }
}
