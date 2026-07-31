<?php

namespace Equidna\StagHerd\Data;

use Equidna\StagHerd\Support\MoneyFormatter;

final readonly class PaymentRequestData
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public int $amount,
        public string $currency,
        public string $method,
        public ?string $provider = null,
        public ?string $providerOrderId = null,
        public ?string $externalReference = null,
        public ?string $payerReference = null,
        public ?string $payerEmail = null,
        public ?string $description = null,
        public ?string $returnUrl = null,
        public ?string $cancelUrl = null,
        public array $metadata = [],
        public string $credentialContext = 'default',
    ) {
        //
    }

    /** @param array<string, mixed> $metadata */
    public static function fromDecimalAmount(
        int|float|string $amount,
        string $currency,
        string $method,
        ?string $provider = null,
        ?string $providerOrderId = null,
        ?string $externalReference = null,
        ?string $payerReference = null,
        ?string $payerEmail = null,
        ?string $description = null,
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
        array $metadata = [],
        string $credentialContext = 'default',
    ): self {
        return new self(
            amount: MoneyFormatter::fromDecimal($amount),
            currency: strtoupper($currency),
            method: strtolower($method),
            provider: $provider !== null ? strtolower($provider) : null,
            providerOrderId: $providerOrderId,
            externalReference: $externalReference,
            payerReference: $payerReference,
            payerEmail: $payerEmail,
            description: $description,
            returnUrl: $returnUrl,
            cancelUrl: $cancelUrl,
            metadata: $metadata,
            credentialContext: $credentialContext,
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (int) $data['amount'],
            currency: strtoupper((string) $data['currency']),
            method: strtolower((string) $data['method']),
            provider: isset($data['provider']) ? strtolower((string) $data['provider']) : null,
            providerOrderId: isset($data['provider_order_id'])
                ? (string) $data['provider_order_id']
                : (isset($data['providerOrderId']) ? (string) $data['providerOrderId'] : null),
            externalReference: isset($data['external_reference'])
                ? (string) $data['external_reference']
                : (isset($data['externalReference']) ? (string) $data['externalReference'] : null),
            payerReference: isset($data['payer_reference'])
                ? (string) $data['payer_reference']
                : (isset($data['payerReference']) ? (string) $data['payerReference'] : null),
            payerEmail: isset($data['payer_email'])
                ? (string) $data['payer_email']
                : (isset($data['payerEmail']) ? (string) $data['payerEmail'] : null),
            description: isset($data['description']) ? (string) $data['description'] : null,
            returnUrl: isset($data['return_url'])
                ? (string) $data['return_url']
                : (isset($data['returnUrl']) ? (string) $data['returnUrl'] : null),
            cancelUrl: isset($data['cancel_url'])
                ? (string) $data['cancel_url']
                : (isset($data['cancelUrl']) ? (string) $data['cancelUrl'] : null),
            metadata: isset($data['metadata']) && is_array($data['metadata'])
                ? $data['metadata']
                : [],
            credentialContext: (string) ($data['credential_context'] ?? $data['credentialContext'] ?? 'default'),
        );
    }

    public function withProvider(string $provider): self
    {
        return new self(
            amount: $this->amount,
            currency: $this->currency,
            method: $this->method,
            provider: strtolower($provider),
            providerOrderId: $this->providerOrderId,
            externalReference: $this->externalReference,
            payerReference: $this->payerReference,
            payerEmail: $this->payerEmail,
            description: $this->description,
            returnUrl: $this->returnUrl,
            cancelUrl: $this->cancelUrl,
            metadata: $this->metadata,
            credentialContext: $this->credentialContext,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'provider' => $this->provider,
            'provider_order_id' => $this->providerOrderId,
            'external_reference' => $this->externalReference,
            'payer_reference' => $this->payerReference,
            'payer_email' => $this->payerEmail,
            'description' => $this->description,
            'return_url' => $this->returnUrl,
            'cancel_url' => $this->cancelUrl,
            'metadata' => $this->metadata,
            'credential_context' => $this->credentialContext,
        ];
    }
}
