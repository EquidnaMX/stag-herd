<?php

namespace Equidna\StagHerd\Data;

final readonly class RefundRequestData
{
    public function __construct(
        public string $provider,
        public ?string $paymentId = null,
        public ?string $providerPaymentId = null,
        public ?string $externalReference = null,
        public ?int $amount = null,
        public ?string $reason = null,
        public array $metadata = [],
    ) {
        //
    }
}
