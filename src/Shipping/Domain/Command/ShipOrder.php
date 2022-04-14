<?php

namespace Shipping\Domain\Command;

final class ShipOrder
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
