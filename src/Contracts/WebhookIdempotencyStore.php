<?php

namespace Equidna\StagHerd\Contracts;

interface WebhookIdempotencyStore
{
    /**
     * Reserve an idempotency key. Returns false when the key already exists.
     */
    public function claim(string $key, int $ttlSeconds): bool;
}
