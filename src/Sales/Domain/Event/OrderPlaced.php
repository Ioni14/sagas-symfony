<?php

namespace Sales\Domain\Event;

use Shared\Domain\DomainEvent;

class OrderPlaced implements DomainEvent
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
