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

//    public static function fromRow(array $row): self
//    {
//        $self = self::create();
//        $self->id = Ulid::fromBinary($row['id']);
//        $self->orderId = Ulid::fromBinary($row['correlation_order_id']);
//
//        return $self;
//    }
//
//    public function toRow(): array
//    {
//        return [
//            'id' => $this->id?->toBinary(),
//            'correlation_order_id' => $this->orderId->toBinary(),
//            'state' => json_encode([
//            ]),
//        ];
//    }
}
