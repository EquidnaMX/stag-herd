<?php

namespace Equidna\StagHerd\Support;

use Equidna\StagHerd\Contracts\BillingProvider;
use Equidna\StagHerd\Exceptions\ProviderNotRegisteredException;

final class BillingProviderRegistry
{
    /** @var array<string, BillingProvider> */
    private array $providers = [];

    public function register(BillingProvider $provider): void
    {
        $this->providers[strtolower($provider->getName())] = $provider;
    }

    public function get(string $provider): BillingProvider
    {
        $provider = strtolower($provider);

        if (!isset($this->providers[$provider])) {
            throw ProviderNotRegisteredException::forProvider($provider);
        }

        return $this->providers[$provider];
    }
}
