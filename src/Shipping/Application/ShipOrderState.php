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

    public static function fromRow(array $row): self
    {
        $payload = json_decode($row['state'], true);

        $self = self::create();
        $self->id = Ulid::fromBinary($row['id']);
        $self->orderId = Ulid::fromBinary($row['correlation_order_id']);
        $self->shipmentAcceptedByMaple = $payload['shipmentAcceptedByMaple'] ?? false;
        $self->shipmentOrderSentToAlpine = $payload['shipmentOrderSentToAlpine'] ?? false;
        $self->shipmentAcceptedByAlpine = $payload['shipmentAcceptedByAlpine'] ?? false;

        return $self;
    }

    public function toRow(): array
    {
        return [
            'id' => $this->id?->toBinary(),
            'correlation_order_id' => $this->orderId->toBinary(),
            'state' => json_encode([
                'shipmentAcceptedByMaple' => $this->shipmentAcceptedByMaple,
                'shipmentOrderSentToAlpine' => $this->shipmentOrderSentToAlpine,
                'shipmentAcceptedByAlpine' => $this->shipmentAcceptedByAlpine,
            ]),
        ];
    }
}
