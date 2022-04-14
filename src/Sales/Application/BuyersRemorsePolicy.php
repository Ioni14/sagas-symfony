<?php

namespace Sales\Application;

use Doctrine\DBAL\Connection;
use Sales\Domain\Command\CancelOrder;
use Sales\Domain\Command\PlaceOrder;
use Sales\Domain\Event\OrderPlaced;
use Shared\Application\Saga;
use Shared\Application\SagaContext;
use Shared\Application\SagaState;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

class BuyersRemorsePolicy extends Saga
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private Connection $dbConnection,
    ) {
        parent::__construct(BuyersRemorseState::class);
    }

    /**
     * TODO : possible de déduire ça dans un CompilerPass pour Messenger ?
     * (e.g. en se basant sur le type du premier argument des méthodes "handle*").
     *
     * ou Attribute les méthodes qu'on veut mettre comme handler
     */
    public static function getHandledMessages(): iterable
    {
        return [ /* Message */ PlaceOrder::class, /* Timeout */ BuyersRemorseIsOver::class, /* Message */ CancelOrder::class];
    }

    protected function handlePlaceOrder(PlaceOrder $command, BuyersRemorseState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('Received PlaceOrder, orderId={orderId}', [
            'orderId' => $command->orderId,
        ]);

        // on s'envoie un message à nous-mêmes dans le futur
        $this->timeout($this->eventBus, $state, 5_000, new BuyersRemorseIsOver());
    }

    protected function handleBuyersRemorseIsOver(BuyersRemorseIsOver $timeout, BuyersRemorseState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('Cooling down period for order {orderId}', [
            'orderId' => $state->orderId->toRfc4122(),
        ]);

        // business logic for placing the order

        $this->eventBus->dispatch(new OrderPlaced($state->orderId->toRfc4122()));

        $sagaContext->markAsCompleted();
    }

    protected function handleCancelOrder(CancelOrder $command, BuyersRemorseState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('Order {orderId} was cancelled.', [
            'orderId' => $command->orderId,
        ]);

        // Possibly publish an OrderCancelled event?

        $sagaContext->markAsCompleted(); // va ignorer un éventuel BuyersRemorseIsOver
    }

    protected function findState(object $message, ?Ulid $sagaId): ?BuyersRemorseState
    {
        if ($message instanceof PlaceOrder || $message instanceof CancelOrder) {
            $row = $this->dbConnection->fetchAssociative('SELECT * FROM buyers_remorse_state WHERE correlation_order_id = :order_id', [
                'order_id' => Ulid::fromString($message->orderId)->toBinary(),
            ]);
        } else {
            // TODO : not configured mapping
            $row = $this->dbConnection->fetchAssociative('SELECT * FROM buyers_remorse_state WHERE id = :id', [
                'id' => $sagaId?->toBinary(),
            ]);
        }
        if (!$row) {
            return null;
        }

        return BuyersRemorseState::fromRow($row);
    }

    /**
     * @param BuyersRemorseState $state
     */
    protected function saveState(SagaState $state): void
    {
        $this->dbConnection->executeStatement(<<<SQL
                INSERT INTO buyers_remorse_state (id, correlation_order_id, state)
                VALUES (:id, :correlation_order_id, :state) ON DUPLICATE KEY UPDATE correlation_order_id = :correlation_order_id,  state = :state
            SQL, $state->toRow());
    }

    protected function deleteState(SagaState $state): void
    {
        $this->dbConnection->delete('buyers_remorse_state', [
            'id' => $state->id?->toBinary(),
        ]);
    }

    protected function canStartSaga(object $message): bool
    {
        return $message instanceof PlaceOrder;
    }
}
