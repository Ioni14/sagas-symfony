<?php

namespace Tests\Acceptance\Saga;

use Shared\Application\Saga;
use Shared\Application\SagaContext;
use Shared\Application\SagaHandler;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shipping\Application\SagaInterface;
use Tests\Acceptance\Saga\Message\BadMessageMappingMessage;
use Tests\Acceptance\Saga\Message\NoHandlerMethodMessage;
use Tests\Acceptance\Saga\State\IntegerState;

class NoHandlerMethodSaga implements SagaInterface
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

    /*
     * Pas de SagaHandler attribute
     */
    public function handleBadNameMethod(NoHandlerMethodMessage $message, SagaContext $context): void
    {
        $context->markAsCompleted();
    }
}
