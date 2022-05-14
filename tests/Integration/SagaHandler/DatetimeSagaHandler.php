<?php

namespace Tests\Integration\SagaHandler;

use Shared\Application\Saga;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shared\Application\SagaState;
use Shipping\Application\SagaInterface;

class DatetimeSagaHandler implements SagaInterface
{
    public static function stateClass(): string
    {
        return DateTimeSagaState::class;
    }

    public static function canStartSaga(object $message): bool
    {
        return false;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('myId')
            ->messageCorrelationIdField(DateTimeMessage::class, 'messageMyId')
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [];
    }
}

class DateTimeMessage
{
    public \DateTimeImmutable $messageMyId;
}

class DateTimeSagaState extends SagaState
{
    public \DateTimeImmutable $myId;
    public string $myName;
    public bool $isActive;
}
