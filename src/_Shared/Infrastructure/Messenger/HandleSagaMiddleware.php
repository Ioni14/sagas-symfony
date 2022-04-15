<?php

namespace Shared\Infrastructure\Messenger;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Shared\Application\SagaContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class HandleSagaMiddleware implements MiddlewareInterface
{
    use LoggerAwareTrait;

    private HandlersLocatorInterface $handlersLocator;

    public function __construct(HandlersLocatorInterface $handlersLocator)
    {
        $this->handlersLocator = $handlersLocator;
        $this->logger = new NullLogger();
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $handler = null;
        $message = $envelope->getMessage();

        $context = [
            'message' => $message,
            'class' => \get_class($message),
        ];

        $exceptions = [];
        foreach ($this->handlersLocator->getHandlers($envelope) as $handlerDescriptor) {
            if ($this->messageHasAlreadyBeenHandled($envelope, $handlerDescriptor)) {
                continue;
            }

            try {
                $handler = $handlerDescriptor->getHandler();



                // TODO : PR Symfony pour $message = $this->eventDispatcher->dispatch(new BeforeHandleMessage($message, $envelope));
                // => on peut listen pour return new SagaContext($message, $sagaContextStamp->sagaId);

                // TODO : une autre idée à l'arrache : un handler dynamique $handler = [new Saga(stamp->$sagaId, ...), '__invoke'];

                /** @var SagaContextStamp $sagaContextStamp */
                if ($sagaContextStamp = $envelope->last(SagaContextStamp::class)) {
                    $result = $handler(new SagaContext($message, $sagaContextStamp->sagaId));
                } else {
                    $result = $handler($message);
                }

                $handledStamp = HandledStamp::fromDescriptor($handlerDescriptor, $result);
                $envelope = $envelope->with($handledStamp);
                $this->logger->info('Message {class} handled by {handler}', $context + ['handler' => $handledStamp->getHandlerName()]);
            } catch (\Throwable $e) {
                $exceptions[] = $e;
            }
        }

        if (null === $handler) {
            $this->logger->info('No handler for message {class}', $context);
        }

        if (\count($exceptions)) {
            throw new HandlerFailedException($envelope, $exceptions);
        }

        return $stack->next()->handle($envelope, $stack);
    }

    private function messageHasAlreadyBeenHandled(Envelope $envelope, HandlerDescriptor $handlerDescriptor): bool
    {
        /** @var HandledStamp $stamp */
        foreach ($envelope->all(HandledStamp::class) as $stamp) {
            if ($stamp->getHandlerName() === $handlerDescriptor->getName()) {
                return true;
            }
        }

        return false;
    }
}
