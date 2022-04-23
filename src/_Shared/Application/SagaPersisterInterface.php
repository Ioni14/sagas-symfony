<?php

namespace Shared\Application;

use Symfony\Component\Uid\AbstractUid;

interface SagaPersisterInterface
{
    public function findStateByCorrelationId(object $message, string $stateClass, SagaMapper $sagaMapper): ?SagaState;

    public function findStateBySagaId(AbstractUid $sagaId, string $stateClass, SagaMapper $sagaMapper): ?SagaState;

    public function saveState(SagaState $state): void;

    public function deleteState(SagaState $state): void;

    public function transactional(callable $callback): mixed;
}
