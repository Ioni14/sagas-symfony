<?php

namespace Shared\Application;

use Symfony\Component\Uid\AbstractUid;

interface SagaPersisterInterface
{
    /**
     * @param class-string<Saga> $sagaHandlerClass
     */
    public function setup(string $sagaHandlerClass): void;

    /**
     * @param class-string<Saga> $sagaHandlerClass
     */
    public function findStateBySagaId(AbstractUid $sagaId, string $sagaHandlerClass): ?SagaState;

    /**
     * @param class-string<Saga> $sagaHandlerClass
     */
    public function findStateByCorrelationId(object $message, string $sagaHandlerClass): ?SagaState;

    /**
     * @param class-string<Saga> $sagaHandlerClass
     */
    public function saveState(SagaState $state, object $message, string $sagaHandlerClass): void;

    /**
     * @param class-string<Saga> $sagaHandlerClass
     */
    public function deleteState(SagaState $state, string $sagaHandlerClass): void;

    public function transactional(callable $callback): mixed;
}
