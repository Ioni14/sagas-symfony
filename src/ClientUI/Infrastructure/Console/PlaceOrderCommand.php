<?php

namespace ClientUI\Infrastructure\Console;

use Psr\Log\LoggerInterface;
use Sales\Domain\Command\PlaceOrder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

#[AsCommand('app:place-order')]
class PlaceOrderCommand extends Command
{
    public function __construct(
        private LoggerInterface $logger,
        private MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = new PlaceOrder((new Ulid())->toRfc4122());

        $this->logger->info('Sending PlaceOrder command, orderId={orderId}', [
            'orderId' => $command->getOrderId(),
        ]);
        $this->commandBus->dispatch($command);

        return self::SUCCESS;
    }
}
