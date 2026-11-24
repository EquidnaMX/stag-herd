<?php

namespace Equidna\StagHerd\Contracts\Gateways;

/** Optional additive capability; existing custom gateway implementations remain compatible. */
interface ExpiresStripeCheckout
{
    /** @return array<string, mixed> */
    public function expireCheckoutSession(string $checkoutSessionId): array;
}
