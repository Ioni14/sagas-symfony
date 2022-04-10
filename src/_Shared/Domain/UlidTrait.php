<?php

namespace Shared\Domain;

use Symfony\Component\Uid\Ulid;

trait UlidTrait
{
    public function __construct(
        private Ulid $id,
    ) {
    }

    public static function fromString(string $uid): self
    {
        return new self(Ulid::fromString($uid));
    }

    public function val(): Ulid
    {
        return $this->id;
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }

    public function __toString(): string
    {
        return $this->id->toRfc4122();
    }
}
