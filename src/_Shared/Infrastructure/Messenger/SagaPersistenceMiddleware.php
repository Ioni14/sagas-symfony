<?php

namespace Shared\Infrastructure\Messenger;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Shared\Application\BadSagaMappingException;
use Shared\Application\Saga;
use Shared\Application\SagaManager;
use Shared\Application\SagaPersistenceInterface;
use Shared\Application\SagaState;
use Shipping\Application\SagaInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Uid\Ulid;

class SagaPersistenceMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param iterable<SagaInterface> $sagaHandlerPrototypes
     */
    public function __construct(
        private readonly SagaPersistenceInterface $sagaPersister,
        private readonly SagaManager              $sagaManager,
        private readonly iterable                 $sagaHandlerPrototypes,
    ) {
        $this->logger = new NullLogger();
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$envelope->all(ReceivedStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        $message = $envelope->getMessage();

        $sagaId = null;
        /** @var SagaContextStamp $sagaContextStamp */
        if ($sagaContextStamp = $envelope->last(SagaContextStamp::class)) {
            $sagaId = $sagaContextStamp->sagaId;
        }

        foreach ($this->sagaHandlerPrototypes as $sagaHandlerPrototype) {
            if (!$sagaHandlerPrototype instanceof SagaInterface) {
                continue;
            }

            if (!in_array($message::class, $sagaHandlerPrototype::getHandledMessages(), true)) {
                continue;
            }

            $mapping = $sagaHandlerPrototype::mapping();

            $sagaHandlerClass = $sagaHandlerPrototype::class;
            $messageCorrelIdField = $mapping->messageCorrelationIdField($message::class);
            if ($sagaId !== null) {
                $state = $this->sagaPersister->findStateBySagaId($sagaId, $sagaHandlerClass);
            } elseif ($messageCorrelIdField !== null) {
                $state = $this->sagaPersister->findStateByCorrelationId($message, $sagaHandlerClass);
            } else {
                $ex = new UnableToFindSagaStateException($sagaHandlerClass, $message, sprintf('Cannot determine how to find the saga state of %s for message %s. Please check the Saga mapping.', $sagaHandlerClass, $message::class));
                throw new UnrecoverableMessageHandlingException($ex->getMessage(), $ex->getCode(), $ex);
            }

            if (!$state) {
                // nouveau Saga
                if (!$sagaHandlerPrototype::canStartSaga($message)) {
                    // un message de ce Saga est arrivé et n'est pas désigné comme départ
                    // cela signifie que le Saga a été terminé et qu'un message de ce même Saga est arrivé ensuite
                    // => ignore
//                    throw new SagaNotFoundException();
                    // TODO : call implement SagaNotFoundHandlerInterface::onSagaNotFound() ?
                    // TODO : gère multiple Saga pour un même type de message ?
                    $this->logger->info('No saga {sagaName} found for message {message}, ignoring since the saga has been marked as complete before the timeout fired.', [
                        'message' => $message,
                        'sagaName' => $sagaHandlerClass,
                        'sagaId' => $sagaId?->toRfc4122(),
                    ]);

                    continue;
                }

                $stateClass = $sagaHandlerPrototype::stateClass();
                if (!is_a($stateClass, SagaState::class, true)) {
                    // TODO : testable at compile ?
                    throw new \RuntimeException('Saga state class must be a subclass of SagaState. Please check the stateClass() method.');
                }

                $state = ($stateClass)::create(new Ulid());
                // TODO : other metadata like originator ?

                $stateCorrelIdField = $mapping->stateCorrelationIdField();

                $accessor = PropertyAccess::createPropertyAccessor();
                try {
                    $correlationValue = $accessor->getValue($message, $messageCorrelIdField);
                } catch (NoSuchPropertyException $e) {
                    $ex = new BadSagaMappingException('Saga message ' . $message::class . ' does not have the mapped property ' . $messageCorrelIdField . '. Please check your Saga mapping.', previous: $e);
                    throw new UnrecoverableMessageHandlingException($ex->getMessage(), $ex->getCode(), $ex);
                }
                try {
                    $accessor->setValue($state, $stateCorrelIdField, $correlationValue);
                } catch (NoSuchPropertyException $e) {
                    $ex = new BadSagaMappingException('Saga state ' . $stateClass . ' does not have the mapped property ' . $stateCorrelIdField . '. Please check your Saga mapping.', previous: $e);
                    throw new UnrecoverableMessageHandlingException($ex->getMessage(), $ex->getCode(), $ex);
                }

                $this->logger->info('A new Saga {sagaName} has started.', [
                    'sagaName' => static::class,
                    'sagaId' => $state->id()->toRfc4122(),
                    'correlation_id' => $correlationValue,
                ]);
            }

            if ($sagaHandlerPrototype instanceof Saga) {
                $sagaHandler = $sagaHandlerPrototype->withSagaContext($state);
            } else {
                $sagaHandler = $sagaHandlerPrototype;
            }

            // TODO : OK que tous les handlers partagent la même db transaction ou autre ? (cf. SagaManager::__invoke())
            $this->sagaManager->addSaga($message, $sagaHandler, $state);
        }

        $envelope = $stack->next()->handle($envelope, $stack);

        foreach ($this->sagaManager->getSagaHandlersFor($message) as ['saga' => $sagaHandler, 'context' => $context]) {
            $state = $context->state;
            if (!$context->isCompleted()) {
                $this->sagaPersister->saveState($state, $message, $sagaHandler::class);
            } else {
                if (!$state->isNew()) {
                    $this->sagaPersister->deleteState($state, $sagaHandler::class);
                }
                $mapping = $sagaHandler::mapping();
                $this->logger->info('The Saga {sagaName} has been completed.', [
                    'sagaName' => $sagaHandler::class,
                    'sagaId' => $state->id()->toRfc4122(),
                    'correlation_id' => $state->{$mapping->stateCorrelationIdField()},
                ]);
            }
        }

        return $envelope;
    }
}
