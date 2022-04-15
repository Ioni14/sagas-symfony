<?php

namespace Shared\Application;

use Billing\Domain\Event\OrderBilled;
use Sales\Domain\Event\OrderPlaced;

class SagaMapperBuilder
{
//    private \Closure $stateCorrelationFunc;
//    /** @var \Closure[] */
//    private array $messageCorrelationFuncs = [];
    private string $stateCorrelationIdField;
    private array $messageCorrelationIdFields = [];

    private function __construct()
    {
    }

    public static function stateCorrelationIdField(string $field): self
    {
        $self = new self;
        $self->stateCorrelationIdField = $field;

        return $self;
    }
    public function messageCorrelationIdField(string $messageClass, string $field): self
    {
        $this->messageCorrelationIdFields[$messageClass] = $field;

        return $this;
    }

    public function build(): SagaMapper
    {
        return new SagaMapper(
            $this->stateCorrelationIdField,
            $this->messageCorrelationIdFields
        );
    }
//    /**
//     * @param callable $stateCorrelationFunc fn(SagaState) => <correlation id>
//     */
//    public static function mapState(callable $stateCorrelationFunc): self
//    {
//        $self = new self();
//
//        $self->stateCorrelationFunc = $stateCorrelationFunc(...);
//
//        return $self;
//    }
//
//    /**
//     * @param callable $messageCorrelationFunc fn(message) => <correlation id>
//     */
//    public function mapMessage(string $messageClass, callable $messageCorrelationFunc): self
//    {
//        $this->messageCorrelationFuncs[$messageClass] = $messageCorrelationFunc(...);
//
//        return $this;

//    }
//    public function build(): SagaMapper
//    {
//        return new SagaMapper(
//            $this->stateCorrelationFunc,
//            $this->messageCorrelationFuncs
//        );
//    }
}
