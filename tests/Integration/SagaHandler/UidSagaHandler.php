<?php

namespace Tests\Integration\SagaHandler;

use Shared\Application\Saga;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shared\Application\SagaState;
use Shipping\Application\SagaInterface;
use Symfony\Component\Uid\Ulid;

class UidSagaHandler implements SagaInterface
{
    public static function stateClass(): string
    {
        return UidSagaState::class;
    }

    public static function canStartSaga(object $message): bool
    {
        return false;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('myId')
            ->messageCorrelationIdField(UidMessage::class, 'messageMyId')
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [];
    }
}

class UidMessage
{
    public Ulid $messageMyId;
}

class UidSagaState extends SagaState
{
    public ?Ulid $myId;
    public string $myName;
    public bool $isActive;
}
