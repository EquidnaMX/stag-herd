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

        if (!isset($this->providers[$name])) {
            throw ProviderNotRegisteredException::forProvider($name);
        }

        return $this->providers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->providers[strtolower($name)]);
    }

    /**
     * @return list<string>
     */
    public function methodsForProvider(string $provider): array
    {
        $provider = strtolower($provider);

        if (!$this->has($provider)) {
            return [];
        }

        $declaredMethods = array_values(array_unique(array_map(
            static fn (string $method): string => strtolower($method),
            $this->get($provider)->getMethods(),
        )));

        if ($declaredMethods === []) {
            return [];
        }

        $providerConfig = config("stag-herd.providers.{$provider}");

        if (!is_array($providerConfig) || !($providerConfig['enabled'] ?? false)) {
            return [];
        }

        $enabledMethods = [];

        foreach ($declaredMethods as $declaredMethod) {
            $methodConfig = $providerConfig['methods'][$declaredMethod] ?? null;

            if (!is_array($methodConfig) || !($methodConfig['enabled'] ?? false)) {
                continue;
            }

            $enabledMethods[] = $declaredMethod;
        }

        return $enabledMethods;
    }

    public function resolveProviderNameForMethod(string $method): string
    {
        $method = strtolower($method);

        foreach (array_keys($this->providers) as $providerName) {
            if (in_array($method, $this->methodsForProvider($providerName), true)) {
                return $providerName;
            }
        }

        throw InvalidPaymentMethodException::forMethod($method);
    }
}
