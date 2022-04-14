<?php

namespace Shared\Application;

use Symfony\Component\Uid\Ulid;

class SagaState
{
    /** unique identifier of the saga. */
    public Ulid $id;
    /** the endpoint that started the saga. */
    public ?string $originator = null;
    /** id of the message that started the saga. */
    public ?string $originalMessageId = null;
}
