<?php

namespace Shared\Application;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Shared\Infrastructure\Messenger\SagaContextStamp;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Uid\Ulid;

abstract class Saga implements MessageSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly string $stateClass,
    ) {
        $this->logger = new NullLogger();
    }

    final public function __invoke(object $sagaContext): void
    {
        if (!$sagaContext instanceof SagaContext) {
            // les messages qui démarrent un saga (si ce n'est pas un SagaContext c'est que le message ne vient pas d'un Saga ET que ce saga le gère => donc on le wrap simplement pour le considérer maintenant comme un saga context)
            $sagaContext = new SagaContext($sagaContext);
        }

        $shortClassName = substr($sagaContext->message::class, strrpos($sagaContext->message::class, '\\') + 1);

        $state = $this->findState($sagaContext->message, $sagaContext->sagaId);
        if (!$state) {
            // nouveau Saga
            if (!$this->canStartSaga($sagaContext->message)) {
                // un message de ce Saga est arrivé et n'est pas désigné comme départ
                // cela signifie que le Saga a été terminé et qu'un message de ce même Saga est arrivé ensuite
                // => ignore
                // TODO : call implement SagaNotFoundHandlerInterface::onSagaNotFound() ?

                // TODO : gère multiple Saga pour un même type de message ?
                $this->logger->info('No saga {sagaName} found for message {message}, ignoring since the saga has been marked as complete before the timeout fired.', [
                    'message' => $sagaContext->message,
                    'sagaName' => static::class,
                    'sagaId' => $sagaContext->sagaId,
                ]);
                return;
            }
            if (!is_a($this->stateClass, SagaState::class, true)) {
                throw new \RuntimeException('State class must be a subclass of SagaState');
            }
            /** @var SagaState $state */
            $state = new ($this->stateClass);
            $state->id = new Ulid();
            // TODO : other metadata

            // TODO : [Bonus] mapping state auto
            $state->orderId = Ulid::fromString($sagaContext->message->orderId);

            $this->logger->info('A new Saga {sagaName} has started.', [
                'sagaName' => static::class,
                'sagaId' => $state->id,
                'correlation_id' => $state->orderId,
            ]);
        }

        $handlerName = 'handle' . $shortClassName;
        if (!method_exists($this, $handlerName)) {
            // TODO : log or throw, cannot handle this message
            throw new \RuntimeException("Cannot handle $shortClassName : no method $handlerName found in the Saga " . static::class);
        }
        $this->logger->info('The Saga {sagaName} handles {message}.', [
            'sagaName' => static::class,
            'sagaId' => $state->id,
            'correlation_id' => $state->orderId,
            'message' => $sagaContext->message,
        ]);
        try {
            $this->$handlerName($sagaContext->message, $state, $sagaContext);
        } catch (\Throwable $e) {
            // TODO : on fait quoi avec le State si le message est full erreur ?
            $this->logger->error('The Saga {sagaName} has failed when handling {message} : {exception}', [
                'sagaName' => static::class,
                'sagaId' => $state->id,
                'correlation_id' => $state->orderId,
                'message' => $sagaContext->message,
                'exception' => $e,
            ]);
            throw $e;
        }

        if (!$sagaContext->isCompleted()) {
            $this->saveState($state);
        } else {
            $this->deleteState($state);
            $this->logger->info('The Saga {sagaName} has been completed.', [
                'sagaName' => static::class,
                'sagaId' => $state->id,
                'correlation_id' => $state->orderId,
            ]);
        }
    }

    protected function publish(MessageBusInterface $bus, SagaState $state, object $message, array $stamps = []): void
    {
        $bus->dispatch($message, [new SagaContextStamp($state->id), ...$stamps]);
    }

    /**
     * TODO : improve $delay param, maybe a configurator / builder ?
     */
    protected function timeout(MessageBusInterface $bus, SagaState $state, \DateInterval $delay, object $message, array $stamps = []): void
    {
        $this->publish($bus, $state, $message, [DelayStamp::delayFor($delay), ...$stamps]);
    }

    abstract protected function canStartSaga(object $message): bool;

    // TODO : refacto avec un service SagaProviderInterface ?
    abstract protected function findState(object $message, ?Ulid $sagaId): ?SagaState;

    abstract protected function saveState(SagaState $state): void;

    abstract protected function deleteState(SagaState $state): void;
}
