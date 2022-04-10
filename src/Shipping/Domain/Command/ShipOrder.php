<?php

namespace Shipping\Domain\Command;

class ShipOrder
{
    public function __construct(
        private string $orderId,
    ) {
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }
}
