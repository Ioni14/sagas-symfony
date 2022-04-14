<?php

namespace Shipping\Application\Command;

final class ShipWithAlpine
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
