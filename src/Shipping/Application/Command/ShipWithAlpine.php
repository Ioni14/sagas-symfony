<?php

namespace Shipping\Application\Command;

use Symfony\Component\Uid\Ulid;

final class ShipWithAlpine
{
    public function __construct(
        public readonly Ulid $orderId,
    ) {
    }
}
