<?php

namespace ClientUI\Infrastructure\Console;

use Psr\Log\LoggerInterface;
use Sales\Domain\Command\PlaceOrder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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

    protected function configure(): void
    {
        $this->addArgument('order_id', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $orderId = $input->getArgument('order_id') ?? Ulid::generate();

        $command = new PlaceOrder(Ulid::fromString($orderId));

        $this->logger->info('Sending PlaceOrder command, orderId={orderId}', [
            'orderId' => $command->orderId->toRfc4122(),
        ]);

        $this->commandBus->dispatch($command);

        return self::SUCCESS;
    }
}
