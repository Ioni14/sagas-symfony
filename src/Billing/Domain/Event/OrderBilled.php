<?php

namespace Billing\Domain\Event;

use Shared\Domain\DomainEvent;
use Symfony\Component\Uid\Ulid;

final class OrderBilled implements DomainEvent
{
    public function __construct(
        public readonly Ulid $orderId,
    ) {
    }
}
