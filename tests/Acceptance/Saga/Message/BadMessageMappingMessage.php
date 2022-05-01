<?php

namespace Tests\Acceptance\Saga\Message;

class BadMessageMappingMessage
{
    public function __construct(public int $id)
    {
    }
}
