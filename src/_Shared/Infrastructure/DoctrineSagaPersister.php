<?php

namespace Shared\Infrastructure;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Application\SagaMapper;
use Shared\Application\SagaPersisterInterface;
use Shared\Application\SagaState;
use Symfony\Component\Uid\AbstractUid;

class DoctrineSagaPersister implements SagaPersisterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findStateByCorrelationId(object $message, string $stateClass, SagaMapper $sagaMapper): ?SagaState
    {
        $correlationField = $sagaMapper->messageCorrelationIdField($message::class);

        return $this->entityManager->getRepository($stateClass)->createQueryBuilder('st')
            ->where('st.' . $correlationField . ' = :correlationId')
            ->setParameter('correlationId', $message->$correlationField)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

//        // TODO : PessimisticLock
//        return $this->entityManager->getRepository($stateClass)->findOneBy([
//            $correlationField => $message->{$correlationField},
//        ]);
    }

    public function findStateBySagaId(AbstractUid $sagaId, string $stateClass, SagaMapper $sagaMapper): ?SagaState
    {
        return $this->entityManager->getRepository($stateClass)->createQueryBuilder('st')
            ->where('st.id = :id')
            ->setParameter('id', $sagaId)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

//        // TODO : PessimisticLock
//        return $this->entityManager->getRepository($stateClass)->findOneBy([
//            'id' => $sagaId,
//        ]);
    }

    public function saveState(SagaState $state): void
    {
        $this->entityManager->persist($state);
//        $this->entityManager->flush();
    }

    public function deleteState(SagaState $state): void
    {
        $this->entityManager->remove($state);
//        $this->entityManager->flush();
    }

    public function transactional(callable $callback): mixed
    {
//        $this->entityManager->getConnection()->setTransactionIsolation();
        return $this->entityManager->wrapInTransaction($callback);
    }
}
