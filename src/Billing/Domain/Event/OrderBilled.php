<?php

namespace Billing\Domain\Event;

use Shared\Domain\DomainEvent;

final class OrderBilled implements DomainEvent
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
