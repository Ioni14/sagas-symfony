<?php

namespace Sales\Domain\Command;

use Symfony\Component\Uid\Ulid;

final class PlaceOrder
{
    public function __construct(
        public readonly Ulid $orderId,
    ) {
    }
}
