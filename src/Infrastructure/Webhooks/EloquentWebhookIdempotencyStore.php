<?php

namespace Equidna\StagHerd\Infrastructure\Webhooks;

use Equidna\StagHerd\Contracts\WebhookIdempotencyStore;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentWebhookIdempotencyStore implements WebhookIdempotencyStore
{
    public function claim(string $key, int $ttlSeconds): bool
    {
        $parts = explode(':', $key);
        $providerEventId = (string) array_pop($parts);
        $credentialContext = (string) (array_pop($parts) ?: 'default');
        $provider = (string) (array_pop($parts) ?: 'unknown');

        try {
            DB::table('stag_herd_webhook_events')->insert([
                'idempotency_key' => $key,
                'provider' => $provider,
                'credential_context' => $credentialContext,
                'provider_event_id' => $providerEventId,
                'event_type' => 'pending',
                'resource_type' => 'pending',
                'state' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                return false;
            }

            throw $exception;
        }
    }

    public function markProcessed(string $key, int $ttlSeconds): void
    {
        DB::table('stag_herd_webhook_events')
            ->where('idempotency_key', $key)
            ->update([
                'state' => 'processed',
                'processed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function releaseIfProcessing(string $key): void
    {
        DB::table('stag_herd_webhook_events')
            ->where('idempotency_key', $key)
            ->where('state', 'processing')
            ->delete();
    }
}
