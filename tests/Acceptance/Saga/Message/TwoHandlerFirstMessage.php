<?php

namespace Tests\Acceptance\Saga\Message;

class TwoHandlerFirstMessage
{
    public function __construct(public int $firstId)
    {
    }
}
