<?php

namespace Tests\Acceptance\Saga;

use Shared\Application\Saga;
use Shared\Application\SagaHandler;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Tests\Acceptance\Saga\Message\BadMessageMappingMessage;
use Tests\Acceptance\Saga\Message\NoHandlerMethodMessage;
use Tests\Acceptance\Saga\State\IntegerState;

class NoHandlerMethodSaga extends Saga
{
    public static function stateClass(): string
    {
        return IntegerState::class;
    }

    public static function canStartSaga(object $message): bool
    {
        return $message instanceof NoHandlerMethodMessage;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('myId')
            ->messageCorrelationIdField(NoHandlerMethodMessage::class, 'id')
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [NoHandlerMethodMessage::class];
    }

    protected function handleBadNameMethod(NoHandlerMethodMessage $message): void
    {
        $this->markAsCompleted();
    }
}
