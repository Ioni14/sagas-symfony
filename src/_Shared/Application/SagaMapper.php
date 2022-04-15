<?php

namespace Shared\Application;

class SagaMapper
{
    /**
     * @param string[] $messageCorrelationIdFields
     */
    public function __construct(
        private string $stateCorrelationIdField,
        private array $messageCorrelationIdFields = [],
    ) {
    }

    public function stateCorrelationIdField(): string
    {
        return $this->stateCorrelationIdField;
    }

    public function messageCorrelationIdField(string $messageClass): ?string
    {
        return $this->messageCorrelationIdFields[$messageClass] ?? null;
    }
}
