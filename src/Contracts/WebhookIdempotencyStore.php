<?php

namespace Equidna\StagHerd\Contracts;

interface WebhookIdempotencyStore
{
    /**
     * Reserve an idempotency key. Returns false when the key already exists.
     */
    public function claim(string $key, int $ttlSeconds): bool;

    /**
     * Persist the webhook as fully processed for the configured TTL.
     */
    public function markProcessed(string $key, int $ttlSeconds): void;

    /**
     * Release the key only when it is still marked as processing.
     */
    public function releaseIfProcessing(string $key): void;
}
