<?php

namespace Tests\Acceptance\Saga\Message;

class ImpossibleStateMessage
{
    public function __construct(public int $id)
    {
    }
}
