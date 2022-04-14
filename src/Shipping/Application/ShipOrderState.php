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

    public static function fromRow(array $row): self
    {
        $payload = json_decode($row['state'], true);

        $self = new self();
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
