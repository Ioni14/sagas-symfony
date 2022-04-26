<?php

namespace Shared\Infrastructure\Command;

use Shared\Application\Saga;
use Shared\Application\SagaPersisterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'soa:saga:setup-tables')]
class SetupSagaTablesCommand extends Command
{
    /**
     * @param Saga[] $sagaHandlerPrototypes
     */
    public function __construct(
        private iterable $sagaHandlerPrototypes,
        private SagaPersisterInterface $sagaPersister,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->sagaHandlerPrototypes as $sagaHandler) {
            $output->writeln('<info>Setup '.$sagaHandler::class.'...</info>');
            $this->sagaPersister->setup($sagaHandler::class);
        }
        $output->writeln('<info>Done.</info>');

        return self::SUCCESS;
    }
}
