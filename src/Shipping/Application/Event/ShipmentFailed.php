<?php

namespace Shipping\Application\Event;

use Symfony\Component\Uid\Ulid;

class ShipmentFailed
{
    public function __construct(
        public readonly Ulid $orderId,
    ) {
    }
}
