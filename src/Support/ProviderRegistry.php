<?php

namespace Equidna\StagHerd\Support;

use Equidna\StagHerd\Contracts\PaymentProvider;
use Equidna\StagHerd\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Exceptions\ProviderNotRegisteredException;

class ProviderRegistry
{
    /**
     * @var array<string, PaymentProvider>
     */
    private array $providers = [];

    public function register(PaymentProvider $provider): void
    {
        $this->providers[strtolower($provider->getName())] = $provider;
    }

    public function get(string $name): PaymentProvider
    {
        $name = strtolower($name);

        if (! isset($this->providers[$name])) {
            throw ProviderNotRegisteredException::forProvider($name);
        }

        return $this->providers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->providers[strtolower($name)]);
    }

    public function resolveProviderNameForMethod(string $method): string
    {
        $method = strtolower($method);

        foreach (config('stag-herd.providers', []) as $providerName => $providerConfig) {
            if (! ($providerConfig['enabled'] ?? false)) {
                continue;
            }

            foreach (($providerConfig['methods'] ?? []) as $configuredMethod => $methodConfig) {
                if (
                    strtolower((string) $configuredMethod) === $method
                    && ($methodConfig['enabled'] ?? false)
                ) {
                    return strtolower((string) $providerName);
                }
            }
        }

        throw InvalidPaymentMethodException::forMethod($method);
    }
}
