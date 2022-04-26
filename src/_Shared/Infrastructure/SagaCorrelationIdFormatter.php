<?php

namespace Shared\Infrastructure;

use Symfony\Component\Uid\AbstractUid;

class SagaCorrelationIdFormatter
{
    public function format(mixed $correlationId): string|int
    {
        if ($correlationId instanceof AbstractUid) {
            return $correlationId->toRfc4122();
        }

        if ($correlationId instanceof \DateTimeInterface) {
            return $correlationId->format('Y-m-d H:i:s');
        }

        if (is_int($correlationId)) {
            return $correlationId;
        }

        return (string) $correlationId;
    }
}
