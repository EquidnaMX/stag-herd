<?php

namespace Equidna\StagHerd\Data;

use Equidna\StagHerd\Domain\Enums\CheckoutMode;

final readonly class CheckoutRequestData
{
    /**
     * @param list<BillingLineItemData> $lineItems
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        public string $provider,
        public CheckoutMode $mode,
        public string $credentialContext,
        public array $lineItems,
        public string $successUrl,
        public string $cancelUrl,
        public ?string $customerId = null,
        public ?string $customerEmail = null,
        public ?string $externalReference = null,
        public array $metadata = [],
        public ?string $idempotencyKey = null,
    ) {
        //
    }
}
