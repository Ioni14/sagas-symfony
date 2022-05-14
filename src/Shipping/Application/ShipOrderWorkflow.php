<?php

namespace Shipping\Application;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Shared\Application\SagaContext;
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
 * @implements SagaInterface<ShipOrderState>
 */
class ShipOrderWorkflow implements SagaInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use SagaPublishTrait;

    public function __construct(
        protected MessageBusInterface $commandBus,
        protected MessageBusInterface $eventBus,
        protected Connection $dbConnection,
    ) {
        $this->logger = new NullLogger();
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

    /**
     * @param SagaContext<ShipOrderState> $context
     */
    #[SagaHandler]
    public function handleShipOrder(ShipOrder $command, SagaContext $context): void
    {
        $this->logger->info('ShipOrderWorkflow for Order {orderId} - Trying Maple first.', [
            'orderId' => $command->orderId->toRfc4122(),
        ]);

        // Execute order to ship with Maple
//        $this->publish($this->commandBus, $state, new ShipWithMaple($command->orderId)); // TODO : if use Reply
        $this->commandBus->dispatch(new ShipWithMaple($command->orderId));

        // Add timeout to escalate if Maple did not ship in time.
        $this->timeout($this->commandBus, \DateInterval::createFromDateString('20 sec'), new ShippingEscalation(), $context);
    }

    /**
     * @param SagaContext<ShipOrderState> $context
     */
    #[SagaHandler]
    public function handleAcceptedByMaple(ShipmentAcceptedByMaple $event, SagaContext $context): void
    {
        if ($context->state->shipmentOrderSentToAlpine) {
            // too late, maintenant on deal avec Alpine
            return;
        }

        $this->logger->info('Order [{orderId}] - Successfully shipped with Maple', [
            'orderId' => $event->orderId->toRfc4122(),
        ]);

        $context->state->shipmentAcceptedByMaple = true;

        // TODO : test quand il y a au "même moment" (persist state...) : ShipmentAcceptedByMaple ET ShippingEscalation
        $context->markAsCompleted();
    }

    /**
     * @param SagaContext<ShipOrderState> $context
     */
    #[SagaHandler]
    public function handleShipmentAcceptedByAlpine(ShipmentAcceptedByAlpine $event, SagaContext $context): void
    {
        $this->logger->info('Order [{orderId}] - Successfully shipped with Alpine', [
            'orderId' => $event->orderId->toRfc4122(),
        ]);

        $context->state->shipmentAcceptedByAlpine = true;

        // maybe publish ShipmentAccepted ?

        // TODO : test quand il y a au "même moment" (persist state...) : ShipmentAcceptedByMaple ET ShippingEscalation
        $context->markAsCompleted();
    }

    /**
     * @param SagaContext<ShipOrderState> $context
     */
    #[SagaHandler]
    public function handleShippingEscalation(ShippingEscalation $timeout, SagaContext $context): void
    {
        if ($context->state->shipmentAcceptedByMaple) {
            return;
        }

        if (!$context->state->shipmentOrderSentToAlpine) {
            $this->logger->info("Order [{orderId}] - We didn't receive answer from Maple, let's try Alpine.", [
                'orderId' => $context->state->orderId,
            ]);

            $context->state->shipmentOrderSentToAlpine = true;
//            $this->publish($this->commandBus, $this->state, new ShipWithAlpine($this->state->orderId->toRfc4122())); // TODO : if use Reply
            $this->commandBus->dispatch(new ShipWithAlpine($context->state->orderId));
            $this->timeout($this->commandBus, \DateInterval::createFromDateString('20 sec'), new ShippingEscalation(), $context);

            return;
        }

        if (!$context->state->shipmentAcceptedByAlpine) {
            $this->logger->warning('Order [{orderId}] - No answer from Maple/Alpine. We need to escalate!', [
                'orderId' => $context->state->orderId,
            ]);

            $this->eventBus->dispatch(new ShipmentFailed($context->state->orderId));

            $context->markAsCompleted();
        }
    }
}
