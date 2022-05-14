<?php

namespace Tests\Acceptance\Saga;

use Shared\Application\Saga;
use Shared\Application\SagaContext;
use Shared\Application\SagaHandler;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Shipping\Application\SagaInterface;
use Shipping\Application\SagaPublishTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\Acceptance\Saga\Message\TwoHandlerFirstMessage;
use Tests\Acceptance\Saga\Message\TwoHandlerSecondMessage;
use Tests\Acceptance\Saga\State\IntegerState;

class TwoHandlersSaga implements SagaInterface
{
    use SagaPublishTrait;

    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public static function stateClass(): string
    {
        return IntegerState::class;
    }

    public static function canStartSaga(object $message): bool
    {
        return $message instanceof TwoHandlerFirstMessage;
    }

    public static function mapping(): SagaMapper
    {
        return SagaMapperBuilder::stateCorrelationIdField('myId')
            ->messageCorrelationIdField(TwoHandlerFirstMessage::class, 'firstId')
            ->messageCorrelationIdField(TwoHandlerSecondMessage::class, 'secondId')
            ->build();
    }

    public static function getHandledMessages(): array
    {
        return [TwoHandlerFirstMessage::class, TwoHandlerSecondMessage::class];
    }

    #[SagaHandler]
    public function handleFirst(TwoHandlerFirstMessage $message, SagaContext $context): void
    {
        $this->publish($this->messageBus, new TwoHandlerSecondMessage($message->firstId), $context);
    }

    #[SagaHandler]
    public function handleSecond(TwoHandlerSecondMessage $message, SagaContext $context): void
    {
        $context->markAsCompleted();
    }
}
