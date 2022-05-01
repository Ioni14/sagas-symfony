<?php

namespace Tests\Acceptance\Saga\State;

use Shared\Application\SagaState;

class IntegerState extends SagaState
{
    public int $myId;
}
