<?php

namespace Shared\Application;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Shared\Infrastructure\Messenger\SagaContextStamp;
use STS\Backoff\Backoff;
use STS\Backoff\Strategies\ExponentialStrategy;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Uid\AbstractUid;

/**
 * @template TState
 */
abstract class Saga implements LoggerAwareInterface
{
    /**
     * TODO : inject good logger channel ?
     */
    use LoggerAwareTrait;

    protected AbstractUid $id;
    /** @var TState */
    protected SagaState $state;
    private bool $completed = false;

    private static array $methodHandlers = [];

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * @internal
     * @return TState
     */
    final public function state(): SagaState
    {
        return $this->state;
    }

    /**
     * @internal
     * @param TState $state
     */
    final public function withSagaContext(SagaState $state): self
    {
        $that = clone $this;
        $that->id = $state->id();
        $that->state = $state;

        return $that;
    }

    /**
     * @return class-string<TState>
     */
    abstract public static function stateClass(): string;

    abstract public static function canStartSaga(object $message): bool;

    abstract public static function mapping(): SagaMapper;

    abstract public static function getHandledMessages(): array;

    /**
     * @internal
     */
    final public function __invoke(object $message): void
    {
        $mapping = static::mapping();

        if (!isset(self::$methodHandlers[$message::class])) {
            $methodToInvoke = null;
            foreach ((new \ReflectionClass(static::class))->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
                $attributes = $method->getAttributes(SagaHandler::class);
                if (!$attributes) {
                    continue;
                }
                $params = $method->getParameters();

                $paramMessageType = $params[0]?->getType();
                $messageTypes = [];
                if ($paramMessageType instanceof \ReflectionNamedType) {
                    $messageTypes[] = $paramMessageType->getName();
                } elseif ($paramMessageType instanceof \ReflectionUnionType) {
                    foreach ($paramMessageType->getTypes() as $type) {
                        $messageTypes[] = $type->getName();
                    }
                }
                foreach ($messageTypes as $messageType) {
                    self::$methodHandlers[$messageType] = $method->getName();
                    if (is_a($message, $messageType, true)) {
                        $methodToInvoke = $method->getName();
                    }
                }
            }

            if ($methodToInvoke === null) {
                $shortClassName = substr($message::class, strrpos($message::class, '\\') + 1);
                $methodToInvoke = 'handle' . $shortClassName;
                if (!method_exists($this, $methodToInvoke)) {
                    throw new \RuntimeException(sprintf("Cannot handle %s by Saga %s : no method $methodToInvoke or attribute %s on a public or protected method with message typehint on first parameter.", $message::class, static::class, SagaHandler::class));
                }
            }

            self::$methodHandlers[$message::class] = $methodToInvoke;
        }

        $methodToInvoke = self::$methodHandlers[$message::class];

        $this->logger->info('The Saga {sagaName} handles {message}.', [
            'sagaName' => static::class,
            'sagaId' => $this->id,
            'correlation_id' => $this->state->{$mapping->stateCorrelationIdField()},
            'message' => $message,
        ]);

        // TODO : options for (enabling) Backoff. (passing options to parent::__construct() ? or wither injection ? and/or override method for strategy backoff, e.g. "only for the message X")
        // https://docs.particular.net/tutorials/nservicebus-step-by-step/5-retrying-errors/#automatic-retries
        $backoff = new Backoff(5, new ExponentialStrategy(100), 10000, true);
        $backoff->setErrorHandler(function (\Throwable $exception, int $attempt, int $maxAttempts) use ($methodToInvoke, $mapping, $message): void {
            if (!$this->logger) {
                return;
            }
            $this->logger->warning("Retry Saga {sagaName} handler {handlerName} {attempt} / {maxAttempts} : {$exception->getMessage()}", [
                'exception' => $exception,
                'attempt' => $attempt,
                'maxAttempts' => $maxAttempts - 1,
                'handlerName' => $methodToInvoke,
                'sagaName' => static::class,
                'sagaId' => $this->id,
                'correlation_id' => $this->state->{$mapping->stateCorrelationIdField()},
                'message' => $message,
            ]);
        });
        try {
            $backoff->run(function () use ($methodToInvoke, $message) {
                $this->$methodToInvoke($message);
            });
        } catch (\Throwable $e) {
            $this->logger->error('The Saga {sagaName} handler {handlerName} has failed when handling ' . $message::class . ' : ' . $e->getMessage(), [
                'exception' => $e,
                'handlerName' => $methodToInvoke,
                'sagaName' => static::class,
                'sagaId' => $this->id->toRfc4122(),
                'correlation_id' => $this->state->{$mapping->stateCorrelationIdField()},
                'message' => $message,
            ]);
            throw new FailedSagaHandlerException("The Saga " . static::class . " handler {$methodToInvoke} has failed when handling " . $message::class . " : {$e->getMessage()}",
                $methodToInvoke,
                static::class,
                $this->state->id(),
                $this->state->{$mapping->stateCorrelationIdField()},
                $message,
                previous: $e,
            );
        }
    }

    final protected function markAsCompleted(): void
    {
        $this->completed = true;
    }

    final public function isCompleted(): bool
    {
        return $this->completed;
    }

    final protected function publish(MessageBusInterface $bus, object $message, array $stamps = []): void
    {
        $bus->dispatch($message, [new SagaContextStamp($this->id), ...$stamps]);
    }

    final protected function timeout(MessageBusInterface $bus, \DateInterval $delay, object $message, array $stamps = []): void
    {
        $this->publish($bus, $message, [DelayStamp::delayFor($delay), ...$stamps]);
    }
}
