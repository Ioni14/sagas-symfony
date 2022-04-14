<?php

namespace Billing\Domain\Event;

use Shared\Domain\DomainEvent;

class OrderBilled implements DomainEvent
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
