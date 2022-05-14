<?php

namespace Shipping\Application;

use Shared\Application\SagaContext;
use Shared\Infrastructure\Messenger\SagaContextStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

trait SagaPublishTrait
{
    final protected function publish(MessageBusInterface $bus, object $message, SagaContext $context, array $stamps = []): void
    {
        $bus->dispatch($message, [new SagaContextStamp($context->id), ...$stamps]);
    }

    final protected function timeout(MessageBusInterface $bus, \DateInterval $delay, object $message, SagaContext $context, array $stamps = []): void
    {
        $this->publish($bus, $message, $context, [DelayStamp::delayFor($delay), ...$stamps]);
    }
}
