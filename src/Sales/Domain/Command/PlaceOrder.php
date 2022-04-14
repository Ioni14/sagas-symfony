<?php

namespace Sales\Domain\Command;

final class PlaceOrder
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
