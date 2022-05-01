<?php

namespace Shared\Application;

use Symfony\Contracts\Service\ResetInterface;

class SagaManager implements ResetInterface
{
    /**
     * @var Saga[][]
     */
    private array $sagas = [];

    public function addSaga(object $message, Saga $saga): void
    {
        // new Saga per couple message/handledSaga
        $this->sagas[spl_object_hash($message)][$saga::class] = $saga;
    }

    // TODO : what if __invoke($message) is cloned from addSaga($message) ? damned :(
    public function __invoke(object $message): void
    {
        foreach ($this->sagas[spl_object_hash($message)] ?? [] as $saga) {
            ($saga)($message);
        }
    }

    /**
     * @return Saga[]
     */
    public function getSagaHandlersFor(object $message): iterable
    {
        return $this->sagas[spl_object_hash($message)] ?? [];
    }

    public function reset(): void
    {
        $this->sagas = [];
    }
}
