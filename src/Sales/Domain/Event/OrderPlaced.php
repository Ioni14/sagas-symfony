<?php

namespace Sales\Domain\Event;

use Shared\Domain\DomainEvent;
use Symfony\Component\Uid\Ulid;

final class OrderPlaced implements DomainEvent
{
    public function __construct(
        public readonly Ulid $orderId,
    ) {
    }
}
