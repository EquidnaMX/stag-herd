<?php

namespace Equidna\StagHerd\Data;

use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;

final readonly class PaymentConfirmationData
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $provider,
        public ?string $method = null,
        public ?string $paymentId = null,
        public ?string $providerPaymentId = null,
        public ?string $externalReference = null,
        public ?string $reason = null,
        public array $metadata = [],
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        $criteria = array_filter([
            $this->paymentId,
            $this->providerPaymentId,
            $this->externalReference,
        ], fn(?string $value) => $value !== null && trim($value) !== '');

        if (count($criteria) === 0) {
            throw InvalidPaymentPayloadException::missingField(
                'paymentId, providerPaymentId or externalReference'
            );
        }
    }
}
