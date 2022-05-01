<?php

namespace Tests\Acceptance\Saga\State;

use Shared\Application\SagaState;

class TwoHandlerState extends SagaState
{
    public int $myId;
}
