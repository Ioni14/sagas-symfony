<?php

namespace Tests\Acceptance\Saga;

use Shared\Application\Saga;
use Shared\Application\SagaMapper;
use Shared\Application\SagaMapperBuilder;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\Acceptance\Saga\Message\TwoHandlerFirstMessage;
use Tests\Acceptance\Saga\Message\TwoHandlerSecondMessage;
use Tests\Acceptance\Saga\State\IntegerState;

class TwoHandlersSaga extends Saga
{
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

    public function handleTwoHandlerFirstMessage(TwoHandlerFirstMessage $message): void
    {
        $this->publish($this->messageBus, new TwoHandlerSecondMessage($message->firstId));
    }

    public function handleTwoHandlerSecondMessage(TwoHandlerSecondMessage $message): void
    {
        $this->markAsCompleted();
    }
}
