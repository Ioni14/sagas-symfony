<?php

namespace Billing\Application;

use Billing\Domain\Event\OrderBilled;
use Psr\Log\LoggerInterface;
use Sales\Domain\Event\OrderPlaced;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'event.bus')]
class OrderPlacedHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(OrderPlaced $event): void
    {
        $this->logger->info("Received OrderPlaced, orderId={orderId} - Charging credit card...", [
            'orderId' => $event->orderId->toRfc4122(),
        ]);

        // charge credit card ...

        $this->eventBus->dispatch(new OrderBilled($event->orderId));
    }
}
