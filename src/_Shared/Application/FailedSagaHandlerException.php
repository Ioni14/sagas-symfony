<?php

namespace Shared\Application;

use Symfony\Component\Uid\AbstractUid;

class FailedSagaHandlerException extends \RuntimeException
{
    public string $handlerName;
    public string $sagaName;
    public AbstractUid $sagaId;
    public mixed $correlationId;
    public object $handlerMessage;

    public function __construct(string $message, string $handlerName, string $sagaName, AbstractUid $sagaId, mixed $correlationId, object $handlerMessage, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->handlerName = $handlerName;
        $this->sagaName = $sagaName;
        $this->sagaId = $sagaId;
        $this->correlationId = $correlationId;
        $this->handlerMessage = $handlerMessage;
    }
}
