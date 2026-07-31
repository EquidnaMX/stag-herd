<?php

namespace Equidna\StagHerd\Contracts;

interface CredentialResolver
{
    /** @return array<string, mixed> */
    public function resolve(string $provider, string $credentialContext): array;
}
