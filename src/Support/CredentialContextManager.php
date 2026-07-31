<?php

namespace Equidna\StagHerd\Support;

use Closure;
use Equidna\StagHerd\Contracts\CredentialResolver;

final readonly class CredentialContextManager
{
    public function __construct(
        private CredentialResolver $resolver,
    ) {
        //
    }

    public function run(string $provider, string $credentialContext, Closure $operation): mixed
    {
        $configKey = "stag-herd.providers.{$provider}.credentials";
        $original = config($configKey, []);
        $credentials = $this->resolver->resolve($provider, $credentialContext);

        config()->set($configKey, $credentials);

        try {
            return $operation();
        } finally {
            config()->set($configKey, $original);
        }
    }
}
