<?php

namespace Equidna\StagHerd\Data;

final readonly class PaymentCancellationData
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $provider,
        public ?string $method = null,
        public ?string $paymentId = null,
        public ?string $providerPaymentId = null,
        public ?string $externalReference = null,
        public ?string $reason = null,
        public array $metadata = [],
        public string $credentialContext = 'default',
    ) {
        //
    }
}
