<?php

namespace Sales\Domain\Command;

class PlaceOrder
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
