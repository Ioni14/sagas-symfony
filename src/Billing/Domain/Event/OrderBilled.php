<?php

namespace Billing\Domain\Event;

use Shared\Domain\DomainEvent;

class OrderBilled implements DomainEvent
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
