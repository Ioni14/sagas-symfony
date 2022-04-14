<?php

namespace Shipping\Application\Event;

class ShipmentAcceptedByAlpine
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
