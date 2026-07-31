<?php

namespace Equidna\StagHerd\Data;

final readonly class SubscriptionCancellationData
{
    public function __construct(
        public string $provider,
        public string $credentialContext,
        public string $subscriptionId,
        public bool $atPeriodEnd = true,
        public ?string $idempotencyKey = null,
    ) {
        //
    }
}
