<?php

namespace Equidna\StagHerd\Data;

final readonly class PaymentCancellationData
{
    public function __construct(
        public string $provider,
        public ?string $method = null,
        public ?string $paymentId = null,
        public ?string $providerPaymentId = null,
        public ?string $externalReference = null,
        public ?string $reason = null,
        public array $metadata = [],
    ) {
        //
    }
}
