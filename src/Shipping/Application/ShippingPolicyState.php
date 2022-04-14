<?php

namespace Shipping\Application;

use Shared\Application\SagaState;
use Symfony\Component\Uid\Ulid;

final class ShippingPolicyState extends SagaState
{
    public Ulid $orderId;
    public bool $orderPlaced = false;
    public bool $orderBilled = false;

    public static function fromRow(array $row): self
    {
        $state = json_decode($row['state'], false);

        $self = new self();
        $self->id = Ulid::fromBinary($row['id']);
        $self->orderId = Ulid::fromBinary($row['correlation_order_id']);
        $self->orderPlaced = $state->order_placed ?? false;
        $self->orderBilled = $state->order_billed ?? false;

        return $self;
    }

    public function toRow(): array
    {
        return [
            'id' => $this->id->toBinary(),
            'correlation_order_id' => $this->orderId->toBinary(),
            'state' => json_encode([
                'order_placed' => $this->orderPlaced,
                'order_billed' => $this->orderBilled,
            ]),
        ];
    }
}
