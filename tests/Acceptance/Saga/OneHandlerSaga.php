<?php

namespace Tests\Acceptance\Saga;

use Shared\Application\Saga;
use Shared\Application\SagaHandler;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Tests\Acceptance\Saga\Message\OneHandlerFirstMessage;
use Tests\Acceptance\Saga\Message\TwoHandlerFirstMessage;
use Tests\Acceptance\Saga\State\StringState;

class OneHandlerSaga extends Saga
{
    public static function stateClass(): string
    {
        return StringState::class;
    }

    public static function canStartSaga(object $message): bool
    {
        return $message instanceof OneHandlerFirstMessage;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('myId')
            ->messageCorrelationIdField(OneHandlerFirstMessage::class, 'id')
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [OneHandlerFirstMessage::class];
    }

    #[SagaHandler]
    protected function handle(OneHandlerFirstMessage $message): void
    {
        $this->markAsCompleted();
    }
}
