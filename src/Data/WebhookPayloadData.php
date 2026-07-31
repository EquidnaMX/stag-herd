<?php

namespace Equidna\StagHerd\Data;

final readonly class WebhookPayloadData
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $query
     */
    public function __construct(
        public string $provider,
        public array $payload,
        public array $headers = [],
        public array $query = [],
        public string $rawBody = '',
        public ?string $ipAddress = null,
        public string $credentialContext = 'default',
    ) {
        //
    }
}
