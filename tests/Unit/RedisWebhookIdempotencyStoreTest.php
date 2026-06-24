<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Infrastructure\Webhooks\RedisWebhookIdempotencyStore;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Support\Facades\Redis;

class RedisWebhookIdempotencyStoreTest extends TestCase
{
    public function test_it_claims_using_laravel_phpredis_set_signature(): void
    {
        Redis::shouldReceive('set')
            ->once()
            ->with('webhook:key', 'processing', 'EX', 120, 'NX')
            ->andReturn('OK');

        $store = new RedisWebhookIdempotencyStore();

        $this->assertTrue($store->claim('webhook:key', 120));
    }

    public function test_it_marks_processed_with_expiration(): void
    {
        Redis::shouldReceive('set')
            ->once()
            ->with('webhook:key', 'processed', 'EX', 120)
            ->andReturn('OK');

        $store = new RedisWebhookIdempotencyStore();
        $store->markProcessed('webhook:key', 120);

        $this->assertTrue(true);
    }

    public function test_it_releases_processing_claim_atomically_with_lua(): void
    {
        Redis::shouldReceive('eval')
            ->once()
            ->with(
                "if redis.call('GET', KEYS[1]) == ARGV[1] then\n    return redis.call('DEL', KEYS[1])\nend\n\nreturn 0",
                1,
                'webhook:key',
                'processing'
            )
            ->andReturn(1);

        $store = new RedisWebhookIdempotencyStore();
        $store->releaseIfProcessing('webhook:key');

        $this->assertTrue(true);
    }
}
