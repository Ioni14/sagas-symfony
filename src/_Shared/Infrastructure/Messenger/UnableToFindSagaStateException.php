<?php

namespace Shared\Infrastructure\Messenger;

use Symfony\Component\Uid\AbstractUid;

class UnableToFindSagaStateException extends \RuntimeException
{
    public AbstractUid $sagaId;
    public string $sagaHandlerClass;
    public object $sagaMessage;

    public function __construct(AbstractUid $sagaId, string $sagaHandlerClass, object $sagaMessage, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->sagaId = $sagaId;
        $this->sagaHandlerClass = $sagaHandlerClass;
        $this->sagaMessage = $sagaMessage;
    }
}
