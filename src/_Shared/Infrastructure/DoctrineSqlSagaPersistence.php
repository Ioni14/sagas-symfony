<?php

namespace Shared\Infrastructure;

use Doctrine\DBAL\Connection;
use Shared\Application\BadSagaMappingException;
use Shared\Application\Saga;
use Shared\Application\SagaPersistenceInterface;
use Shared\Application\SagaState;
use Shared\Application\UnableDetermineSqlTypeException;
use Shipping\Application\SagaInterface;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;

class DoctrineSqlSagaPersistence implements SagaPersistenceInterface
{
//    private Serializer $serializer;

    public function __construct(
        private readonly Connection $dbConnection,
        private readonly SerializerInterface $serializer, // TODO : init a custom Serializer with only the needed Normalizer&Encoder
    ) {
        // TODO : create our proper serializer and inject it
//        $this->serializer = new Serializer([
//            new CustomNormalizer(),
//            new UidNormalizer(),
//            new JsonSerializableNormalizer(),
//            new DateTimeNormalizer(),
//            new DateTimeZoneNormalizer(),
//            new DateIntervalNormalizer(),
//            new BackedEnumNormalizer(),
//            new DataUriNormalizer(),
//            new ArrayDenormalizer(),
//            new ObjectNormalizer(),
//        ], [new JsonEncoder()]);
    }

    /**
     * @param class-string<SagaInterface> $sagaHandlerClass
     *
     * TODO : decline in all SQL dialects (MySQL, MariaDB, PostgreSQL, SQLServer, Oracle...)
     */
    public function setup(string $sagaHandlerClass): void
    {
        if (!is_a($sagaHandlerClass, SagaInterface::class, true)) {
            throw new \InvalidArgumentException('Argument $sagaHandlerClass must extends ' . Saga::class);
        }

        $tableName = strtolower(str_replace('\\', '_', $sagaHandlerClass));
        $mapping = $sagaHandlerClass::mapping();
        $stateCorrelationField = $mapping->stateCorrelationIdField();

        $stateClass = $sagaHandlerClass::stateClass();
        $sqlType = $this->determineSqlType($stateClass, $stateCorrelationField);

        // TODO : extract protected method just the SQL ?
        $this->dbConnection->executeStatement(<<<SQL
            create table if not exists `$tableName`(
                id char(38) not null,
                correlation_$stateCorrelationField $sqlType not null,
                payload json not null,
                primary key (id),
                constraint idx_correlation_$stateCorrelationField unique (correlation_$stateCorrelationField)
            ) default charset=ascii
        SQL
        );
    }

    /**
     * @param class-string<SagaInterface> $sagaHandlerClass
     */
    public function findStateBySagaId(AbstractUid $sagaId, string $sagaHandlerClass): ?SagaState
    {
        if (!is_a($sagaHandlerClass, SagaInterface::class, true)) {
            throw new \InvalidArgumentException('Argument $sagaHandlerClass must extends ' . Saga::class);
        }

        $tableName = strtolower(str_replace('\\', '_', $sagaHandlerClass));
        $mapping = $sagaHandlerClass::mapping();
        $stateCorrelationField = $mapping->stateCorrelationIdField();

        $stateClass = $sagaHandlerClass::stateClass();

        $row = $this->dbConnection->fetchAssociative(<<<SQL
            SELECT id, correlation_$stateCorrelationField, payload FROM `$tableName`
            WHERE id = :id
            FOR UPDATE
        SQL, [
            'id' => $sagaId->toRfc4122(),
        ]);
        if ($row === false) {
            return null;
        }

        /** @var SagaState $state */
        $state = $this->serializer->deserialize($row['payload'], $stateClass, 'json');
        $ref = new \ReflectionProperty($state, 'id');
        $ref->setAccessible(true);
        $ref->setValue($state, Ulid::fromString($row['id']));

        return $state;
    }

    /**
     * @param class-string<SagaInterface> $sagaHandlerClass
     */
    public function findStateByCorrelationId(object $message, string $sagaHandlerClass): ?SagaState
    {
        if (!is_a($sagaHandlerClass, SagaInterface::class, true)) {
            throw new \InvalidArgumentException('Argument $sagaHandlerClass must extends ' . Saga::class);
        }

        $tableName = strtolower(str_replace('\\', '_', $sagaHandlerClass));
        $mapping = $sagaHandlerClass::mapping();
        $stateCorrelationField = $mapping->stateCorrelationIdField();
        $messageCorrelationField = $mapping->messageCorrelationIdField($message::class);
        if ($messageCorrelationField === null) {
            throw new \InvalidArgumentException(sprintf('Unable to determine message correlation id field for message %s. Please check the Saga mapping of %s.', $message::class, $sagaHandlerClass));
        }

        $accessor = PropertyAccess::createPropertyAccessor();
        try {
            $correlationId = $accessor->getValue($message, $messageCorrelationField);
        } catch (NoSuchPropertyException $e) {
            throw new BadSagaMappingException('Saga message ' . $message::class . ' does not have the mapped property ' . $messageCorrelationField . '. Please check your Saga mapping.', previous: $e);
        }

        $correlationValue = (new SagaCorrelationIdFormatter())->format($correlationId);

        $stateClass = $sagaHandlerClass::stateClass();

        $row = $this->dbConnection->fetchAssociative(<<<SQL
            SELECT id, correlation_$stateCorrelationField, payload FROM `$tableName`
            WHERE correlation_$stateCorrelationField = :correlation_id
            FOR UPDATE
        SQL, [
            'correlation_id' => $correlationValue,
        ]);
        if ($row === false) {
            return null;
        }

        /** @var SagaState $state */
        $state = $this->serializer->deserialize($row['payload'], $stateClass, 'json');
        $ref = new \ReflectionProperty($state, 'id');
        $ref->setAccessible(true);
        $ref->setValue($state, Ulid::fromString($row['id']));

        return $state;
    }

    /**
     * @param class-string<SagaInterface> $sagaHandlerClass
     */
    public function saveState(SagaState $state, object $message, string $sagaHandlerClass): void
    {
        if (!is_a($sagaHandlerClass, SagaInterface::class, true)) {
            throw new \InvalidArgumentException('Argument $sagaHandlerClass must extends ' . Saga::class);
        }

        $tableName = strtolower(str_replace('\\', '_', $sagaHandlerClass));
        $mapping = $sagaHandlerClass::mapping();
        $stateCorrelationField = $mapping->stateCorrelationIdField();
        $messageCorrelationField = $mapping->messageCorrelationIdField($message::class);

        $accessor = PropertyAccess::createPropertyAccessor();
        try {
            $stateCorrelationId = $accessor->getValue($state, $stateCorrelationField);
        } catch (NoSuchPropertyException $e) {
            throw new BadSagaMappingException('Saga state ' . $state::class . ' does not have the correlation field ' . $stateCorrelationField . '. Please check your Saga mapping.', previous: $e);
        }

        $correlationValue = (new SagaCorrelationIdFormatter())->format($stateCorrelationId);

        if ($messageCorrelationField !== null) {
            try {
                $messageCorrelationId = $accessor->getValue($message, $messageCorrelationField);
            } catch (NoSuchPropertyException $e) {
                throw new BadSagaMappingException('Saga message ' . $message::class . ' does not have the mapped property ' . $messageCorrelationField . '. Please check your Saga mapping.', previous: $e);
            }

            // *** /!\ the operator != is used to compare 2 value objects (e.g. Uuid) ***
            if ($messageCorrelationId != $stateCorrelationId) {
                throw new \RuntimeException(sprintf('Saga State Correlation ID %s::%s has changed to "%s". Please check the Saga %s.', $state::class, $stateCorrelationField, $correlationValue, $sagaHandlerClass,));
            }
        } elseif ($stateCorrelationId === null) {
            throw new \RuntimeException(sprintf('Saga State Correlation ID %s::%s has changed to null. Please check the Saga %s.', $state::class, $stateCorrelationField, $sagaHandlerClass,));
        }

        if ($state->isNew()) {
            $this->dbConnection->executeStatement(<<<SQL
                insert into `$tableName` (id, correlation_$stateCorrelationField, payload)
                values (:id, :correlation_id, :payload)
            SQL, [
                'id' => $state->id()->toRfc4122(),
                'correlation_id' => $correlationValue,
                'payload' => $this->serializer->serialize($state, 'json'),
            ]);

            return;
        }

        // TODO : improve perf when updating json payload (what if the json is fat ?)
        $this->dbConnection->executeStatement(<<<SQL
            update `$tableName` set payload = :payload where id = :id
        SQL, [
            'id' => $state->id()->toRfc4122(),
            'payload' => $this->serializer->serialize($state, 'json'),
        ]);
    }

    /**
     * @param class-string<SagaInterface> $sagaHandlerClass
     */
    public function deleteState(SagaState $state, string $sagaHandlerClass): void
    {
        if (!is_a($sagaHandlerClass, SagaInterface::class, true)) {
            throw new \InvalidArgumentException('Argument $sagaHandlerClass must extends ' . Saga::class);
        }

        $tableName = strtolower(str_replace('\\', '_', $sagaHandlerClass));

        $this->dbConnection->executeStatement(<<<SQL
            delete from `$tableName` where id = :id
        SQL, [
            'id' => $state->id()->toRfc4122(),
        ]);
    }

    public function transactional(callable $callback): mixed
    {
//        $this->dbConnection->setTransactionIsolation();
        return $this->dbConnection->transactional($callback);
    }

    /**
     * @param class-string<SagaState> $stateClass
     */
    private function determineSqlType(string $stateClass, string $stateCorrelationField): string
    {
        $property = new \ReflectionProperty($stateClass, $stateCorrelationField);

        $type = $property->getType();
        $sqlType = null;
        if ($type instanceof \ReflectionNamedType) {
            if (is_a($type->getName(), AbstractUid::class, true)) {
                $sqlType = 'char(38) character set ascii';
            } elseif (is_a($type->getName(), \DateTimeInterface::class, true)) {
                $sqlType = 'datetime';
            } elseif ($type->getName() === 'int') {
                $sqlType = 'bigint(20)';
            } elseif ($type->getName() === 'string' || is_a($type->getName(), \Stringable::class, true)) {
                $sqlType = 'varchar(200) character set utf8mb4';
            }
        }
        if ($sqlType === null) {
            throw new UnableDetermineSqlTypeException('Unable to determine type of property ' . $stateClass . '::' . $stateCorrelationField . '. Please typehint one of ' . implode(', ', [AbstractUid::class, 'string', 'int', \DateTimeInterface::class]));
        }

        return $sqlType;
    }
}
