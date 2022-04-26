<?php

namespace Shipping\Application;

use Shared\Application\SagaState;
use Symfony\Component\Uid\Ulid;

final class ShippingPolicyState extends SagaState
{
    public Ulid $orderId;

    public bool $orderPlaced = false;
    public bool $orderBilled = false;
}
