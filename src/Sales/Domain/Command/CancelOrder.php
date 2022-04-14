<?php

namespace Sales\Domain\Command;

class CancelOrder
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
