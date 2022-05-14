<?php

namespace Tests\Acceptance\Saga;

use Shared\Application\SagaContext;
use Shared\Application\SagaHandler;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shipping\Application\SagaInterface;
use Tests\Acceptance\Saga\Message\ImpossibleStateMessage;
use Tests\Acceptance\Saga\State\IntegerState;

class ImpossibleStateSaga implements SagaInterface
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

    #[SagaHandler]
    public function handle(ImpossibleStateMessage $message, SagaContext $context): void
    {
        $context->markAsCompleted();
    }
}
