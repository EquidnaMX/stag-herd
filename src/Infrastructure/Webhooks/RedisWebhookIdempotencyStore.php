<?php

namespace Equidna\StagHerd\Infrastructure\Webhooks;

use Equidna\StagHerd\Contracts\WebhookIdempotencyStore;
use Illuminate\Support\Facades\Redis;

final class RedisWebhookIdempotencyStore implements WebhookIdempotencyStore
{
    public function claim(string $key, int $ttlSeconds): bool
    {
        $result = Redis::command('set', [
            $key,
            'processing',
            'EX',
            $ttlSeconds,
            'NX',
        ]);

        return $result === true || $result === 'OK';
    }
}
