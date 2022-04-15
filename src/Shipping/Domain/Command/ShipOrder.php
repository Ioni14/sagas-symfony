<?php

namespace Shipping\Domain\Command;

use Symfony\Component\Uid\Ulid;

final class ShipOrder
{
    public function __construct(
        public readonly Ulid $orderId,
    ) {
    }
}
