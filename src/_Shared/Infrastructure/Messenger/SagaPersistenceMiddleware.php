<?php

namespace Shared\Infrastructure\Messenger;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Shared\Application\Saga;
use Shared\Application\SagaManager;
use Shared\Application\SagaPersisterInterface;
use Shared\Application\SagaState;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Ulid;

class SagaPersistenceMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param Saga[] $sagaHandlerPrototypes
     */
    public function __construct(
        private readonly SagaPersisterInterface $sagaPersister,
        private readonly SagaManager $sagaManager,
        private readonly iterable $sagaHandlerPrototypes,
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
            if (!$sagaHandlerPrototype instanceof Saga) {
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
                throw new UnableToFindSagaStateException($sagaId, $sagaHandlerClass, $message);
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

                // TODO : Property Accessor ?
                $stateCorrelIdField = $mapping->stateCorrelationIdField();
                if (!property_exists($state, $stateCorrelIdField)) {
                    throw new \RuntimeException('Saga state ' . $stateClass . ' does not have the mapped property ' . $stateCorrelIdField . '. Please check your Saga mapping.');
                }
                if (!property_exists($message, $messageCorrelIdField)) {
                    throw new \RuntimeException('Saga message' . $message::class . ' does not have the mapped property ' . $messageCorrelIdField . '. Please check your Saga mapping.');
                }
                $state->{$stateCorrelIdField} = $message->{$messageCorrelIdField};

                $this->logger->info('A new Saga {sagaName} has started.', [
                    'sagaName' => static::class,
                    'sagaId' => $state->id()->toRfc4122(),
                    'correlation_id' => $state->{$stateCorrelIdField},
                ]);
            }

            $sagaHandler = $sagaHandlerPrototype->withSagaContext($state);

            // TODO : OK que tous les handlers partagent la même db transaction ou autre ? (cf. SagaManager::__invoke())
            $this->sagaManager->addSaga($message, $sagaHandler);
        }

        $envelope = $stack->next()->handle($envelope, $stack);

        foreach ($this->sagaManager->getSagaHandlersFor($message) as $sagaHandler) {
            $state = $sagaHandler->state();
            if (!$sagaHandler->isCompleted()) {
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
