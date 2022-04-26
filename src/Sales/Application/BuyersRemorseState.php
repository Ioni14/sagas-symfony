<?php

namespace Sales\Application;

use Shared\Application\SagaState;
use Symfony\Component\Uid\Ulid;

final class BuyersRemorseState extends SagaState
{
    public Ulid $orderId;
}
