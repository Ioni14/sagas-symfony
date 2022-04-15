<?php

namespace Shared\Application;

use Doctrine\DBAL\Connection;
use Shared\Application\Saga;
use Shared\Application\SagaContext;
use Shared\Application\SagaState;
use Shipping\Application\Command\ShipWithAlpine;
use Shipping\Application\Command\ShipWithMaple;
use Shipping\Application\Event\ShipmentAcceptedByAlpine;
use Shipping\Application\Event\ShipmentAcceptedByMaple;
use Shipping\Application\Event\ShipmentFailed;
use Shipping\Application\ShipOrderWorkflow;
use Shipping\Application\Timeout\ShippingEscalation;
use Shipping\Domain\Command\ShipOrder;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;

class SagaFactory implements MessageHandlerInterface
{
//    public function __construct(
//        protected MessageBusInterface $commandBus,
//        protected MessageBusInterface $eventBus,
//    ) {
//    }
//
//    public function __invoke($message): void
//    {
//        $state = [];
//        (new ShipOrderWorkflow($state, $context))($message);
//    }
}
