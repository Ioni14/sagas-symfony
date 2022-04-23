<?php

namespace Shipping\Application;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Shared\Application\SagaState;
use Symfony\Component\Uid\Ulid;

#[Entity]
#[Table(name: "shipping_policy_state_entity")]
final class ShippingPolicyState extends SagaState
{
    #[Column(type: 'ulid', unique: true)]
    public Ulid $orderId;

    // TODO : et si on veut mettre tout le reste en JSON ? => serialize json tout le state yolo

    #[Column(type: 'boolean')]
    public bool $orderPlaced = false;
    #[Column(type: 'boolean')]
    public bool $orderBilled = false;
}
