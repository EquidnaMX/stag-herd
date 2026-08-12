<?php

namespace Equidna\StagHerd\Contracts;

interface PaymentMethodRepository
{
    /** @param array<string, mixed> $attributes */
    public function upsert(array $attributes): bool;

    /** @return array<string, mixed>|null */
    public function findByProviderPaymentMethodId(
        string $provider,
        string $credentialContext,
        string $providerPaymentMethodId,
    ): ?array;

    /** @return array<string, mixed>|null */
    public function findByFingerprint(
        string $provider,
        string $credentialContext,
        string $providerCustomerId,
        string $fingerprint,
    ): ?array;

    /** @return array<string, mixed>|null */
    public function findActiveByOwnerFingerprint(
        string $provider,
        string $credentialContext,
        string $ownerReference,
        string $fingerprint,
    ): ?array;

    /** @return array<int, array<string, mixed>> */
    public function listByOwner(
        string $provider,
        string $credentialContext,
        string $ownerReference,
    ): array;

    /** @return array<int, array<string, mixed>> */
    public function listActiveByOwner(
        string $provider,
        string $credentialContext,
        string $ownerReference,
    ): array;

    /** @return array<string, mixed>|null */
    public function findActiveByOwner(
        string $provider,
        string $credentialContext,
        string $ownerReference,
        string $providerPaymentMethodId,
    ): ?array;

    /** @return array<string, mixed>|null */
    public function findDefaultByOwner(
        string $provider,
        string $credentialContext,
        string $ownerReference,
    ): ?array;

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
