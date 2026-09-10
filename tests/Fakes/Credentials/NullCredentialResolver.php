<?php

namespace Equidna\StagHerd\Tests\Fakes\Credentials;

use Equidna\StagHerd\Contracts\CredentialResolver;

final class NullCredentialResolver implements CredentialResolver
{
    public function resolve(string $provider, string $credentialContext): array
    {
        return [];
    }
}
