<?php

namespace Shipping\Application;

use Shared\Application\SagaState;
use Symfony\Component\Uid\Ulid;

class ShipOrderState extends SagaState
{
    public Ulid $orderId;

    public bool $shipmentAcceptedByMaple = false;
    public bool $shipmentOrderSentToAlpine = false;
    public bool $shipmentAcceptedByAlpine = false;
}
