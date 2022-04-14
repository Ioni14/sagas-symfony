<?php

namespace Shipping\Application;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Shipping\Application\Command\ShipWithAlpine;
use Shipping\Application\Event\ShipmentAcceptedByAlpine;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'command.bus')]
class ShipWithAlpineHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const MAX_TIME_MIGHT_RESPONSE = 30;

    public function __construct(
        private MessageBusInterface $eventBus,
    ) {
        $this->logger = new NullLogger();
    }

    public function __invoke(ShipWithAlpine $command): void
    {
        $waitingTime = mt_rand(1, self::MAX_TIME_MIGHT_RESPONSE);

        $this->logger->info('ShipWithAlpineHandler: Delaying Order [{orderId}] {waitingTime} seconds.', [
            'orderId' => $command->orderId,
            'waitingTime' => $waitingTime,
        ]);

        sleep($waitingTime); // fake call to webservice

        // TODO : ajouter le concept de "Reply" réponse à un message (envoyer un message à l'expéditeur)
        // Il faut ajouter le SagaContextStamp avec son sagaId
//        $this->eventBus->dispatch(new ShipmentAcceptedByAlpine());

        // workaround en attendant
        $this->eventBus->dispatch(new ShipmentAcceptedByAlpine($command->orderId));
    }
}
