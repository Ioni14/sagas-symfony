<?php

namespace Tests\Acceptance;

use Shared\Application\SagaHandler;
use Shared\Application\SagaPersistenceInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Tests\Acceptance\Saga\ImpossibleStateSaga;
use Tests\Acceptance\Saga\Message\BadMessageMappingMessage;
use Tests\Acceptance\Saga\Message\BadStateMappingMessage;
use Tests\Acceptance\Saga\Message\ImpossibleStateMessage;
use Tests\Acceptance\Saga\Message\NoHandlerMethodMessage;
use Tests\Acceptance\Saga\Message\OneHandlerFirstMessage;
use Tests\Acceptance\Saga\Message\TwoHandlerFirstMessage;
use Tests\Acceptance\Saga\NoHandlerMethodSaga;
use Tests\Acceptance\Saga\OneHandlerSaga;
use Tests\Acceptance\Saga\State\IntegerState;
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

        $state = static::get(SagaPersistenceInterface::class)->findStateByCorrelationId($message, OneHandlerSaga::class);
        static::assertNull($state, 'State should be deleted since Saga has been completed.');
    }

    public function test_saga_with_two_successive_handlers_should_start_and_complete(): void
    {
        $message = new TwoHandlerFirstMessage(10);
        static::get(MessageBusInterface::class)->dispatch($message);

        $commandTester = static::createCommandTester('messenger:consume');
        $commandTester->execute(['receivers' => ['memory'], '--limit' => '1', '--time-limit' => '3', '--no-reset' => true]);
        static::assertSame(0, $commandTester->getStatusCode());

        /** @var IntegerState $state */
        $state = static::get(SagaPersistenceInterface::class)->findStateByCorrelationId($message, TwoHandlersSaga::class);
        static::assertNotNull($state, 'State should not be deleted since Saga has not been completed.');
        static::assertSame(10, $state->myId);

        $commandTester = static::createCommandTester('messenger:consume');
        $commandTester->execute(['receivers' => ['memory'], '--limit' => '1', '--time-limit' => '3', '--no-reset' => true]);

        static::assertSame(0, $commandTester->getStatusCode());

        $state = static::get(SagaPersistenceInterface::class)->findStateByCorrelationId($message, TwoHandlersSaga::class);
        static::assertNull($state, 'State should be deleted since Saga has been completed.');
    }

    public function test_saga_should_raise_error_for_bad_state_mapping(): void
    {
        $message = new BadStateMappingMessage(10);
        static::get(MessageBusInterface::class)->dispatch($message);

        $commandTester = static::createCommandTester('messenger:consume');
        $commandTester->execute(['receivers' => ['memory'], '--limit' => '1', '--time-limit' => '3', '--no-reset' => true]);
        static::assertSame(0, $commandTester->getStatusCode());

        $failedMessage = static::getFailedMessage();
        static::assertEquals($message, $failedMessage->getMessage());
        static::assertFailedMessage($failedMessage, 'Saga state '.IntegerState::class.' does not have the mapped property notId. Please check your Saga mapping.');
    }

    public function test_saga_should_raise_error_for_bad_message_mapping(): void
    {
        $message = new BadMessageMappingMessage(10);
        static::get(MessageBusInterface::class)->dispatch($message);

        $commandTester = static::createCommandTester('messenger:consume');
        $commandTester->execute(['receivers' => ['memory'], '--limit' => '1', '--time-limit' => '3', '--no-reset' => true]);
        static::assertSame(0, $commandTester->getStatusCode());

        $failedMessage = static::getFailedMessage();

        static::assertEquals($message, $failedMessage->getMessage());
        static::assertFailedMessage($failedMessage, 'Saga message '.BadMessageMappingMessage::class.' does not have the mapped property notId. Please check your Saga mapping.');
    }

    public function test_saga_should_raise_error_for_impossible_state_finding(): void
    {
        $message = new ImpossibleStateMessage(10);
        static::get(MessageBusInterface::class)->dispatch($message);

        $commandTester = static::createCommandTester('messenger:consume');
        $commandTester->execute(['receivers' => ['memory'], '--limit' => '1', '--time-limit' => '3', '--no-reset' => true]);
        static::assertSame(0, $commandTester->getStatusCode());

        $failedMessage = static::getFailedMessage();
        static::assertEquals($message, $failedMessage->getMessage());
        static::assertFailedMessage($failedMessage, 'Cannot determine how to find the saga state of '.ImpossibleStateSaga::class.' for message '.ImpossibleStateMessage::class.'. Please check the Saga mapping.');
    }

    public function test_saga_should_raise_error_for_no_handler_method_found(): void
    {
        $message = new NoHandlerMethodMessage(10);
        static::get(MessageBusInterface::class)->dispatch($message);

        $commandTester = static::createCommandTester('messenger:consume');
        $commandTester->execute(['receivers' => ['memory'], '--limit' => '1', '--time-limit' => '3', '--no-reset' => true]);
        static::assertSame(0, $commandTester->getStatusCode());

        $failedMessage = static::getFailedMessage();
        static::assertEquals($message, $failedMessage->getMessage());
        static::assertFailedMessage($failedMessage, 'Cannot handle '.NoHandlerMethodMessage::class.' by Saga '.NoHandlerMethodSaga::class.' : no method handleNoHandlerMethodMessage or attribute '.SagaHandler::class.' on a public or protected method with message typehint on first parameter.');
    }

    protected static function getFailedMessage(): Envelope
    {
        /** @var TransportInterface $failTransport */
        $failTransport = static::get('messenger.transport.failed');
        static::assertCount(1, $failedMessages = $failTransport->get(), 'There are no failed messages.');

        return $failedMessages[0];
    }

    protected static function assertFailedMessage(Envelope $failedMessage, string $exceptionMessage): void
    {
        /** @var ErrorDetailsStamp $errorStamp */
        $errorStamp = $failedMessage->last(ErrorDetailsStamp::class);
        static::assertSame($exceptionMessage, $errorStamp->getExceptionMessage());
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
