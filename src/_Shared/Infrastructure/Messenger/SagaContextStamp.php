<?php

namespace Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\AbstractUid;

/**
 * @internal
 */
class SagaContextStamp implements StampInterface
{
    public function __construct(
        public readonly AbstractUid $sagaId,
    ) {
    }
}
