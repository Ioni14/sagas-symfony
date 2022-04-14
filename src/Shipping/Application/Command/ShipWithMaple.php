<?php

namespace Shipping\Application\Command;

final class ShipWithMaple
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
