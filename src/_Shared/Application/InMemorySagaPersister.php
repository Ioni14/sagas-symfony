<?php

namespace Shared\Application;

use Shared\Infrastructure\SagaCorrelationIdFormatter;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;

class InMemorySagaPersister implements SagaPersisterInterface
{
    /**
     * @var SagaState[]
     */
    private array $states = [];

    public function setup(string $sagaHandlerClass): void
    {
        // nothing
    }

    /**
     * {@inheritDoc}
     */
    public function findStateBySagaId(AbstractUid $sagaId, string $sagaHandlerClass): ?SagaState
    {
        if (!is_a($sagaHandlerClass, Saga::class, true)) {
            throw new \InvalidArgumentException('Argument $sagaHandlerClass must extends '.Saga::class);
        }

        foreach ($this->states as $persistedState) {
            if ($sagaHandlerClass::stateClass() === $persistedState::class && $persistedState->id()->equals($sagaId)) {
                $ref = new \ReflectionProperty($persistedState, 'isNew');
                $ref->setAccessible(true);
                $ref->setValue($persistedState, false);

                return $persistedState;
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function findStateByCorrelationId(object $message, string $sagaHandlerClass): ?SagaState
    {
        if (!is_a($sagaHandlerClass, Saga::class, true)) {
            throw new \InvalidArgumentException('Argument $sagaHandlerClass must extends '.Saga::class);
        }

        $mapping = $sagaHandlerClass::mapping();
        $stateCorrelationField = $mapping->stateCorrelationIdField();
        $messageCorrelationField = $mapping->messageCorrelationIdField($message::class);
        if ($messageCorrelationField === null) {
            throw new \InvalidArgumentException(sprintf('Unable to determine message correlation id field for message %s. Please check the Saga mapping of %s.', $message::class, $sagaHandlerClass));
        }

        $correlationValue = (new SagaCorrelationIdFormatter())->format($message->$messageCorrelationField);

        foreach ($this->states as $persistedState) {
            $persistedCorrelationValue = (new SagaCorrelationIdFormatter())->format($persistedState->$stateCorrelationField);
            if ($persistedCorrelationValue === $correlationValue) {
                $ref = new \ReflectionProperty($persistedState, 'isNew');
                $ref->setAccessible(true);
                $ref->setValue($persistedState, false);

                return $persistedState;
            }
        }

        return null;
    }

    public function saveState(SagaState $state, object $message, string $sagaHandlerClass): void
    {
        if (!is_a($sagaHandlerClass, Saga::class, true)) {
            throw new \InvalidArgumentException('Argument $sagaHandlerClass must extends '.Saga::class);
        }

        $this->states[] = $state;
    }

    public function deleteState(SagaState $state, string $sagaHandlerClass): void
    {
        if (!is_a($sagaHandlerClass, Saga::class, true)) {
            throw new \InvalidArgumentException('Argument $sagaHandlerClass must extends '.Saga::class);
        }

        foreach ($this->states as $key => $persistedState) {
            if ($persistedState->id()->equals($state->id())) {
                unset($this->states[$key]);
            }
        }
    }

    public function transactional(callable $callback): mixed
    {
        return ($callback)();
    }
}
