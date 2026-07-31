<?php

namespace Equidna\StagHerd\Data;

final readonly class BillingPortalSessionData
{
    /** @param array<string, mixed> $rawPayload */
    public function __construct(
        public string $provider,
        public string $id,
        public string $url,
        public string $credentialContext,
        public array $rawPayload = [],
    ) {
        //
    }
}
