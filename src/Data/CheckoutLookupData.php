<?php

namespace Equidna\StagHerd\Data;

final readonly class CheckoutLookupData
{
    public function __construct(
        public string $provider,
        public string $credentialContext,
        public string $checkoutSessionId,
    ) {
        //
    }
}
