<?php

namespace Sales\Application;

use Sales\Domain\Command\CancelOrder;
use Sales\Domain\Command\PlaceOrder;
use Sales\Domain\Event\OrderPlaced;
use Shared\Application\Saga;
use Shared\Application\SagaContext;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shared\Application\SagaProviderInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class BuyersRemorsePolicy extends Saga
{
    public function __construct(
        private MessageBusInterface $eventBus,
        SagaProviderInterface $sagaProvider
    ) {
        parent::__construct(BuyersRemorseState::class, $sagaProvider);
    }

    protected static function configureMapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('orderId')
            ->messageCorrelationIdField(PlaceOrder::class, 'orderId')
            ->messageCorrelationIdField(CancelOrder::class, 'orderId')
            ->build();
    }

    /**
     * TODO : possible de déduire ça dans un CompilerPass pour Messenger ?
     * (e.g. en se basant sur le type du premier argument des méthodes "handle*").
     *
     * ou Attribute les méthodes qu'on veut mettre comme handler
     */
    protected static function getHandleMessages(): array
    {
        return [PlaceOrder::class, CancelOrder::class];
    }

    protected static function getTimeoutMessages(): array
    {
        return [BuyersRemorseIsOver::class];
    }

    protected function handlePlaceOrder(PlaceOrder $command, BuyersRemorseState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('Received PlaceOrder, orderId={orderId}', [
            'orderId' => $command->orderId->toRfc4122(),
        ]);

        // on s'envoie un message à nous-mêmes dans le futur
        $this->timeout($this->eventBus, $state, \DateInterval::createFromDateString('5 sec'), new BuyersRemorseIsOver());
    }

    protected function handleBuyersRemorseIsOver(BuyersRemorseIsOver $timeout, BuyersRemorseState $state, SagaContext $sagaContext): void
    {
        $this->logger->info('Cooling down period for order {orderId}', [
            'orderId' => $state->orderId->toRfc4122(),
        ]);

        // business logic for placing the order

        $this->eventBus->dispatch(new OrderPlaced($state->orderId));

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

    protected function canStartSaga(object $message): bool
    {
        return $message instanceof PlaceOrder;
    }
}
