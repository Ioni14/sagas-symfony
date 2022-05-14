<?php

namespace Tests\Acceptance\Saga;

use Shared\Application\Saga;
use Shared\Application\SagaContext;
use Shared\Application\SagaHandler;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shipping\Application\SagaInterface;
use Tests\Acceptance\Saga\Message\BadStateMappingMessage;
use Tests\Acceptance\Saga\State\IntegerState;

class BadStateMappingSaga implements SagaInterface
{
    public static function stateClass(): string
    {
        return IntegerState::class;
    }

    public static function canStartSaga(object $message): bool
    {
        return $message instanceof BadStateMappingMessage;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('notId')
            ->messageCorrelationIdField(BadStateMappingMessage::class, 'id')
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [BadStateMappingMessage::class];
    }

    #[SagaHandler]
    public function handle(BadStateMappingMessage $message, SagaContext $context): void
    {
        $context->markAsCompleted();
    }
}
