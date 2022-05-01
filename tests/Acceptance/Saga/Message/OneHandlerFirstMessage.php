<?php

namespace Tests\Acceptance\Saga\Message;

class OneHandlerFirstMessage
{
    public function __construct(public string $id)
    {
    }
}
