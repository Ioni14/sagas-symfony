<?php

namespace Shared\Application;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Shipping\Application\SagaInterface;
use STS\Backoff\Backoff;
use STS\Backoff\Strategies\ExponentialStrategy;
use Symfony\Contracts\Service\ResetInterface;

class SagaManager implements ResetInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private static array $methodHandlers = [];

    /**
     * @var SagaInterface[][]
     */
    private array $sagas = [];

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    public function addSaga(object $message, SagaInterface $saga, SagaState $state): void
    {
        $context = new SagaContext($state->id(), $state);
        $this->sagas[$this->digestMessage($message)][$saga::class] = ['saga' => $saga, 'context' => $context];
    }

    public function __invoke(object $message): void
    {
        /**
         * @var SagaContext $context
         */
        foreach ($this->sagas[$this->digestMessage($message)] ?? [] as ['saga' => $saga, 'context' => $context]) {
            $mapping = $saga::mapping();

            $method = $this->findHandleMethod($saga::class, $message);
            $state = $context->state;

            $this->logger->info('The Saga {sagaName} handles {message}.', [
                'sagaName' => static::class,
                'sagaId' => $state->id(),
                'correlation_id' => $state->{$mapping->stateCorrelationIdField()},
                'message' => $message,
            ]);

            // TODO : options for (enabling) Backoff. (passing options to parent::__construct() ? or wither injection ? and/or override method for strategy backoff, e.g. "only for the message X")
            // https://docs.particular.net/tutorials/nservicebus-step-by-step/5-retrying-errors/#automatic-retries
            $backoff = new Backoff(5, new ExponentialStrategy(100), 10000, true);
            $backoff->setErrorHandler(function (\Throwable $exception, int $attempt, int $maxAttempts) use ($method, $mapping, $message, $state, $saga): void {
                if (!$this->logger) {
                    return;
                }
                $this->logger->warning("Retry Saga {sagaName} handler {handlerName} {attempt} / {maxAttempts} : {$exception->getMessage()}", [
                    'exception' => $exception,
                    'attempt' => $attempt,
                    'maxAttempts' => $maxAttempts - 1,
                    'handlerName' => $method,
                    'sagaName' => $saga::class,
                    'sagaId' => $state->id(),
                    'correlation_id' => $state->{$mapping->stateCorrelationIdField()},
                    'message' => $message,
                ]);
            });
            try {
                $backoff->run(static function () use ($saga, $method, $message, $context): void {
                    [$saga, $method]($message, $context);
                });
            } catch (\Throwable $e) {
                $this->logger->error('The Saga {sagaName} handler {handlerName} has failed when handling ' . $message::class . ' : ' . $e->getMessage(), [
                    'exception' => $e,
                    'handlerName' => $method,
                    'sagaName' => $saga::class,
                    'sagaId' => $state->id()->toRfc4122(),
                    'correlation_id' => $state->{$mapping->stateCorrelationIdField()},
                    'message' => $message,
                ]);
                throw new FailedSagaHandlerException("The Saga " . $saga::class . " handler {$method} has failed when handling " . $message::class . " : {$e->getMessage()}",
                    $method,
                    $saga::class,
                    $state->id(),
                    $state->{$mapping->stateCorrelationIdField()},
                    $message,
                    previous: $e,
                );
            }
        }
    }

    /**
     * @return SagaInterface[]
     */
    public function getSagaHandlersFor(object $message): iterable
    {
        return $this->sagas[$this->digestMessage($message)] ?? [];
    }

    public function reset(): void
    {
        $this->sagas = [];
    }

    /**
     * @param class-string<SagaInterface> $sagaHandlerClass
     */
    private function findHandleMethod(string $sagaHandlerClass, object $message): string
    {
        if (!isset(self::$methodHandlers[$message::class])) {
            $methodToInvoke = null;
            foreach ((new \ReflectionClass($sagaHandlerClass))->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
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
                    throw new \RuntimeException(sprintf("Cannot handle %s by Saga %s : no method $methodToInvoke or attribute %s on a public or protected method with message typehint on first parameter.", $message::class, $sagaHandlerClass, SagaHandler::class));
                }
            }

            self::$methodHandlers[$message::class] = $methodToInvoke;
        }

        return self::$methodHandlers[$message::class];
    }

    private function digestMessage(object $message): string
    {
        return md5(serialize($message));
    }
}
