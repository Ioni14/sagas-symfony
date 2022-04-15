<?php

namespace Shipping\Application;

use Doctrine\DBAL\Connection;
use Shared\Application\Saga;
use Shared\Application\SagaContext;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shared\Application\SagaProviderInterface;
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
 */
class ShipOrderWorkflow extends Saga
{
    public function __construct(
        protected MessageBusInterface $commandBus,
        protected MessageBusInterface $eventBus,
        protected Connection $dbConnection,
        SagaProviderInterface $sagaProvider
    ) {
        parent::__construct(ShipOrderState::class, $sagaProvider);
    }

    protected static function configureMapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('orderId')
            ->messageCorrelationIdField(ShipOrder::class, 'orderId')
            ->messageCorrelationIdField(ShipmentAcceptedByMaple::class, 'orderId')
            ->messageCorrelationIdField(ShipmentAcceptedByAlpine::class, 'orderId')
            ->build();
    }

    protected static function getHandleMessages(): array
    {
        return [ShipOrder::class, ShipmentAcceptedByMaple::class, ShipmentAcceptedByAlpine::class];
    }

    protected static function getTimeoutMessages(): array
    {
        return [ShippingEscalation::class];
    }

    protected function handleShipOrder(ShipOrder $command, ShipOrderState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('ShipOrderWorkflow for Order {orderId} - Trying Maple first.', [
            'orderId' => $command->orderId->toRfc4122(),
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
            'orderId' => $event->orderId->toRfc4122(),
        ]);

        $state->shipmentAcceptedByMaple = true;

        // TODO : test quand il y a au "même moment" (persist state...) : ShipmentAcceptedByMaple ET ShippingEscalation
        $sagaContext->markAsCompleted();
    }

    protected function handleShipmentAcceptedByAlpine(ShipmentAcceptedByAlpine $event, ShipOrderState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('Order [{orderId}] - Successfully shipped with Alpine', [
            'orderId' => $event->orderId->toRfc4122(),
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
            $this->commandBus->dispatch(new ShipWithAlpine($state->orderId));
            $this->timeout($this->commandBus, $state, \DateInterval::createFromDateString('20 sec'), new ShippingEscalation());

            return;
        }

        if (!$state->shipmentAcceptedByAlpine) {
            $this->logger->warning('Order [{orderId}] - No answer from Maple/Alpine. We need to escalate!', [
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
}
