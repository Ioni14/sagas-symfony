<?php

namespace Tests\Integration\SagaHandler;

use Shared\Application\Saga;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shared\Application\SagaState;

class IntSagaHandler extends Saga
{
    public static function stateClass(): string
    {
        return IntSagaState::class;
    }

    public static function canStartSaga(object $message): bool
    {
        return false;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('myId')
            ->messageCorrelationIdField(IntMessage::class, 'messageMyId')
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [];
    }
}

class IntMessage
{
    public int $messageMyId;
}

class IntSagaState extends SagaState
{
    public int $myId;
    public string $myName;
    public bool $isActive;
}
