<?php

namespace Shipping\Application;

use Psr\Log\LoggerInterface;
use Shipping\Domain\Command\ShipOrder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class ShipOrderHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ShipOrder $command): void
    {
        $this->logger->info('Order {orderId} - successfully shipped', [
            'orderId' => $command->getOrderId(),
        ]);
    }
}
