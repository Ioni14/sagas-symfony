<?php

namespace Sales\Domain\Command;

final class CancelOrder
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
