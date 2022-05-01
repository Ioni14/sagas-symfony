<?php

namespace Tests\Acceptance;

use Shared\Application\SagaPersisterInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;
use Tests\Acceptance\Saga\Message\OneHandlerFirstMessage;
use Tests\Acceptance\Saga\Message\TwoHandlerFirstMessage;
use Tests\Acceptance\Saga\OneHandlerSaga;
use Tests\Acceptance\Saga\State\OneHandlerState;
use Tests\Acceptance\Saga\State\TwoHandlerState;
use Tests\Acceptance\Saga\TwoHandlersSaga;

class SagaTest extends KernelTestCase
{
    protected function setUp(): void
    {
        static::bootKernel(['environment' => 'test_acceptance']);
    }

    public function test_saga_with_one_handler_should_start_and_complete(): void
    {
        $message = new OneHandlerFirstMessage('foobar');
        static::get(MessageBusInterface::class)->dispatch($message);

        $commandTester = static::createCommandTester('messenger:consume');
        $commandTester->execute(['receivers' => ['memory'], '--limit' => '1', '--time-limit' => '3', '--no-reset' => true]);
        static::assertSame(0, $commandTester->getStatusCode());

        $state = static::get(SagaPersisterInterface::class)->findStateByCorrelationId($message, OneHandlerSaga::class);
        static::assertNull($state, 'State should be deleted since Saga has been completed.');
    }

    public function test_saga_with_two_successive_handlers_should_start_and_complete(): void
    {
        $message = new TwoHandlerFirstMessage(10);
        static::get(MessageBusInterface::class)->dispatch($message);

        $commandTester = static::createCommandTester('messenger:consume');
        $commandTester->execute(['receivers' => ['memory'], '--limit' => '1', '--time-limit' => '3', '--no-reset' => true]);
        static::assertSame(0, $commandTester->getStatusCode());

        /** @var TwoHandlerState $state */
        $state = static::get(SagaPersisterInterface::class)->findStateByCorrelationId($message, TwoHandlersSaga::class);
        static::assertNotNull($state, 'State should not be deleted since Saga has not been completed.');
        static::assertSame(10, $state->myId);

        $commandTester = static::createCommandTester('messenger:consume');
        $commandTester->execute(['receivers' => ['memory'], '--limit' => '1', '--time-limit' => '3', '--no-reset' => true]);
        static::assertSame(0, $commandTester->getStatusCode());

        $state = static::get(SagaPersisterInterface::class)->findStateByCorrelationId($message, TwoHandlersSaga::class);
        static::assertNull($state, 'State should be deleted since Saga has been completed.');
    }

    /**
     * @template T
     * @param class-string<T> $serviceId
     * @return T|null
     */
    protected static function get(string $serviceId): ?object
    {
        return static::getContainer()->get($serviceId);
    }

    protected static function createCommandTester(string $commandName): CommandTester
    {
        $app = new Application(static::$kernel);
        $command = $app->find($commandName);

        return new CommandTester($command);
    }
}
