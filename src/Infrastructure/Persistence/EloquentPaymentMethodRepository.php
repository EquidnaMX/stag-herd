<?php

namespace Equidna\StagHerd\Infrastructure\Persistence;

use Equidna\StagHerd\Contracts\PaymentMethodRepository;
use Equidna\StagHerd\Infrastructure\Persistence\Models\StagHerdPaymentMethod;
use Illuminate\Support\Facades\DB;

final class EloquentPaymentMethodRepository implements PaymentMethodRepository
{
    /** @param array<string, mixed> $attributes */
    public function upsert(array $attributes): bool
    {
        return DB::transaction(function () use ($attributes): bool {
            $identity = [
                'provider' => strtolower((string) $attributes['provider']),
                'credential_context' => $attributes['credential_context'] ?? 'default',
                'provider_payment_method_id' => $attributes['provider_payment_method_id'],
            ];

            /** @var StagHerdPaymentMethod|null $existing */
            $existing = StagHerdPaymentMethod::query()
                ->where($identity)
                ->lockForUpdate()
                ->first();

            $providerEventCreatedAt = $attributes['provider_event_created_at'] ?? null;

            if (
                $existing instanceof StagHerdPaymentMethod
                && $providerEventCreatedAt !== null
                && (int) $existing->provider_event_created_at > (int) $providerEventCreatedAt
            ) {
                return false;
            }

            StagHerdPaymentMethod::query()->updateOrCreate($identity, [
                'owner_reference' => $attributes['owner_reference'],
                'provider_customer_id' => $attributes['provider_customer_id'],
                'type' => $attributes['type'] ?? 'card',
                'fingerprint' => $attributes['fingerprint'] ?? null,
                'display_name' => $attributes['display_name'] ?? null,
                'brand' => $attributes['brand'] ?? null,
                'last4' => $attributes['last4'] ?? null,
                'exp_month' => $attributes['exp_month'] ?? null,
                'exp_year' => $attributes['exp_year'] ?? null,
                'is_default' => $attributes['is_default'] ?? false,
                'status' => $attributes['status'] ?? 'active',
                'attached_at' => $attributes['attached_at'] ?? null,
                'detached_at' => $attributes['detached_at'] ?? null,
                'last_used_at' => $attributes['last_used_at'] ?? null,
                'provider_event_created_at' => $providerEventCreatedAt
                    ?? (int) ($existing?->provider_event_created_at ?? 0),
                'payload' => $attributes['payload'] ?? null,
            ]);

            return true;
        });
    }

    /** @return array<string, mixed>|null */
    public function findByProviderPaymentMethodId(
        string $provider,
        string $credentialContext,
        string $providerPaymentMethodId,
    ): ?array {
        return StagHerdPaymentMethod::query()
            ->where('provider', $provider)
            ->where('credential_context', $credentialContext)
            ->where('provider_payment_method_id', $providerPaymentMethodId)
            ->first()
            ?->toArray();
    }

    /** @return array<string, mixed>|null */
    public function findByFingerprint(
        string $provider,
        string $credentialContext,
        string $providerCustomerId,
        string $fingerprint,
    ): ?array {
        return StagHerdPaymentMethod::query()
            ->where('provider', $provider)
            ->where('credential_context', $credentialContext)
            ->where('provider_customer_id', $providerCustomerId)
            ->where('fingerprint', $fingerprint)
            ->where('status', 'active')
            ->first()
            ?->toArray();
    }

    /** @return array<string, mixed>|null */
    public function findActiveByOwnerFingerprint(
        string $provider,
        string $credentialContext,
        string $ownerReference,
        string $fingerprint,
    ): ?array {
        /** @var StagHerdPaymentMethod|null $paymentMethod */
        $paymentMethod = StagHerdPaymentMethod::query()
            ->where('provider', $provider)
            ->where('credential_context', $credentialContext)
            ->where('owner_reference', $ownerReference)
            ->where('fingerprint', $fingerprint)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderByDesc('last_used_at')
            ->orderByDesc('attached_at')
            ->orderByDesc('id')
            ->first();

        return $paymentMethod?->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function listByOwner(
        string $provider,
        string $credentialContext,
        string $ownerReference,
    ): array {
        return StagHerdPaymentMethod::query()
            ->where('provider', $provider)
            ->where('credential_context', $credentialContext)
            ->where('owner_reference', $ownerReference)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function listActiveByOwner(
        string $provider,
        string $credentialContext,
        string $ownerReference,
    ): array {
        return StagHerdPaymentMethod::query()
            ->where('provider', $provider)
            ->where('credential_context', $credentialContext)
            ->where('owner_reference', $ownerReference)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->toArray();
    }

    /** @return array<string, mixed>|null */
    public function findActiveByOwner(
        string $provider,
        string $credentialContext,
        string $ownerReference,
        string $providerPaymentMethodId,
    ): ?array {
        return StagHerdPaymentMethod::query()
            ->where('provider', $provider)
            ->where('credential_context', $credentialContext)
            ->where('owner_reference', $ownerReference)
            ->where('provider_payment_method_id', $providerPaymentMethodId)
            ->where('status', 'active')
            ->first()
            ?->toArray();
    }

    /** @return array<string, mixed>|null */
    public function findDefaultByOwner(
        string $provider,
        string $credentialContext,
        string $ownerReference,
    ): ?array {
        return StagHerdPaymentMethod::query()
            ->where('provider', $provider)
            ->where('credential_context', $credentialContext)
            ->where('owner_reference', $ownerReference)
            ->where('status', 'active')
            ->where('is_default', true)
            ->first()
            ?->toArray();
    }

    public function markAsDefault(
        string $provider,
        string $credentialContext,
        string $ownerReference,
        string $providerPaymentMethodId,
    ): void {
        StagHerdPaymentMethod::query()
            ->where('provider', $provider)
            ->where('credential_context', $credentialContext)
            ->where('owner_reference', $ownerReference)
            ->update(['is_default' => false]);

        StagHerdPaymentMethod::query()
            ->where('provider', $provider)
            ->where('credential_context', $credentialContext)
            ->where('owner_reference', $ownerReference)
            ->where('provider_payment_method_id', $providerPaymentMethodId)
            ->update([
                'is_default' => true,
                'status' => 'active',
                'detached_at' => null,
            ]);
    }

    public function markDetached(
        string $provider,
        string $credentialContext,
        string $providerPaymentMethodId,
    ): void {
        StagHerdPaymentMethod::query()
            ->where('provider', $provider)
            ->where('credential_context', $credentialContext)
            ->where('provider_payment_method_id', $providerPaymentMethodId)
            ->update([
                'status' => 'detached',
                'is_default' => false,
                'detached_at' => now(),
            ]);
    }

    public function touchLastUsed(
        string $provider,
        string $credentialContext,
        string $providerPaymentMethodId,
    ): void {
        StagHerdPaymentMethod::query()
            ->where('provider', $provider)
            ->where('credential_context', $credentialContext)
            ->where('provider_payment_method_id', $providerPaymentMethodId)
            ->update([
                'last_used_at' => now(),
            ]);
    }

    public function updateDisplayName(
        string $provider,
        string $credentialContext,
        string $providerPaymentMethodId,
        string $displayName,
    ): void {
        StagHerdPaymentMethod::query()
            ->where('provider', $provider)
            ->where('credential_context', $credentialContext)
            ->where('provider_payment_method_id', $providerPaymentMethodId)
            ->update([
                'display_name' => $displayName,
            ]);
    }
}
