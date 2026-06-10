<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Custom;

use Equidna\StagHerd\Contracts\CustomPaymentHandler;
use Equidna\StagHerd\Exceptions\InvalidPaymentMethodException;

class CustomPaymentHandlerRegistry
{
    /**
     * @var array<string, CustomPaymentHandler>
     */
    private array $handlers = [];

    public function register(CustomPaymentHandler $handler): void
    {
        $this->handlers[strtolower($handler->getMethod())] = $handler;
    }

    public function get(string $method): CustomPaymentHandler
    {
        $method = strtolower($method);

        if (! isset($this->handlers[$method])) {
            throw InvalidPaymentMethodException::forMethod($method);
        }

        return $this->handlers[$method];
    }

    public function has(string $method): bool
    {
        return isset($this->handlers[strtolower($method)]);
    }
}
