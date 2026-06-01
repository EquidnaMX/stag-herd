<?php

namespace Equidna\StagHerd\Domain;

use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;

final class Payment
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $provider,
        public readonly string $method,
        public readonly int $amount,
        public readonly string $currency,
        public readonly PaymentStatusEnum $status,
        public readonly ?string $providerStatus = null,
        public readonly ?string $externalReference = null,
        public readonly ?string $payerReference = null,
        public readonly ?string $payerEmail = null,
        public readonly ?ProviderReferencesData $references = null,
        public readonly array $metadata = [],
    ) {
        //
    }

    /**
     * Return a new payment instance with a different status.
     */
    public function withStatus(
        PaymentStatusEnum $status,
        ?string $providerStatus = null,
    ): self {
        return new self(
            id: $this->id,
            provider: $this->provider,
            method: $this->method,
            amount: $this->amount,
            currency: $this->currency,
            status: $status,
            providerStatus: $providerStatus ?? $this->providerStatus,
            externalReference: $this->externalReference,
            payerReference: $this->payerReference,
            payerEmail: $this->payerEmail,
            references: $this->references,
            metadata: $this->metadata,
        );
    }

    /**
     * Check if the payment status is final.
     */
    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    /**
     * Convert the payment to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'method' => $this->method,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'provider_status' => $this->providerStatus,
            'external_reference' => $this->externalReference,
            'payer_reference' => $this->payerReference,
            'payer_email' => $this->payerEmail,
            'references' => $this->references?->toArray(),
            'metadata' => $this->metadata,
        ];
    }
}
