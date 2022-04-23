<?php

namespace Shared\Application;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Doctrine\ORM\Mapping\Version;
use Symfony\Component\Uid\Ulid;

#[MappedSuperclass]
abstract class SagaState
{
    /** unique identifier of the saga. */
    #[Id]
    #[Column(type: "ulid")]
    public Ulid $id;
    /** the endpoint that started the saga. */
    public ?string $originator = null;
    /** id of the message that started the saga. */
    public ?string $originalMessageId = null;

    #[Version]
    #[Column(type: "integer")]
    private int $version = 1;
    private bool $isNew = false;

    private function __construct()
    {
    }

    final public static function create(): static
    {
        $instance = new static();
        $instance->isNew = true;

        return $instance;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }
}
