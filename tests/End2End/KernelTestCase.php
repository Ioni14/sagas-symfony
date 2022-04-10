<?php

namespace Tests\End2End;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase as BaseKernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection as AmqpConnection;
use Tests\Constraint\ArraySubset;

abstract class KernelTestCase extends BaseKernelTestCase
{
    protected static Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();
        static::$connection = static::get(ManagerRegistry::class)->getConnection();
        static::$connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (static::$connection->isTransactionActive()) {
            static::$connection->rollBack();
        }
        parent::tearDown();
    }

    protected static function get(string $serviceId): ?object
    {
        return static::getContainer()->get($serviceId);
    }

    protected static function assertArraySubset(iterable $subset, $haystack, string $message = ''): void
    {
        static::assertThat($haystack, new ArraySubset($subset), $message);
    }

    protected static function createCommandTester(string $commandName): CommandTester
    {
        $app = new Application(static::$kernel);
        $command = $app->find($commandName);

        return new CommandTester($command);
    }

    protected static function loadSqlFixtures(string $filepath): void
    {
        static::$connection->executeStatement(file_get_contents(static::getFixturesPath().'/'.$filepath));
    }

    /**
     * @return string the directory path of fixtures used to locate fixtures files
     */
    protected static function getFixturesPath(): string
    {
        return __DIR__.'/resources';
    }

    protected static function getAmqpConnection(string $transportId): AmqpConnection
    {
        $busTransport = static::get($transportId);
        $reflect = new \ReflectionProperty($busTransport, 'connection');
        $reflect->setAccessible(true);

        return $reflect->getValue($busTransport);
    }

    protected static function purgeQueues(string $transportId): void
    {
        static::getAmqpConnection($transportId)->purgeQueues();
    }

    protected static function assertTransportCountMessages(string $transportId, int $count): void
    {
        /** @var AmqpTransport $transport */
        $transport = static::get($transportId);
        static::assertSame($count, $transport->getMessageCount());
    }

    protected static function assertNextMessageEquals(string $transportId, object $expectedMessage): void
    {
        /** @var AmqpTransport $transport */
        $transport = static::get($transportId);
        $message = $transport->get()->current()->getMessage();
        static::assertInstanceOf($expectedMessage::class, $message);
        static::assertEquals($expectedMessage, $message);
    }
}
