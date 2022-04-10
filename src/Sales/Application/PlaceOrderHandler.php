<?php

namespace Sales\Application;

use Psr\Log\LoggerInterface;
use Sales\Domain\Command\PlaceOrder;
use Sales\Domain\Event\OrderPlaced;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'command.bus')]
class PlaceOrderHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(PlaceOrder $command): void
    {
        $this->logger->info("Received PlaceOrder, orderId={orderId}", [
            'orderId' => $command->getOrderId(),
        ]);

        // This is normally where some business logic would occur

        // Uncomment to test throwing a systemic exception
        //throw new \Exception("BOOM");

        // Uncomment to test throwing a transient exception
        // if (mt_rand(0, 5) === 0)
        // {
        //     throw new \Exception("Oops");
        // }

        $this->eventBus->dispatch(new OrderPlaced($command->getOrderId()));
    }
}
