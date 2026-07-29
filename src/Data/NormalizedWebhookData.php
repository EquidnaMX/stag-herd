<?php

namespace Equidna\StagHerd\Data;

final readonly class NormalizedWebhookData
{
    /**
     * @param array<string, mixed> $rawPayload
     */
    public function __construct(
        public string $provider,
        public string $eventType,
        public string $resourceType,
        public string $resourceId,
        public ?string $providerPaymentId = null,
        public ?string $providerOrderId = null,
        public ?string $method = null,
        public array $rawPayload = [],
    ) {
        //
    }

    public function idempotencyKey(string $prefix): string
    {
        return sprintf(
            '%s:%s:%s:%s:%s',
            rtrim($prefix, ':'),
            $this->provider,
            $this->eventType,
            $this->resourceType,
            $this->resourceId,
        );
    }
}
