<?php

namespace Equidna\StagHerd\Domain;

use Equidna\StagHerd\Data\NextActionData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;

final readonly class Payment
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string|int $id,
        public string $provider,
        public string $method,
        public int $amount,
        public string $currency,
        public PaymentStatusEnum $status,
        public ?string $providerStatus = null,
        public ?string $externalReference = null,
        public ?string $payerReference = null,
        public ?string $payerEmail = null,
        public ?ProviderReferencesData $references = null,
        public array $metadata = [],
        public ?NextActionData $nextAction = null,
    ) {
        //
    }

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
            nextAction: $this->nextAction,
        );
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatusEnum::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === PaymentStatusEnum::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === PaymentStatusEnum::REJECTED;
    }

    public function isCanceled(): bool
    {
        return $this->status === PaymentStatusEnum::CANCELED;
    }

    public function isRefunded(): bool
    {
        return $this->status === PaymentStatusEnum::REFUNDED;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatusEnum::FAILED;
    }

    public function canBeCanceled(): bool
    {
        return $this->isPending();
    }

    public function canBeRefunded(): bool
    {
        return $this->isApproved();
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [
            PaymentStatusEnum::APPROVED,
            PaymentStatusEnum::REJECTED,
            PaymentStatusEnum::CANCELED,
            PaymentStatusEnum::REFUNDED,
            PaymentStatusEnum::FAILED,
        ], true);
    }

    /**
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
            'next_action' => $this->nextAction?->toArray(),
        ];
    }
}
