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

    // TODO : et si on veut mettre tout le reste en JSON ?

    #[Column(type: 'boolean')]
    public bool $orderPlaced = false;
    #[Column(type: 'boolean')]
    public bool $orderBilled = false;

//    public static function fromRow(array $row): self
//    {
//        $state = json_decode($row['state'], false);
//
//        $self = self::create();
//        $self->id = Ulid::fromBinary($row['id']);
//        $self->orderId = Ulid::fromBinary($row['correlation_order_id']);
//        $self->orderPlaced = $state->order_placed ?? false;
//        $self->orderBilled = $state->order_billed ?? false;
//
//        return $self;
//    }
//
//    public function toRow(): array
//    {
//        return [
//            'id' => $this->id->toBinary(),
//            'correlation_order_id' => $this->orderId->toBinary(),
//            'state' => json_encode([
//                'order_placed' => $this->orderPlaced,
//                'order_billed' => $this->orderBilled,
//            ]),
//        ];
//    }
}
