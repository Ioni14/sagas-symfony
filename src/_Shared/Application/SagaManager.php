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
        $this->sagas[md5(serialize($message))][$saga::class] = $saga;
    }

    public function __invoke(object $message): void
    {
        foreach ($this->sagas[md5(serialize($message))] ?? [] as $saga) {
            ($saga)($message);
        }
    }

    /**
     * @return Saga[]
     */
    public function getSagaHandlersFor(object $message): iterable
    {
        return $this->sagas[md5(serialize($message))] ?? [];
    }

    public function reset(): void
    {
        $this->sagas = [];
    }
}
