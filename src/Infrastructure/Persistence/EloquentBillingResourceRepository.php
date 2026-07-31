<?php

namespace Equidna\StagHerd\Infrastructure\Persistence;

use Equidna\StagHerd\Contracts\BillingResourceRepository;
use Equidna\StagHerd\Infrastructure\Persistence\Models\StagHerdBillingResource;
use Illuminate\Support\Facades\DB;

final class EloquentBillingResourceRepository implements BillingResourceRepository
{
    public function upsert(
        string $provider,
        string $credentialContext,
        string $resourceType,
        string $providerResourceId,
        ?string $status,
        array $payload,
        ?int $providerEventCreatedAt = null,
    ): bool {
        return DB::transaction(function () use (
            $provider,
            $credentialContext,
            $resourceType,
            $providerResourceId,
            $status,
            $payload,
            $providerEventCreatedAt,
        ): bool {
            $identity = [
                'provider' => strtolower($provider),
                'credential_context' => $credentialContext,
                'resource_type' => $resourceType,
                'provider_resource_id' => $providerResourceId,
            ];
            /** @var StagHerdBillingResource|null $existing */
            $existing = StagHerdBillingResource::query()
                ->where($identity)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof StagHerdBillingResource
                && $providerEventCreatedAt !== null
                && (int) $existing->provider_event_created_at > $providerEventCreatedAt) {
                return false;
            }

            StagHerdBillingResource::query()->updateOrCreate($identity, [
                'status' => $status,
                'payload' => $payload,
                'provider_event_created_at' => $providerEventCreatedAt
                    ?? (int) ($existing?->provider_event_created_at ?? 0),
            ]);

            return true;
        });
    }
}
