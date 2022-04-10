<?php

namespace Shipping\Application;

use Billing\Domain\Event\OrderBilled;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Sales\Domain\Event\OrderPlaced;
use Shipping\Domain\Command\ShipOrder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

/**
 * An order is shipped when it is both accepted and billed.
 */
#[AsMessageHandler(bus: 'event.bus')]
class ShippingPolicy
{
    public function __construct(
        private LoggerInterface $logger,
        private MessageBusInterface $commandBus,
        private Connection $dbConnection,
    ) {
    }

    /**
     * TODO : code technique à extraire (parent class ?).
     */
    public function __invoke(OrderBilled|OrderPlaced $event): void
    {
        $handlerName = 'handle'.substr($event::class, strrpos($event::class, '\\') + 1);

        $state = $this->findState($event->getOrderId());
        if (!$state) {
            if (!$this->canStartSaga($event)) {
                // discard
                return;
            }
            $state = new ShippingPolicyState(Ulid::fromString($event->getOrderId()));
            $state->id = Uuid::v4();
        }

        $result = $this->$handlerName($event, $state);

        if (!$state->complete) {
            $this->saveState($state);
        }
    }

    /**
     * TODO : code à technique à extraire/configurer (parent class ?).
     *
     * l'objectif est de retrouver le state du saga via un correlation id entre chaque messages et le saga state
     */
    private function findState(string $orderId): ?ShippingPolicyState
    {
        $row = $this->dbConnection->fetchAssociative('SELECT * FROM shipping_policy_state WHERE correlation_order_id = :order_id', [
            'order_id' => Ulid::fromString($orderId)->toBinary(),
        ]);
        if (!$row) {
            return null;
        }

        return ShippingPolicyState::fromRow($row);
    }

    /**
     * TODO : code technique à extraire (parent class ?).
     */
    private function saveState(?ShippingPolicyState $state): void
    {
        $this->dbConnection->executeStatement(<<<SQL
                INSERT INTO shipping_policy_state (id, correlation_order_id, state)
                VALUES (:id, :correlation_order_id, :state) ON DUPLICATE KEY UPDATE state = :state
            SQL, $state->toRow());
    }

    /**
     * TODO : code à technique à extraire/configurer (parent class ?).
     */
    private function canStartSaga(object $event): bool
    {
        return $event instanceof OrderPlaced || $event instanceof OrderBilled;
    }

    private function handleOrderBilled(OrderBilled $event, ShippingPolicyState $state)
    {
        $this->logger->info('Received OrderBilled, orderId={orderId}', [
            'orderId' => $event->getOrderId(),
        ]);

        $state->orderBilled = true;

        return $this->processOrder($state);
    }

    private function handleOrderPlaced(OrderPlaced $event, ShippingPolicyState $state)
    {
        $this->logger->info('Received OrderPlaced, orderId={orderId}', [
            'orderId' => $event->getOrderId(),
        ]);

        $state->orderPlaced = true;

        return $this->processOrder($state);
    }

    private function processOrder(ShippingPolicyState $state)
    {
        if ($state->orderPlaced && $state->orderBilled) {
            $this->commandBus->dispatch(new ShipOrder($state->orderId->toRfc4122()));
            $this->markAsComplete($state);
        }
    }

    /**
     * TODO : code à technique à extraire/configurer (parent class ?).
     */
    private function markAsComplete(ShippingPolicyState $state): void
    {
        $state->complete = true;
        $this->dbConnection->delete('shipping_policy_state', [
            'id' => Uuid::fromString($state->id)->toBinary(),
        ]);
    }
}

/**
 * @internal
 */
final class ShippingPolicyState
{
    public ?Uuid $id = null;
    /** the endpoint that started the saga. */
    public ?string $originator = null;
    /** id of the message that started the saga. */
    public ?string $originalMessageId = null;
    public bool $complete = false;

    public function __construct(
        public Ulid $orderId,
        public bool $orderPlaced = false,
        public bool $orderBilled = false,
    ) {
    }

    public static function fromRow(array $row): self
    {
        $state = json_decode($row['state'], false);

        $self = new self(
            Ulid::fromBinary($row['correlation_order_id']),
            $state->order_placed ?? false,
            $state->order_billed ?? false,
        );
        $self->id = Uuid::fromBinary($row['id']);

        return $self;
    }

    public function toRow(): array
    {
        return [
            'id' => $this->id->toBinary(),
            'correlation_order_id' => $this->orderId->toBinary(),
            'state' => json_encode([
                'order_placed' => $this->orderPlaced,
                'order_billed' => $this->orderBilled,
            ]),
        ];
    }
}
