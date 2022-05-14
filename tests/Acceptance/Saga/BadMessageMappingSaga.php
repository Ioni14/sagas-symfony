<?php

namespace Tests\Acceptance\Saga;

use Shared\Application\Saga;
use Shared\Application\SagaContext;
use Shared\Application\SagaHandler;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shipping\Application\SagaInterface;
use Tests\Acceptance\Saga\Message\BadMessageMappingMessage;
use Tests\Acceptance\Saga\State\IntegerState;

class BadMessageMappingSaga implements SagaInterface
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

    #[SagaHandler]
    public function handle(BadMessageMappingMessage $message, SagaContext $context): void
    {
        $context->markAsCompleted();
    }
}
