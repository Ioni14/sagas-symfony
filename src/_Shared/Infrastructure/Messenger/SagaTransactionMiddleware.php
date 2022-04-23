<?php

namespace Shared\Infrastructure\Messenger;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Shared\Application\SagaPersisterInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class SagaTransactionMiddleware implements MiddlewareInterface
{
    use LoggerAwareTrait;

    public function __construct(private SagaPersisterInterface $sagaPersister)
    {
        $this->logger = new NullLogger();
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$envelope->all(ReceivedStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        return $this->sagaPersister->transactional(function () use ($stack, $envelope) {
            return $stack->next()->handle($envelope, $stack);
        });
    }
}
