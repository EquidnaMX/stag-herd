<?php

namespace Equidna\StagHerd\Data;

final readonly class SubscriptionLookupData
{
    public function __construct(
        public string $provider,
        public string $credentialContext,
        public string $subscriptionId,
    ) {
        //
    }
}
