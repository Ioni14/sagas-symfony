<?php

namespace Shipping\Application;

use Doctrine\DBAL\Connection;
use Shared\Application\Saga;
use Shared\Application\SagaHandler;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shipping\Application\Command\ShipWithAlpine;
use Shipping\Application\Command\ShipWithMaple;
use Shipping\Application\Event\ShipmentAcceptedByAlpine;
use Shipping\Application\Event\ShipmentAcceptedByMaple;
use Shipping\Application\Event\ShipmentFailed;
use Shipping\Application\Timeout\ShippingEscalation;
use Shipping\Domain\Command\ShipOrder;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Try to ship to 3rd-party Maple Shipping Service,
 * Otherwise try to ship to 3rd-party Alpine Service,
 * Otherwise Fail.
 * @implements Saga<ShipOrderState>
 */
class ShipOrderWorkflow extends Saga
{
    public function __construct(
        protected MessageBusInterface $commandBus,
        protected MessageBusInterface $eventBus,
        protected Connection $dbConnection,
    ) {
        parent::__construct();
    }

    public static function stateClass(): string
    {
        return ShipOrderState::class;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('orderId')
            ->messageCorrelationIdField(ShipOrder::class, 'orderId')
            ->messageCorrelationIdField(ShipmentAcceptedByMaple::class, 'orderId')
            ->messageCorrelationIdField(ShipmentAcceptedByAlpine::class, 'orderId')
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [ShipOrder::class, ShipmentAcceptedByMaple::class, ShipmentAcceptedByAlpine::class, ShippingEscalation::class];
    }

    public static function canStartSaga(object $message): bool
    {
        return $message instanceof ShipOrder;
    }

    #[SagaHandler]
    protected function handleShipOrder(ShipOrder $command): void
    {
        $this->logger->info('ShipOrderWorkflow for Order {orderId} - Trying Maple first.', [
            'orderId' => $command->orderId->toRfc4122(),
        ]);

        // Execute order to ship with Maple
//        $this->publish($this->commandBus, $state, new ShipWithMaple($command->orderId)); // TODO : if use Reply
        $this->commandBus->dispatch(new ShipWithMaple($command->orderId));

        // Add timeout to escalate if Maple did not ship in time.
        $this->timeout($this->commandBus, \DateInterval::createFromDateString('20 sec'), new ShippingEscalation());
    }

    #[SagaHandler]
    protected function handleAcceptedByMaple(ShipmentAcceptedByMaple $event): void
    {
        if ($this->state->shipmentOrderSentToAlpine) {
            // too late, maintenant on deal avec Alpine
            return;
        }

        $this->logger->info('Order [{orderId}] - Successfully shipped with Maple', [
            'orderId' => $event->orderId->toRfc4122(),
        ]);

        $this->state->shipmentAcceptedByMaple = true;

        // TODO : test quand il y a au "même moment" (persist state...) : ShipmentAcceptedByMaple ET ShippingEscalation
        $this->markAsCompleted();
    }

    #[SagaHandler]
    protected function handleShipmentAcceptedByAlpine(ShipmentAcceptedByAlpine $event): void
    {
        $this->logger->info('Order [{orderId}] - Successfully shipped with Alpine', [
            'orderId' => $event->orderId->toRfc4122(),
        ]);

        $this->state->shipmentAcceptedByAlpine = true;

        // maybe publish ShipmentAccepted ?

        // TODO : test quand il y a au "même moment" (persist state...) : ShipmentAcceptedByMaple ET ShippingEscalation
        $this->markAsCompleted();
    }

    #[SagaHandler]
    protected function handleShippingEscalation(ShippingEscalation $timeout): void
    {
        if ($this->state->shipmentAcceptedByMaple) {
            return;
        }

        if (!$this->state->shipmentOrderSentToAlpine) {
            $this->logger->info("Order [{orderId}] - We didn't receive answer from Maple, let's try Alpine.", [
                'orderId' => $this->state->orderId,
            ]);

            $this->state->shipmentOrderSentToAlpine = true;
//            $this->publish($this->commandBus, $this->state, new ShipWithAlpine($this->state->orderId->toRfc4122())); // TODO : if use Reply
            $this->commandBus->dispatch(new ShipWithAlpine($this->state->orderId));
            $this->timeout($this->commandBus, \DateInterval::createFromDateString('20 sec'), new ShippingEscalation());

            return;
        }

        if (!$this->state->shipmentAcceptedByAlpine) {
            $this->logger->warning('Order [{orderId}] - No answer from Maple/Alpine. We need to escalate!', [
                'orderId' => $this->state->orderId,
            ]);

            $this->eventBus->dispatch(new ShipmentFailed($this->state->orderId));

            $this->markAsCompleted();
        }
    }
}
