<?php

namespace Equidna\StagHerd\Contracts;

interface BillingResourceRepository
{
    /** @param array<string, mixed> $payload */
    public function upsert(
        string $provider,
        string $credentialContext,
        string $resourceType,
        string $providerResourceId,
        ?string $status,
        array $payload,
        ?int $providerEventCreatedAt = null,
    ): bool;
}
