<?php

namespace Equidna\StagHerd\Infrastructure\Credentials;

use Equidna\StagHerd\Contracts\CredentialResolver;

final class ConfigCredentialResolver implements CredentialResolver
{
    public function resolve(string $provider, string $credentialContext): array
    {
        $base = config("stag-herd.providers.{$provider}.credentials", []);
        $contextual = config("stag-herd.credential_contexts.{$credentialContext}.{$provider}", []);

        return array_merge(
            is_array($base) ? $base : [],
            is_array($contextual) ? $contextual : [],
        );
    }
}
