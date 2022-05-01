<?php

namespace Tests\Acceptance\Saga\Message;

class BadStateMappingMessage
{
    public function __construct(public int $id)
    {
    }
}
