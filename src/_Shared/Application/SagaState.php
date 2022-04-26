<?php

namespace Shared\Application;

use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Uid\Ulid;

abstract class SagaState
{
    #[Ignore]
    protected Ulid $id;

    #[Ignore]
    private bool $isNew = false;

    final private function __construct()
    {
    }

    final public static function create(Ulid $id): static
    {
        $instance = new static();
        $instance->id = $id;
        $instance->isNew = true;

        return $instance;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    #[Ignore]
    public function isNew(): bool
    {
        return $this->isNew;
    }
}
