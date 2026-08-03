<?php

namespace Equidna\StagHerd\Contracts;

interface PaymentMethodRepository
{
    public function upsert(array $attributes): void;

    public function findByProviderPaymentMethodId(
        string $provider,
        string $credentialContext,
        string $providerPaymentMethodId,
    ): ?array;

    public function findByFingerprint(
        string $provider,
        string $credentialContext,
        string $providerCustomerId,
        string $fingerprint,
    ): ?array;

    public function listByOwner(
        string $provider,
        string $credentialContext,
        string $ownerReference,
    ): array;

    public function markAsDefault(
        string $provider,
        string $credentialContext,
        string $ownerReference,
        string $providerPaymentMethodId,
    ): void;

    public function markDetached(
        string $provider,
        string $credentialContext,
        string $providerPaymentMethodId,
    ): void;

    public function touchLastUsed(
        string $provider,
        string $credentialContext,
        string $providerPaymentMethodId,
    ): void;
}
