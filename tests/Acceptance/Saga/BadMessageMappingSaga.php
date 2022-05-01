<?php

namespace Tests\Acceptance\Saga;

use Shared\Application\Saga;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Tests\Acceptance\Saga\Message\BadMessageMappingMessage;
use Tests\Acceptance\Saga\State\IntegerState;

class BadMessageMappingSaga extends Saga
{
    public static function stateClass(): string
    {
        return IntegerState::class;
    }

    public static function canStartSaga(object $message): bool
    {
        return $message instanceof BadMessageMappingMessage;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('myId')
            ->messageCorrelationIdField(BadMessageMappingMessage::class, 'notId')
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [BadMessageMappingMessage::class];
    }

    public function handleBadMessageMappingMessage(BadMessageMappingMessage $message): void
    {
        $this->markAsCompleted();
    }
}
