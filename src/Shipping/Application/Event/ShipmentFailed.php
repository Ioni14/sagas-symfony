<?php

namespace Shipping\Application\Event;

class ShipmentFailed
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
