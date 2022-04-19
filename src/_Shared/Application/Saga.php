<?php

namespace Shared\Application;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Shared\Infrastructure\Messenger\SagaContextStamp;
use STS\Backoff\Backoff;
use STS\Backoff\Strategies\ExponentialStrategy;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Uid\Ulid;

abstract class Saga implements MessageSubscriberInterface, LoggerAwareInterface
{
    /**
     * TODO : inject good logger channel ?
     */
    use LoggerAwareTrait;

    public function __construct(
        private readonly string $stateClass,
        private readonly SagaProviderInterface $sagaProvider,
    ) {
        $this->logger = new NullLogger();
    }

    final public static function getHandledMessages(): iterable
    {
        return array_merge(static::getHandleMessages(), static::getTimeoutMessages());
    }

    abstract protected static function getHandleMessages(): array;

    protected static function getTimeoutMessages(): array
    {
        return [];
    }

    private function isTimeoutMessage(object $message): bool
    {
        return in_array($message::class, static::getTimeoutMessages(), true);
    }

    final public function __invoke(object $sagaContext): void
    {
        if (!$sagaContext instanceof SagaContext) {
            // les messages qui démarrent un saga (si ce n'est pas un SagaContext c'est que le message ne vient pas d'un Saga ET que ce saga le gère => donc on le wrap simplement pour le considérer maintenant comme un saga context)
            $sagaContext = new SagaContext($sagaContext);
        }
        $mapping = static::configureMapping();

        $shortClassName = substr($sagaContext->message::class, strrpos($sagaContext->message::class, '\\') + 1);

        if ($this->isTimeoutMessage($sagaContext->message)) {
            $state = $this->sagaProvider->findStateBySagaId($sagaContext->sagaId, $this->stateClass, $mapping);
        } else {
            $state = $this->sagaProvider->findStateByCorrelationId($sagaContext->message, $this->stateClass, $mapping);
        }
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
            $state = ($this->stateClass)::create();
            $state->id = new Ulid();
            // TODO : other metadata like originator ?

            if (!property_exists($state, $mapping->stateCorrelationIdField())) {
                throw new \RuntimeException('Saga state ' . $this->stateClass . ' does not have the mapped property ' . $mapping->stateCorrelationIdField() . '. Please check your Saga mapping.');
            }
            if (!property_exists($sagaContext->message, $mapping->messageCorrelationIdField($sagaContext->message::class))) {
                throw new \RuntimeException('Saga message' . $sagaContext->message::class . ' does not have the mapped property ' . $mapping->messageCorrelationIdField($sagaContext->message::class) . '. Please check your Saga mapping.');
            }
            // TODO : Property Accessor ?
            $state->{$mapping->stateCorrelationIdField()} = $sagaContext->message->{$mapping->messageCorrelationIdField($sagaContext->message::class)};

            $this->logger->info('A new Saga {sagaName} has started.', [
                'sagaName' => static::class,
                'sagaId' => $state->id,
                'correlation_id' => $state->{$mapping->stateCorrelationIdField()},
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
            'correlation_id' => $state->{$mapping->stateCorrelationIdField()},
            'message' => $sagaContext->message,
        ]);

        // TODO : options for (enabling) Backoff. (passing options to parent::__construct() ? or wither injection ? and/or override method for strategy backoff, e.g. "only for the message X")
        // https://docs.particular.net/tutorials/nservicebus-step-by-step/5-retrying-errors/#automatic-retries
        $backoff = new Backoff(5, new ExponentialStrategy(100), 10000, true);
        $backoff->setErrorHandler(function (\Throwable $exception, int $attempt, int $maxAttempts) use ($handlerName, $state, $mapping, $sagaContext): void {
            if (!$this->logger) {
                return;
            }
            $this->logger->warning("Retry Saga {sagaName} handler {handlerName} {attempt} / {maxAttempts} : {$exception->getMessage()}", [
                'exception' => $exception,
                'attempt' => $attempt,
                'maxAttempts' => $maxAttempts - 1,
                'handlerName' => $handlerName,
                'sagaName' => static::class,
                'sagaId' => $state->id,
                'correlation_id' => $state->{$mapping->stateCorrelationIdField()},
                'message' => $sagaContext->message,
            ]);
        });
        try {
            $backoff->run(function () use ($handlerName, $sagaContext, $state) {
                $this->$handlerName($sagaContext->message, $state, $sagaContext);
            });
        } catch (\Throwable $e) {
            $this->logger->error('The Saga {sagaName} handler {handlerName} has failed when handling '.$sagaContext->message::class.' : ' . $e->getMessage(), [
                'exception' => $e,
                'handlerName' => $handlerName,
                'sagaName' => static::class,
                'sagaId' => $state->id->toRfc4122(),
                'correlation_id' => $state->{$mapping->stateCorrelationIdField()},
                'message' => $sagaContext->message,
            ]);
            throw new FailedSagaHandlerException("The Saga ".static::class." handler {$handlerName} has failed when handling ".$sagaContext->message::class." : {$e->getMessage()}",
                $handlerName,
                static::class,
                $state->id,
                $state->{$mapping->stateCorrelationIdField()},
                $sagaContext->message,
                previous: $e,
            );
        }

//        try {
//            $this->$handlerName($sagaContext->message, $state, $sagaContext);
//        } catch (\Throwable $e) {
//            // TODO : on fait quoi avec le State si le message est full erreur ?
//            $this->logger->error('The Saga {sagaName} has failed when handling {message} : '.$e->getMessage(), [
//                'sagaName' => static::class,
//                'sagaId' => $state->id,
//                'correlation_id' => $state->{$mapping->stateCorrelationIdField()},
//                'message' => $sagaContext->message,
//                'exception' => $e,
//            ]);
//            throw $e;
//        }

        if (!$sagaContext->isCompleted()) {
            $this->sagaProvider->saveState($state);
        } else {
            $this->sagaProvider->deleteState($state);
            $this->logger->info('The Saga {sagaName} has been completed.', [
                'sagaName' => static::class,
                'sagaId' => $state->id,
                'correlation_id' => $state->{$mapping->stateCorrelationIdField()},
            ]);
        }
    }


    protected function publish(MessageBusInterface $bus, SagaState $state, object $message, array $stamps = []): void
    {
        $bus->dispatch($message, [new SagaContextStamp($state->id), ...$stamps]);
    }

    protected function timeout(MessageBusInterface $bus, SagaState $state, \DateInterval $delay, object $message, array $stamps = []): void
    {
        $this->publish($bus, $state, $message, [DelayStamp::delayFor($delay), ...$stamps]);
    }

    abstract protected function canStartSaga(object $message): bool;

    abstract protected static function configureMapping(): SagaMapper;
}
