<?php

namespace Tests\Integration;

use Shared\Infrastructure\DoctrineSqlSagaPersister;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;
use Tests\End2End\KernelTestCase;
use Tests\Integration\SagaHandler\DateTimeMessage;
use Tests\Integration\SagaHandler\DatetimeSagaHandler;
use Tests\Integration\SagaHandler\DummyStringeable;
use Tests\Integration\SagaHandler\IntMessage;
use Tests\Integration\SagaHandler\IntSagaHandler;
use Tests\Integration\SagaHandler\IntSagaState;
use Tests\Integration\SagaHandler\StringeableMessage;
use Tests\Integration\SagaHandler\StringeableSagaHandler;
use Tests\Integration\SagaHandler\StringeableSagaState;
use Tests\Integration\SagaHandler\UidMessage;
use Tests\Integration\SagaHandler\UidSagaHandler;
use Tests\Integration\SagaHandler\UidSagaState;

class DoctrineSqlSagaPersisterTest extends KernelTestCase
{
    public function test_it_creates_saga_state_table(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_intsagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(IntSagaHandler::class);

        $tableColumns = static::$connection->fetchAllAssociativeIndexed("
            select c.column_name, c.*
            from information_schema.columns c
            where c.table_name = 'tests_integration_sagahandler_intsagahandler';
        ");
        static::assertArraySubset([
            'COLUMN_TYPE' => 'char(38)',
            'CHARACTER_SET_NAME' => 'ascii',
            'IS_NULLABLE' => 'NO',
        ], $tableColumns['id']);
        static::assertArraySubset([
            'COLUMN_TYPE' => 'bigint(20)',
            'CHARACTER_SET_NAME' => null,
            'IS_NULLABLE' => 'NO',
        ], $tableColumns['correlation_myId']);
        static::assertArraySubset([
            'COLUMN_TYPE' => 'longtext',
            'CHARACTER_SET_NAME' => 'utf8mb4',
            'IS_NULLABLE' => 'NO',
        ], $tableColumns['payload']);
    }

    public function test_it_reads_saga_state_by_id(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_intsagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(IntSagaHandler::class);

        static::$connection->executeStatement('
            INSERT INTO tests_integration_sagahandler_intsagahandler (id, correlation_myId, payload)
            VALUES (?, ?, ?)', [
            '00000000-0000-0000-0000-000000000001',
            10,
            '{"myId": 10, "myName": "John", "isActive": true}',
        ]);

        /** @var IntSagaState $state */
        $state = $sagaPersister->findStateBySagaId(Ulid::fromString('00000000-0000-0000-0000-000000000001'), IntSagaHandler::class);

        static::assertEquals(Ulid::fromString('00000000-0000-0000-0000-000000000001'), $state->id());
        static::assertFalse($state->isNew());
        static::assertSame(10, $state->myId);
        static::assertSame('John', $state->myName);
        static::assertTrue($state->isActive);
    }

    public function test_state_should_be_null_when_reads_inexistent_saga_state_by_id(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_intsagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(IntSagaHandler::class);

        $persistedId = '00000000-0000-0000-0000-000000000001';
        $searchedId = '00000000-0000-0000-0000-000000000002';

        static::$connection->executeStatement('
            INSERT INTO tests_integration_sagahandler_intsagahandler (id, correlation_myId, payload)
            VALUES (?, ?, ?)', [
            $persistedId,
            10,
            '{"myId": 10, "myName": "John", "isActive": true}',
        ]);

        /** @var IntSagaState $state */
        $state = $sagaPersister->findStateBySagaId(Ulid::fromString($searchedId), IntSagaHandler::class);

        static::assertNull($state, 'State should be null when reads inexistent saga state by id');
    }

    public function test_it_reads_saga_state_by_correlation_id_typed_int(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_intsagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(IntSagaHandler::class);

        static::$connection->executeStatement('
            INSERT INTO tests_integration_sagahandler_intsagahandler (id, correlation_myId, payload)
            VALUES (?, ?, ?)', [
            '00000000-0000-0000-0000-000000000001',
            10,
            '{"myId": 10, "myName": "John", "isActive": true}',
        ]);

        $message = new IntMessage();
        $message->messageMyId = 10;

        /** @var IntSagaState $state */
        $state = $sagaPersister->findStateByCorrelationId($message, IntSagaHandler::class);

        static::assertEquals(Ulid::fromString('00000000-0000-0000-0000-000000000001'), $state->id());
        static::assertFalse($state->isNew());
        static::assertSame(10, $state->myId);
        static::assertSame('John', $state->myName);
        static::assertTrue($state->isActive);
    }

    public function test_it_reads_saga_state_by_correlation_id_typed_string(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_stringeablesagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(StringeableSagaHandler::class);

        static::$connection->executeStatement('
            INSERT INTO tests_integration_sagahandler_stringeablesagahandler (id, correlation_myId, payload)
            VALUES (?, ?, ?)', [
            '00000000-0000-0000-0000-000000000001',
            'foo',
            '{"myId": "foo", "myName": "John", "isActive": true}',
        ]);

        $message = new StringeableMessage();
        $message->messageMyId = new DummyStringeable();

        /** @var StringeableSagaState $state */
        $state = $sagaPersister->findStateByCorrelationId($message, StringeableSagaHandler::class);

        static::assertEquals(Ulid::fromString('00000000-0000-0000-0000-000000000001'), $state->id());
        static::assertFalse($state->isNew());
        static::assertEquals(new DummyStringeable(), $state->myId);
        static::assertSame('John', $state->myName);
        static::assertTrue($state->isActive);
    }

    public function test_it_reads_saga_state_by_correlation_id_typed_datetime(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_datetimesagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(DatetimeSagaHandler::class);

        static::$connection->executeStatement('
            INSERT INTO tests_integration_sagahandler_datetimesagahandler (id, correlation_myId, payload)
            VALUES (?, ?, ?)', [
            '00000000-0000-0000-0000-000000000001',
            '2022-01-01 10:00:00',
            '{"myId": "2022-01-01 10:00:00", "myName": "John", "isActive": true}',
        ]);

        $message = new DateTimeMessage();
        $message->messageMyId = new \DateTimeImmutable('2022-01-01 10:00:00');

        /** @var StringeableSagaState $state */
        $state = $sagaPersister->findStateByCorrelationId($message, DatetimeSagaHandler::class);

        static::assertEquals(Ulid::fromString('00000000-0000-0000-0000-000000000001'), $state->id());
        static::assertFalse($state->isNew());
        static::assertEquals(new \DateTimeImmutable('2022-01-01 10:00:00'), $state->myId);
        static::assertSame('John', $state->myName);
        static::assertTrue($state->isActive);
    }

    public function test_it_reads_saga_state_by_correlation_id_typed_uid(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_uidsagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(UidSagaHandler::class);

        static::$connection->executeStatement('
            INSERT INTO tests_integration_sagahandler_uidsagahandler (id, correlation_myId, payload)
            VALUES (?, ?, ?)', [
            '00000000-0000-0000-0000-000000000001',
            '00000000-0000-0000-0000-000000000002',
            '{"myId": "00000000-0000-0000-0000-000000000002", "myName": "John", "isActive": true}',
        ]);

        $message = new UidMessage();
        $message->messageMyId = Ulid::fromString('00000000-0000-0000-0000-000000000002');

        /** @var UidSagaState $state */
        $state = $sagaPersister->findStateByCorrelationId($message, UidSagaHandler::class);

        static::assertEquals(Ulid::fromString('00000000-0000-0000-0000-000000000001'), $state->id());
        static::assertFalse($state->isNew());
        static::assertEquals(Ulid::fromString('00000000-0000-0000-0000-000000000002'), $state->myId);
        static::assertSame('John', $state->myName);
        static::assertTrue($state->isActive);
    }

    public function test_it_inserts_new_saga_state(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_uidsagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(UidSagaHandler::class);

        $state = UidSagaState::create(Ulid::fromString('00000000-0000-0000-0000-000000000001'));
        $state->isActive = true;
        $state->myName = 'Jane';
        $state->myId = Ulid::fromString('00000000-0000-0000-0000-000000000002');

        $message = new UidMessage;
        $message->messageMyId = Ulid::fromString('00000000-0000-0000-0000-000000000002');

        $sagaPersister->saveState($state, $message, UidSagaHandler::class);

        $result = static::$connection->fetchAssociative('SELECT * FROM tests_integration_sagahandler_uidsagahandler WHERE id = ?', ['00000000-0000-0000-0000-000000000001']);
        static::assertSame([
            'id' => '00000000-0000-0000-0000-000000000001',
            'correlation_myId' => '00000000-0000-0000-0000-000000000002',
            'payload' => '{"myId":"00000000000000000000000002","myName":"Jane","isActive":true}',
        ], $result);
    }

    public function test_state_should_not_have_null_correlation_id_at_insert(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_uidsagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(UidSagaHandler::class);

        $state = UidSagaState::create(Ulid::fromString('00000000-0000-0000-0000-000000000001'));
        $state->isActive = true;
        $state->myName = 'Jane';
        $state->myId = null;

        $message = new \stdclass;

        $this->expectExceptionMessage(sprintf('Saga State Correlation ID %s::%s has changed to null. Please check the Saga %s.', UidSagaState::class, 'myId', UidSagaHandler::class));

        $sagaPersister->saveState($state, $message, UidSagaHandler::class);

        $result = static::$connection->fetchAssociative('SELECT * FROM tests_integration_sagahandler_uidsagahandler WHERE id = ?', ['00000000-0000-0000-0000-000000000001']);
        static::assertSame([
            'id' => '00000000-0000-0000-0000-000000000001',
            'correlation_myId' => '00000000-0000-0000-0000-000000000002',
            'payload' => '{"myId":"00000000000000000000000002","myName":"Jane","isActive":true}',
        ], $result);
    }

    public function test_state_correlation_id_should_not_change_at_insert(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_uidsagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(UidSagaHandler::class);

        $state = UidSagaState::create(Ulid::fromString('00000000-0000-0000-0000-000000000001'));
        $state->isActive = true;
        $state->myName = 'Jane';
        $state->myId = Ulid::fromString('00000000-0000-0000-0000-000000000003'); // differ from $message->messageMyId

        $message = new UidMessage;
        $message->messageMyId = Ulid::fromString('00000000-0000-0000-0000-000000000002');

        $this->expectExceptionMessage(sprintf('Saga State Correlation ID %s::%s has changed to "%s". Please check the Saga %s.', UidSagaState::class, 'myId', '00000000-0000-0000-0000-000000000003', UidSagaHandler::class));

        $sagaPersister->saveState($state, $message, UidSagaHandler::class);

        $result = static::$connection->fetchAssociative('SELECT * FROM tests_integration_sagahandler_uidsagahandler WHERE id = ?', ['00000000-0000-0000-0000-000000000001']);
        static::assertSame([
            'id' => '00000000-0000-0000-0000-000000000001',
            'correlation_myId' => '00000000-0000-0000-0000-000000000002',
            'payload' => '{"myId":"00000000000000000000000002","myName":"Jane","isActive":true}',
        ], $result);
    }

    public function test_it_updates_saga_state(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_uidsagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(UidSagaHandler::class);

        static::$connection->executeStatement('
            INSERT INTO tests_integration_sagahandler_uidsagahandler (id, correlation_myId, payload)
            VALUES (?, ?, ?)', [
            '00000000-0000-0000-0000-000000000001',
            '00000000-0000-0000-0000-000000000002',
            '{"myId": "00000000-0000-0000-0000-000000000002", "myName": "John", "isActive": true}',
        ]);
;
        /** @var UidSagaState $state */
        $state = $sagaPersister->findStateBySagaId(Ulid::fromString('00000000-0000-0000-0000-000000000001'), UidSagaHandler::class);

        $state->isActive = false;
        $state->myName = 'Jane';

        $message = new UidMessage;
        $message->messageMyId = Ulid::fromString('00000000-0000-0000-0000-000000000002');

        $sagaPersister->saveState($state, $message, UidSagaHandler::class);

        $result = static::$connection->fetchAssociative('SELECT * FROM tests_integration_sagahandler_uidsagahandler WHERE id = ?', ['00000000-0000-0000-0000-000000000001']);
        static::assertSame([
            'id' => '00000000-0000-0000-0000-000000000001',
            'correlation_myId' => '00000000-0000-0000-0000-000000000002',
            'payload' => '{"myId":"00000000000000000000000002","myName":"Jane","isActive":false}',
        ], $result);
    }

    public function test_state_correlation_id_should_not_be_updated(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_uidsagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(UidSagaHandler::class);

        static::$connection->executeStatement('
            INSERT INTO tests_integration_sagahandler_uidsagahandler (id, correlation_myId, payload)
            VALUES (?, ?, ?)', [
            '00000000-0000-0000-0000-000000000001',
            '00000000-0000-0000-0000-000000000002',
            '{"myId": "00000000-0000-0000-0000-000000000002", "myName": "John", "isActive": true}',
        ]);
;
        /** @var UidSagaState $state */
        $state = $sagaPersister->findStateBySagaId(Ulid::fromString('00000000-0000-0000-0000-000000000001'), UidSagaHandler::class);

        $state->isActive = false;
        $state->myName = 'Jane';
        $state->myId = Ulid::fromString('00000000-0000-0000-0000-000000000003'); // *** Try to update correlation id ***

        $message = new UidMessage;
        $message->messageMyId = Ulid::fromString('00000000-0000-0000-0000-000000000002');

        $this->expectExceptionMessage(sprintf('Saga State Correlation ID %s::%s has changed to "%s". Please check the Saga %s.', UidSagaState::class, 'myId', '00000000-0000-0000-0000-000000000003', UidSagaHandler::class));

        $sagaPersister->saveState($state, $message, UidSagaHandler::class);

        $result = static::$connection->fetchAssociative('SELECT * FROM tests_integration_sagahandler_uidsagahandler WHERE id = ?', ['00000000-0000-0000-0000-000000000001']);
        static::assertSame([
            'id' => '00000000-0000-0000-0000-000000000001',
            'correlation_myId' => '00000000-0000-0000-0000-000000000002',
            'payload' => '{"myId":"00000000000000000000000002","myName":"Jane","isActive":false}',
        ], $result);
    }

    public function test_state_correlation_id_should_not_be_unset(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_uidsagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(UidSagaHandler::class);

        static::$connection->executeStatement('
            INSERT INTO tests_integration_sagahandler_uidsagahandler (id, correlation_myId, payload)
            VALUES (?, ?, ?)', [
            '00000000-0000-0000-0000-000000000001',
            '00000000-0000-0000-0000-000000000002',
            '{"myId": "00000000-0000-0000-0000-000000000002", "myName": "John", "isActive": true}',
        ]);
;
        /** @var UidSagaState $state */
        $state = $sagaPersister->findStateBySagaId(Ulid::fromString('00000000-0000-0000-0000-000000000001'), UidSagaHandler::class);

        $state->isActive = false;
        $state->myName = 'Jane';
        $state->myId = null;

        $message = new \stdclass;

        $this->expectExceptionMessage(sprintf('Saga State Correlation ID %s::%s has changed to null. Please check the Saga %s.', UidSagaState::class, 'myId', UidSagaHandler::class));

        $sagaPersister->saveState($state, $message, UidSagaHandler::class);

        $result = static::$connection->fetchAssociative('SELECT * FROM tests_integration_sagahandler_uidsagahandler WHERE id = ?', ['00000000-0000-0000-0000-000000000001']);
        static::assertSame([
            'id' => '00000000-0000-0000-0000-000000000001',
            'correlation_myId' => '00000000-0000-0000-0000-000000000002',
            'payload' => '{"myId":"00000000000000000000000002","myName":"Jane","isActive":false}',
        ], $result);
    }

    public function test_it_should_delete_state(): void
    {
        static::$connection->executeStatement('DROP TABLE IF EXISTS tests_integration_sagahandler_uidsagahandler');

        $sagaPersister = static::get(DoctrineSqlSagaPersister::class);
        $sagaPersister->setup(UidSagaHandler::class);

        static::$connection->executeStatement('
            INSERT INTO tests_integration_sagahandler_uidsagahandler (id, correlation_myId, payload)
            VALUES (?, ?, ?)', [
            '00000000-0000-0000-0000-000000000001',
            '00000000-0000-0000-0000-000000000002',
            '{"myId": "00000000-0000-0000-0000-000000000002", "myName": "John", "isActive": true}',
        ]);
;
        /** @var UidSagaState $state */
        $state = $sagaPersister->findStateBySagaId(Ulid::fromString('00000000-0000-0000-0000-000000000001'), UidSagaHandler::class);

        $sagaPersister->deleteState($state, UidSagaHandler::class);

        $result = static::$connection->fetchAssociative('SELECT * FROM tests_integration_sagahandler_uidsagahandler WHERE id = ?', ['00000000-0000-0000-0000-000000000001']);
        static::assertFalse($result, 'State should be deleted');
    }
}
