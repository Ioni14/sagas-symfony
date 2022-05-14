<?php

namespace Shipping\Application;

/**
 * @template TState
 */
interface SagaInterface
{
    /**
     * @return class-string<TState>
     */
    public static function stateClass(): string;
}
