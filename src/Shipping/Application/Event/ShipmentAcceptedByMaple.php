<?php

namespace Shipping\Application\Event;

class ShipmentAcceptedByMaple
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
