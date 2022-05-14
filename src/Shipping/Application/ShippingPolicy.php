<?php

namespace Shipping\Application;

use Billing\Domain\Event\OrderBilled;
use Sales\Domain\Event\OrderPlaced;
use Shared\Application\Saga;
use Shared\Application\SagaHandler;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shipping\Domain\Command\ShipOrder;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * An order is shipped when it is both accepted and billed.
 * @implements SagaInterface<ShippingPolicyState>
 */
class ShippingPolicy extends Saga
{
    public function __construct(
        protected MessageBusInterface $commandBus,
    ) {
    }

    public static function stateClass(): string
    {
        return ShippingPolicyState::class;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('orderId')
            ->messageCorrelationIdField(OrderPlaced::class, 'orderId')
            ->messageCorrelationIdField(OrderBilled::class, 'orderId')
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [OrderBilled::class, OrderPlaced::class];
    }

    public static function canStartSaga(object $message): bool
    {
        return $message instanceof OrderPlaced || $message instanceof OrderBilled;
    }

    #[SagaHandler]
    public function handleOrderBilled(OrderBilled $event): void
    {
        $this->logger->info('Received OrderBilled, orderId={orderId}', [
            'orderId' => $event->orderId->toRfc4122(),
        ]);

        $this->state->orderBilled = true;

        $this->processOrder();
    }

    #[SagaHandler]
    public function handleOrderPlaced(OrderPlaced $event): void
    {
        $this->logger->info('Received OrderPlaced, orderId={orderId}', [
            'orderId' => $event->orderId->toRfc4122(),
        ]);

        $this->state->orderPlaced = true;

        $this->processOrder();
    }

    private function processOrder(): void
    {
        if ($this->state->orderPlaced && $this->state->orderBilled) {
            $this->commandBus->dispatch(new ShipOrder($this->state->orderId));
            $this->markAsCompleted();
        }
    }
}
