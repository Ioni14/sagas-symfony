<?php

namespace Sales\Domain\Event;

use Shared\Domain\DomainEvent;

class OrderPlaced implements DomainEvent
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
