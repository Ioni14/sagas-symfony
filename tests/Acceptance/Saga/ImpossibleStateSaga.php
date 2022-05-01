<?php

namespace Tests\Acceptance\Saga;

use Shared\Application\Saga;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Tests\Acceptance\Saga\Message\ImpossibleStateMessage;
use Tests\Acceptance\Saga\State\IntegerState;

class ImpossibleStateSaga extends Saga
{
    public static function stateClass(): string
    {
        return IntegerState::class;
    }

    public static function canStartSaga(object $message): bool
    {
        return $message instanceof ImpossibleStateMessage;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('myId')
            // no message mapping
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [ImpossibleStateMessage::class];
    }

    public function handleImpossibleStateMessage(ImpossibleStateMessage $message): void
    {
        $this->markAsCompleted();
    }
}
