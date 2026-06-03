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
            amount: (int) $data['amount'],
            currency: strtoupper((string) $data['currency']),
            method: strtolower((string) $data['method']),
            provider: strtolower((string) $data['provider']),
            externalReference: isset($data['external_reference'])
                ? (string) $data['external_reference']
                : null,
            payerReference: isset($data['payer_reference'])
                ? (string) $data['payer_reference']
                : null,
            payerEmail: isset($data['payer_email'])
                ? (string) $data['payer_email']
                : null,
            description: isset($data['description'])
                ? (string) $data['description']
                : null,
            returnUrl: isset($data['return_url'])
                ? (string) $data['return_url']
                : null,
            cancelUrl: isset($data['cancel_url'])
                ? (string) $data['cancel_url']
                : null,
            metadata: isset($data['metadata']) && is_array($data['metadata'])
                ? $data['metadata']
                : [],
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
