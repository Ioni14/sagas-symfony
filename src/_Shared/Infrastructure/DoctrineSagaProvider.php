<?php

namespace Shared\Infrastructure;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Application\SagaMapper;
use Shared\Application\SagaProviderInterface;
use Shared\Application\SagaState;
use Symfony\Component\Uid\AbstractUid;

class DoctrineSagaProvider implements SagaProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findStateByCorrelationId(object $message, string $stateClass, SagaMapper $sagaMapper): ?SagaState
    {
        $correlationField = $sagaMapper->messageCorrelationIdField($message::class);

        return $this->entityManager->getRepository($stateClass)->findOneBy([
            $correlationField => $message->{$correlationField},
        ]);
    }

    public function findStateBySagaId(AbstractUid $sagaId, string $stateClass, SagaMapper $sagaMapper): ?SagaState
    {
        return $this->entityManager->getRepository($stateClass)->findOneBy([
            'id' => $sagaId,
        ]);
    }

    public function saveState(SagaState $state): void
    {
        $this->entityManager->persist($state);
        $this->entityManager->flush();
    }

    public function deleteState(SagaState $state): void
    {
        $this->entityManager->remove($state);
        $this->entityManager->flush();
    }
}
