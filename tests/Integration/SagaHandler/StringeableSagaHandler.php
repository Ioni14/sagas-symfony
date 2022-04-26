<?php

namespace Tests\Integration\SagaHandler;

use Shared\Application\Saga;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shared\Application\SagaState;

class StringeableSagaHandler extends Saga
{
    public static function stateClass(): string
    {
        return StringeableSagaState::class;
    }

    public static function canStartSaga(object $message): bool
    {
        return false;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('myId')
            ->messageCorrelationIdField(StringeableMessage::class, 'messageMyId')
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [];
    }
}

class DummyStringeable {
    public function __toString() {
        return 'foo';
    }
}

class StringeableMessage
{
    public DummyStringeable $messageMyId;
}

class StringeableSagaState extends SagaState
{
    public DummyStringeable $myId;
    public string $myName;
    public bool $isActive;
}
