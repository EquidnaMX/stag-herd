<?php

namespace Equidna\StagHerd\Support;

use Equidna\StagHerd\Contracts\PaymentMethodHandler;
use Equidna\StagHerd\Exceptions\InvalidPaymentMethodException;

final class PaymentMethodHandlerRegistry
{
    /**
     * @var array<string, PaymentMethodHandler>
     */
    private array $handlers = [];

    public function register(PaymentMethodHandler $handler): void
    {
        $this->handlers[strtolower($handler->getMethod())] = $handler;
    }

    public function get(string $method): PaymentMethodHandler
    {
        $method = strtolower($method);

        if (!isset($this->handlers[$method])) {
            throw InvalidPaymentMethodException::forMethod($method);
        }

        return $this->handlers[$method];
    }

    public function has(string $method): bool
    {
        return isset($this->handlers[strtolower($method)]);
    }
}
