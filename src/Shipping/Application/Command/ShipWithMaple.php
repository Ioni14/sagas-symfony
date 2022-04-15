<?php

namespace Shipping\Application\Command;

use Symfony\Component\Uid\Ulid;

final class ShipWithMaple
{
    public function __construct(
        public readonly Ulid $orderId,
    ) {
    }
}
