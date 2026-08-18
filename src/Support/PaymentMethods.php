<?php

namespace Equidna\StagHerd\Support;

final class PaymentMethods
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getMethods(bool $enabledOnly = false): array
    {
        $providers = config('stag-herd.providers', []);
        $methods = [];

        foreach ($providers as $providerConfig) {
            if (!is_array($providerConfig)) {
                continue;
            }

            if ($enabledOnly && !($providerConfig['enabled'] ?? false)) {
                continue;
            }

            foreach (($providerConfig['methods'] ?? []) as $method => $methodConfig) {
                if (!is_array($methodConfig)) {
                    continue;
                }

                if ($enabledOnly && !($methodConfig['enabled'] ?? false)) {
                    continue;
                }

                $methods[(string) $method] = $methodConfig;
            }
        }

        return $methods;
    }
}
