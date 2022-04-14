<?php

namespace Shared\Application;

use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Uid\Ulid;

class SagaContext
{
    #[Ignore]
    private bool $completed = false;

    public function __construct(
        public object $message,
        public ?Ulid $sagaId = null,
    ) {
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function markAsCompleted(): void
    {
        $this->completed = true;
    }
}
