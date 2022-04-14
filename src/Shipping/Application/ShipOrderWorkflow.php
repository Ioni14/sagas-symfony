<?php

namespace Shipping\Application;

use Doctrine\DBAL\Connection;
use Shared\Application\Saga;
use Shared\Application\SagaContext;
use Shared\Application\SagaState;
use Shipping\Application\Command\ShipWithAlpine;
use Shipping\Application\Command\ShipWithMaple;
use Shipping\Application\Event\ShipmentAcceptedByAlpine;
use Shipping\Application\Event\ShipmentAcceptedByMaple;
use Shipping\Application\Event\ShipmentFailed;
use Shipping\Application\Timeout\ShippingEscalation;
use Shipping\Domain\Command\ShipOrder;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Try to ship to 3rd-party Maple Shipping Service,
 * Otherwise try to ship to 3rd-party Alpine Service,
 * Otherwise Fail.
 */
class ShipOrderWorkflow extends Saga
{
    public function __construct(
        protected MessageBusInterface $commandBus,
        protected MessageBusInterface $eventBus,
        protected Connection $dbConnection,
    ) {
        parent::__construct(ShipOrderState::class);
    }

    public static function getHandledMessages(): iterable
    {
        return [ShipOrder::class, ShippingEscalation::class, ShipmentAcceptedByMaple::class, ShipmentAcceptedByAlpine::class];
    }

    protected function handleShipOrder(ShipOrder $command, ShipOrderState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('ShipOrderWorkflow for Order {orderId} - Trying Maple first.', [
            'orderId' => $command->orderId,
        ]);

        // Execute order to ship with Maple
//        $this->publish($this->commandBus, $state, new ShipWithMaple($command->orderId)); // TODO : if use Reply
        $this->commandBus->dispatch(new ShipWithMaple($command->orderId));

        // Add timeout to escalate if Maple did not ship in time.
        $this->timeout($this->commandBus, $state, \DateInterval::createFromDateString('20 sec'), new ShippingEscalation());
    }

    protected function handleShipmentAcceptedByMaple(ShipmentAcceptedByMaple $event, ShipOrderState $state, SagaContext $sagaContext): void
    {
        if ($state->shipmentOrderSentToAlpine) {
            // too late, maintenant on deal avec Alpine
            return;
        }

        $this->logger->info('Order [{orderId}] - Successfully shipped with Maple', [
            'orderId' => $event->orderId,
        ]);

        $state->shipmentAcceptedByMaple = true;

        // TODO : test quand il y a au "même moment" (persist state...) : ShipmentAcceptedByMaple ET ShippingEscalation
        $sagaContext->markAsCompleted();
    }

    protected function handleShipmentAcceptedByAlpine(ShipmentAcceptedByAlpine $event, ShipOrderState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('Order [{orderId}] - Successfully shipped with Alpine', [
            'orderId' => $event->orderId,
        ]);

        $state->shipmentAcceptedByAlpine = true;

        // maybe publish ShipmentAccepted ?

        // TODO : test quand il y a au "même moment" (persist state...) : ShipmentAcceptedByMaple ET ShippingEscalation
        $sagaContext->markAsCompleted();
    }

    protected function handleShippingEscalation(ShippingEscalation $timeout, ShipOrderState $state, SagaContext $sagaContext): void
    {
        if ($state->shipmentAcceptedByMaple) {
            return;
        }

        if (!$state->shipmentOrderSentToAlpine) {
            $this->logger->info("Order [{orderId}] - We didn't receive answer from Maple, let's try Alpine.", [
                'orderId' => $state->orderId,
            ]);

            $state->shipmentOrderSentToAlpine = true;
//            $this->publish($this->commandBus, $state, new ShipWithAlpine($state->orderId->toRfc4122())); // TODO : if use Reply
            $this->commandBus->dispatch(new ShipWithAlpine($state->orderId->toRfc4122()));
            $this->timeout($this->commandBus, $state, \DateInterval::createFromDateString('20 sec'), new ShippingEscalation());

            return;
        }

        if (!$state->shipmentAcceptedByAlpine) {
            $this->logger->warning("Order [{orderId}] - No answer from Maple/Alpine. We need to escalate!", [
                'orderId' => $state->orderId,
            ]);

            $this->eventBus->dispatch(new ShipmentFailed($state->orderId));

            $sagaContext->markAsCompleted();
        }
    }

    protected function canStartSaga(object $message): bool
    {
        return $message instanceof ShipOrder;
    }

    protected function findState(object $message, ?Ulid $sagaId): ?ShipOrderState
    {
        if ($message instanceof ShipOrder || $message instanceof ShipmentAcceptedByMaple || $message instanceof ShipmentAcceptedByAlpine) {
            $row = $this->dbConnection->fetchAssociative('SELECT * FROM ship_order_state WHERE correlation_order_id = :order_id', [
                'order_id' => Ulid::fromString($message->orderId)->toBinary(),
            ]);
        } else {
            // TODO : not configured mapping
            $row = $this->dbConnection->fetchAssociative('SELECT * FROM ship_order_state WHERE id = :id', [
                'id' => $sagaId?->toBinary(),
            ]);
        }
        if (!$row) {
            return null;
        }

        return ShipOrderState::fromRow($row);
    }

    protected function saveState(SagaState $state): void
    {
        $this->dbConnection->executeStatement(<<<SQL
                INSERT INTO ship_order_state (id, correlation_order_id, state)
                VALUES (:id, :correlation_order_id, :state) ON DUPLICATE KEY UPDATE state = :state
            SQL, $state->toRow());
    }

    protected function deleteState(SagaState $state): void
    {
        $this->dbConnection->delete('ship_order_state', [
            'id' => $state->id->toBinary(),
        ]);
    }
}
