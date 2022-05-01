<?php

namespace Tests\Acceptance\Saga\Message;

class NoHandlerMethodMessage
{
    public function __construct(public int $id)
    {
    }
}
