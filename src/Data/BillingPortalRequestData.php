<?php

namespace Equidna\StagHerd\Data;

final readonly class BillingPortalRequestData
{
    public function __construct(
        public string $provider,
        public string $credentialContext,
        public string $customerId,
        public string $returnUrl,
        public ?string $idempotencyKey = null,
    ) {
        //
    }
}
