<?php

namespace Equidna\StagHerd\Infrastructure\Webhooks;

use Equidna\StagHerd\Contracts\WebhookIdempotencyStore;
use Illuminate\Support\Facades\Redis;

final class RedisWebhookIdempotencyStore implements WebhookIdempotencyStore
{
    private const PROCESSING_STATE = 'processing';

    private const PROCESSED_STATE = 'processed';

    private const RELEASE_IF_PROCESSING_LUA = <<<'LUA'
if redis.call('GET', KEYS[1]) == ARGV[1] then
    return redis.call('DEL', KEYS[1])
end

return 0
LUA;

    public function claim(string $key, int $ttlSeconds): bool
    {
        $result = Redis::set($key, self::PROCESSING_STATE, 'EX', $ttlSeconds, 'NX');

        return $result === true || $result === 'OK';
    }

    public function markProcessed(string $key, int $ttlSeconds): void
    {
        Redis::set($key, self::PROCESSED_STATE, 'EX', $ttlSeconds);
    }

    public function releaseIfProcessing(string $key): void
    {
        Redis::eval(self::RELEASE_IF_PROCESSING_LUA, 1, $key, self::PROCESSING_STATE);
    }
}
