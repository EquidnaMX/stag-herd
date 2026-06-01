<?php

namespace Equidna\StagHerd\Data;

final readonly class PaymentRequestData
{
    public function __construct(
        public int $amount,
        public string $currency,
        public string $method,
        public string $provider,
        public ?string $externalReference = null,
        public ?string $payerReference = null,
        public ?string $payerEmail = null,
        public ?string $description = null,
        public ?string $returnUrl = null,
        public ?string $cancelUrl = null,
        public array $metadata = [],
    ) {
        //
    }

    /**
     * Create a payment request from array data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            currency: strtoupper((string) $data['currency']),
            method: (string) $data['method'],
            provider: (string) $data['provider'],
            externalReference: $data['external_reference'] ?? null,
            payerReference: $data['payer_reference'] ?? null,
            payerEmail: $data['payer_email'] ?? null,
            description: $data['description'] ?? null,
            returnUrl: $data['return_url'] ?? null,
            cancelUrl: $data['cancel_url'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * Convert the payment request to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'provider' => $this->provider,
            'external_reference' => $this->externalReference,
            'payer_reference' => $this->payerReference,
            'payer_email' => $this->payerEmail,
            'description' => $this->description,
            'return_url' => $this->returnUrl,
            'cancel_url' => $this->cancelUrl,
            'metadata' => $this->metadata,
        ];
    }
}
