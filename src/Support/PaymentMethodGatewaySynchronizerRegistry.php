<?php

namespace Equidna\StagHerd\Support;

use Equidna\StagHerd\Application\PaymentMethodGatewaySynchronizer;

final class PaymentMethodGatewaySynchronizerRegistry
{
    /**
     * @var array<string, PaymentMethodGatewaySynchronizer>
     */
    private array $synchronizers = [];

    public function register(string $provider, PaymentMethodGatewaySynchronizer $synchronizer): void
    {
        $this->synchronizers[strtolower($provider)] = $synchronizer;
    }

    public function get(string $provider): ?PaymentMethodGatewaySynchronizer
    {
        return $this->synchronizers[strtolower($provider)] ?? null;
    }
}
