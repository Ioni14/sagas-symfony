<?php

namespace Shared\Application;

use Shipping\Application\SagaInterface;
use Symfony\Component\Uid\AbstractUid;

/**
 * @template TState
 */
abstract class Saga implements SagaInterface
{
    protected AbstractUid $id;
    /** @var TState */
    protected SagaState $state;
    private bool $completed = false;

    /**
     * @internal
     * @return TState
     */
    final public function state(): SagaState
    {
        return $this->state;
    }

    /**
     * @internal
     * @param TState $state
     */
    final public function withSagaContext(SagaState $state): self
    {
        $that = clone $this;
        $that->id = $state->id();
        $that->state = $state;

        return $that;
    }

    /**
     * @return class-string<TState>
     */
    abstract public static function stateClass(): string;

    abstract public static function canStartSaga(object $message): bool;

    abstract public static function mapping(): SagaMapper;

    abstract public static function getHandledMessages(): array;

    final protected function markAsCompleted(): void
    {
        $this->completed = true;
    }

    final public function isCompleted(): bool
    {
        return $this->completed;
    }
}
