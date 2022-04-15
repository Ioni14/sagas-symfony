<?php

namespace Shipping\Application;

use Billing\Domain\Event\OrderBilled;
use Sales\Domain\Event\OrderPlaced;
use Shared\Application\Saga;
use Shared\Application\SagaContext;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shared\Application\SagaProviderInterface;
use Shipping\Domain\Command\ShipOrder;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * An order is shipped when it is both accepted and billed.
 */
class ShippingPolicy extends Saga
{
    public function __construct(
        protected MessageBusInterface $commandBus,
        SagaProviderInterface $sagaProvider, // ou directement une implémentation spécifique
    ) {
        parent::__construct(ShippingPolicyState::class, $sagaProvider);
    }

    protected static function configureMapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('orderId')
            ->messageCorrelationIdField(OrderPlaced::class, 'orderId')
            ->messageCorrelationIdField(OrderBilled::class, 'orderId')
            ->build();
    }

    protected static function getHandleMessages(): array
    {
        return [OrderBilled::class, OrderPlaced::class];
    }

    protected function handleOrderBilled(OrderBilled $event, ShippingPolicyState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('Received OrderBilled, orderId={orderId}', [
            'orderId' => $event->orderId->toRfc4122(),
        ]);

        $state->orderBilled = true;

        $this->processOrder($state, $sagaContext);
    }

    protected function handleOrderPlaced(OrderPlaced $event, ShippingPolicyState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('Received OrderPlaced, orderId={orderId}', [
            'orderId' => $event->orderId->toRfc4122(),
        ]);

        $state->orderPlaced = true;

        $this->processOrder($state, $sagaContext);
    }

    private function processOrder(ShippingPolicyState $state, SagaContext $sagaContext): void
    {
        if ($state->orderPlaced && $state->orderBilled) {
            $this->commandBus->dispatch(new ShipOrder($state->orderId));
            $sagaContext->markAsCompleted();
        }
    }

    protected function canStartSaga(object $message): bool
    {
        return $message instanceof OrderPlaced || $message instanceof OrderBilled;
    }
}
