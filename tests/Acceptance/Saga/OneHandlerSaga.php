<?php

namespace Tests\Acceptance\Saga;

use Shared\Application\SagaContext;
use Shared\Application\SagaHandler;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shipping\Application\SagaInterface;
use Tests\Acceptance\Saga\Message\OneHandlerFirstMessage;
use Tests\Acceptance\Saga\State\StringState;

class OneHandlerSaga implements SagaInterface
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
    public function handle(OneHandlerFirstMessage $message, SagaContext $context): void
    {
        $context->markAsCompleted();
    }
}
