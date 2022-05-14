<?php

namespace Shared\Application;

use Symfony\Component\Uid\AbstractUid;

/**
 * @template TState
 */
class SagaContext
{
    private bool $completed = false;

    /**
     * @param TState $state
     */
    public function __construct(
        public AbstractUid $id,
        public SagaState $state,
    ) {
    }

    public function markAsCompleted(): void
    {
        $this->completed = true;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }
}
