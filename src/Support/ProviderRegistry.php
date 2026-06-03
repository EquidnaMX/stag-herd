<?php

namespace Equidna\StagHerd\Support;

use Equidna\StagHerd\Contracts\PaymentProvider;
use Equidna\StagHerd\Exceptions\ProviderNotRegisteredException;

class ProviderRegistry
{
    /**
     * @var array<string, PaymentProvider>
     */
    private array $providers = [];

    public function register(PaymentProvider $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    public function get(string $name): PaymentProvider
    {
        if (! isset($this->providers[$name])) {
            throw ProviderNotRegisteredException::forProvider($name);
        }

        return $this->providers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }
}
