<?php

namespace Shipping\Application;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Shared\Application\SagaState;
use Symfony\Component\Uid\Ulid;

#[Entity]
#[Table(name: "ship_order_state_entity")]
class ShipOrderState extends SagaState
{
    #[Column(type: 'ulid', unique: true)]
    public Ulid $orderId;

    #[Column(type: 'boolean')]
    public bool $shipmentAcceptedByMaple = false;
    #[Column(type: 'boolean')]
    public bool $shipmentOrderSentToAlpine = false;
    #[Column(type: 'boolean')]
    public bool $shipmentAcceptedByAlpine = false;
}
