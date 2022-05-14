<?php

namespace Sales\Application;

use Sales\Domain\Command\CancelOrder;
use Sales\Domain\Command\PlaceOrder;
use Sales\Domain\Event\OrderPlaced;
use Shared\Application\Saga;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements Saga<BuyersRemorseState>
 */
class BuyersRemorsePolicy extends Saga
{
    public function __construct(
        private MessageBusInterface $eventBus,
    ) {
    }

    public static function stateClass(): string
    {
        return BuyersRemorseState::class;
    }

    public static function mapping(): SagaMapper
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
    public static function getHandledMessages(): array
    {
        return [PlaceOrder::class, CancelOrder::class, BuyersRemorseIsOver::class];
    }

    public static function canStartSaga(object $message): bool
    {
        return $message instanceof PlaceOrder;
    }

    protected function handlePlaceOrder(PlaceOrder $command): void
    {
        $this->logger->info('Received PlaceOrder, orderId={orderId}', [
            'orderId' => $command->orderId->toRfc4122(),
        ]);

        // systemic exception
//        throw new \Exception("Systemic error");

        // transient exception
//        if (mt_rand(0, 5) === 0) {
//            throw new \Exception("Transient error");
//        }

        // on s'envoie un message à nous-mêmes dans le futur
//        $this->timeout($this->eventBus, $state, \DateInterval::createFromDateString('5 sec'), new BuyersRemorseIsOver());
        $this->eventBus->dispatch(new OrderPlaced($this->state->orderId));

        $this->markAsCompleted();
    }

    protected function handleBuyersRemorseIsOver(BuyersRemorseIsOver $timeout): void
    {
        $this->logger->info('Cooling down period for order {orderId}', [
            'orderId' => $this->state->orderId->toRfc4122(),
        ]);

        // business logic for placing the order

        $this->eventBus->dispatch(new OrderPlaced($this->state->orderId));

        $this->markAsCompleted();
    }

    protected function handleCancelOrder(CancelOrder $command): void
    {
        $this->logger->info('Order {orderId} was cancelled.', [
            'orderId' => $command->orderId,
        ]);

        // Possibly publish an OrderCancelled event?

        $this->markAsCompleted(); // va ignorer un éventuel BuyersRemorseIsOver
    }
}
