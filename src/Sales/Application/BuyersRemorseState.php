<?php

namespace Sales\Application;

use Shared\Application\SagaState;
use Symfony\Component\Uid\Ulid;

/**
 * @internal
 */
final class BuyersRemorseState extends SagaState
{
    public Ulid $orderId;

    public static function fromRow(array $row): self
    {
        $self = new self();
        $self->id = Ulid::fromBinary($row['id']);
        $self->orderId = Ulid::fromBinary($row['correlation_order_id']);

        return $self;
    }

    public function toRow(): array
    {
        return [
            'id' => $this->id?->toBinary(),
            'correlation_order_id' => $this->orderId?->toBinary(),
            'state' => json_encode([
            ]),
        ];
    }
}
