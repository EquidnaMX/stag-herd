<?php

namespace Equidna\StagHerd\Data;

final readonly class BillingPriceData
{
    public function __construct(
        public string $provider,
        public string $id,
        public string $productId,
        public int $unitAmount,
        public string $currency,
        public string $credentialContext,
        public ?string $recurringInterval = null,
        public bool $active = true,
    ) {
        //
    }
}
