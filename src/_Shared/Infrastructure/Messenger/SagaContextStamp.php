<?php

namespace Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Ulid;

/**
 * @internal
 */
class SagaContextStamp implements StampInterface
{
    public function __construct(
        public readonly Ulid $sagaId,
    ) {
    }
}
