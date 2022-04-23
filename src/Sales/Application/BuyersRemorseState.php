<?php

namespace Sales\Application;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use Shared\Application\SagaState;
use Symfony\Component\Uid\Ulid;

#[Entity]
#[Table(name: "buyers_remorse_state_entity")]
final class BuyersRemorseState extends SagaState
{
    #[Column(type: 'ulid', unique: true)]
    public Ulid $orderId;
}
