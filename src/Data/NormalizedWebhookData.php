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
        public ?string $providerEventId = null,
        public string $credentialContext = 'default',
        public ?string $status = null,
        public ?string $customerId = null,
        public ?string $subscriptionId = null,
        public ?string $invoiceId = null,
        public ?string $paymentStatus = null,
    ) {
        //
    }

    public function idempotencyKey(string $prefix): string
    {
        if ($this->providerEventId !== null && $this->providerEventId !== '') {
            return sprintf(
                '%s:%s:%s:%s',
                rtrim($prefix, ':'),
                $this->provider,
                $this->credentialContext,
                $this->providerEventId,
            );
        }

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
