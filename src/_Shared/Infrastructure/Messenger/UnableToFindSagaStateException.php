<?php

namespace Shared\Infrastructure\Messenger;

use Symfony\Component\Uid\AbstractUid;

class UnableToFindSagaStateException extends \RuntimeException
{
    public string $sagaHandlerClass;
    public object $sagaMessage;

    public function __construct(string $sagaHandlerClass, object $sagaMessage, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->sagaHandlerClass = $sagaHandlerClass;
        $this->sagaMessage = $sagaMessage;
    }
}
