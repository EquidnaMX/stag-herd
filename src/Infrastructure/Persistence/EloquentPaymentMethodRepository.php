<?php

namespace Equidna\StagHerd\Infrastructure\Persistence;

use Equidna\StagHerd\Contracts\PaymentMethodRepository;
use Equidna\StagHerd\Infrastructure\Persistence\Models\StagHerdPaymentMethod;

final class EloquentPaymentMethodRepository implements PaymentMethodRepository
{
    public function upsert(array $attributes): void
    {
        $record = StagHerdPaymentMethod::query()->firstOrNew([
            'provider' => $attributes['provider'],
            'credential_context' => $attributes['credential_context'] ?? 'default',
            'provider_payment_method_id' => $attributes['provider_payment_method_id'],
        ]);

        $record->fill([
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
            'provider_event_created_at' => $attributes['provider_event_created_at'] ?? 0,
            'payload' => $attributes['payload'] ?? null,
        ]);

        $record->save();

        if (($attributes['is_default'] ?? false) === true) {
            $this->markAsDefault(
                $record->provider,
                $record->credential_context,
                $record->owner_reference,
                $record->provider_payment_method_id,
            );
        }
    }

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
}
