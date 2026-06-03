<?php

namespace Equidna\StagHerd\Data;

final readonly class PaymentLookupData
{
    public function __construct(
        public string $provider,
        public ?string $paymentId = null,
        public ?string $providerPaymentId = null,
        public ?string $externalReference = null,
        public array $metadata = [],
    ) {
        //
    }
}
