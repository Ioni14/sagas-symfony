<?php

namespace Sales\Domain\Command;

class PlaceOrder
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
