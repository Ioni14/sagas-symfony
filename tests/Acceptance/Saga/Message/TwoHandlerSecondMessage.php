<?php

namespace Tests\Acceptance\Saga\Message;

class TwoHandlerSecondMessage
{
    public function __construct(public int $secondId)
    {
    }
}
