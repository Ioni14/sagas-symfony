<?php

namespace Shared\Domain;

use Doctrine\ORM\Mapping\Version;

abstract class AggregateRoot
{
    #[Version]
    private int $version = 1;

    private array $events = [];

    protected function addEvent(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return DomainEvent[]
     */
    public function retrieveAndClearEvents(): iterable
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
