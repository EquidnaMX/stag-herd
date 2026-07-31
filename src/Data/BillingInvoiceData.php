<?php

namespace Equidna\StagHerd\Data;

use Carbon\CarbonImmutable;

final readonly class BillingInvoiceData
{
    public function __construct(
        public string $provider,
        public string $id,
        public string $status,
        public string $credentialContext,
        public ?string $customerId = null,
        public ?string $subscriptionId = null,
        public ?int $amountPaid = null,
        public ?string $currency = null,
        public ?CarbonImmutable $periodStart = null,
        public ?CarbonImmutable $periodEnd = null,
    ) {
        //
    }
}
