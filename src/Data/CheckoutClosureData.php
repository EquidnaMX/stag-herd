<?php

namespace Equidna\StagHerd\Data;

/** Server-owned references only. Never pass identifiers supplied by a payer. */
final readonly class CheckoutClosureData
{
    public function __construct(
        public string $provider,
        public string $checkoutId,
        public string $externalReference,
        public string $credentialContext = 'default',
        public bool $consumerCaptureBlocked = false,
        public ?string $settledPaymentId = null,
    ) {}
}
