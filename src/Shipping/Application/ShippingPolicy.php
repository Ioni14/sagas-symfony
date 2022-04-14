<?php

namespace Shipping\Application;

use Billing\Domain\Event\OrderBilled;
use Doctrine\DBAL\Connection;
use Sales\Domain\Event\OrderPlaced;
use Shared\Application\Saga;
use Shared\Application\SagaContext;
use Shared\Application\SagaState;
use Shipping\Domain\Command\ShipOrder;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

/**
 * An order is shipped when it is both accepted and billed.
 */
class ShippingPolicy extends Saga
{
    public function __construct(
        protected MessageBusInterface $commandBus,
        protected Connection $dbConnection,
    ) {
        parent::__construct(ShippingPolicyState::class);
    }

    public static function getHandledMessages(): iterable
    {
        return [OrderBilled::class, OrderPlaced::class];
    }

    protected function handleOrderBilled(OrderBilled $event, ShippingPolicyState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('Received OrderBilled, orderId={orderId}', [
            'orderId' => $event->orderId,
        ]);

        $state->orderBilled = true;

        $this->processOrder($state, $sagaContext);
    }

    protected function handleOrderPlaced(OrderPlaced $event, ShippingPolicyState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('Received OrderPlaced, orderId={orderId}', [
            'orderId' => $event->orderId,
        ]);

        $state->orderPlaced = true;

        $this->processOrder($state, $sagaContext);
    }

    private function processOrder(ShippingPolicyState $state, SagaContext $sagaContext): void
    {
        if ($state->orderPlaced && $state->orderBilled) {
            $this->commandBus->dispatch(new ShipOrder($state->orderId->toRfc4122()));
            $sagaContext->markAsCompleted();
        }
    }

    protected function canStartSaga(object $message): bool
    {
        return $message instanceof OrderPlaced || $message instanceof OrderBilled;
    }

    protected function findState(object $message, ?Ulid $sagaId): ?ShippingPolicyState
    {
        if ($message instanceof OrderPlaced || $message instanceof OrderBilled) {
            $row = $this->dbConnection->fetchAssociative('SELECT * FROM shipping_policy_state WHERE correlation_order_id = :order_id', [
                'order_id' => Ulid::fromString($message->orderId)->toBinary(),
            ]);
        } else {
            // TODO : not configured mapping
            $row = $this->dbConnection->fetchAssociative('SELECT * FROM shipping_policy_state WHERE id = :id', [
                'id' => $sagaId?->toBinary(),
            ]);
        }
        if (!$row) {
            return null;
        }

        return ShippingPolicyState::fromRow($row);
    }

    protected function saveState(SagaState $state): void
    {
        $this->dbConnection->executeStatement(<<<SQL
                INSERT INTO shipping_policy_state (id, correlation_order_id, state)
                VALUES (:id, :correlation_order_id, :state) ON DUPLICATE KEY UPDATE state = :state
            SQL, $state->toRow());
    }

    protected function deleteState(SagaState $state): void
    {
        $this->dbConnection->delete('shipping_policy_state', [
            'id' => $state->id->toBinary(),
        ]);
    }
}
