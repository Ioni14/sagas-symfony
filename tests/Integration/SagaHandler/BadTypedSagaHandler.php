<?php

namespace Tests\Integration\SagaHandler;

use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shared\Application\SagaState;
use Shipping\Application\SagaInterface;

class BadTypedSagaHandler implements SagaInterface
{
    public static function stateClass(): string
    {
        return BadTypedSagaState::class;
    }

    public static function canStartSaga(object $message): bool
    {
        return false;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('myId')
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [];
    }
}

class BadTypedSagaState extends SagaState
{
    public \stdClass $myId;
}
