<?php

namespace Equidna\StagHerd\Data;

use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;

final readonly class PaymentResultData
{
    public function __construct(
        public string $provider,
        public string $method,
        public PaymentStatusEnum $status,
        public ?string $providerStatus = null,
        public ?ProviderReferencesData $references = null,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?NextActionData $nextAction = null,
        public ?string $reason = null,
        public array $metadata = [],
        public array $rawPayload = [],
    ) {
        //
    }

    /**
     * Create an approved payment result.
     */
    public static function approved(
        string $provider,
        string $method,
        ?string $providerStatus = null,
        ?ProviderReferencesData $references = null,
        ?int $amount = null,
        ?string $currency = null,
        array $metadata = [],
        array $rawPayload = [],
    ): self {
        return new self(
            provider: $provider,
            method: $method,
            status: PaymentStatusEnum::APPROVED,
            providerStatus: $providerStatus,
            references: $references,
            amount: $amount,
            currency: $currency,
            nextAction: NextActionData::none(),
            metadata: $metadata,
            rawPayload: $rawPayload,
        );
    }

    /**
     * Create a pending payment result.
     */
    public static function pending(
        string $provider,
        string $method,
        ?string $providerStatus = null,
        ?ProviderReferencesData $references = null,
        ?float $amount = null,
        ?string $currency = null,
        ?NextActionData $nextAction = null,
        ?string $reason = null,
        array $metadata = [],
        array $rawPayload = [],
    ): self {
        return new self(
            provider: $provider,
            method: $method,
            status: PaymentStatusEnum::PENDING,
            providerStatus: $providerStatus,
            references: $references,
            amount: $amount,
            currency: $currency,
            nextAction: $nextAction ?? NextActionData::none(),
            reason: $reason,
            metadata: $metadata,
            rawPayload: $rawPayload,
        );
    }

    /**
     * Create a rejected payment result.
     */
    public static function rejected(
        string $provider,
        string $method,
        ?string $providerStatus = null,
        ?string $reason = null,
        array $metadata = [],
        array $rawPayload = [],
    ): self {
        return new self(
            provider: $provider,
            method: $method,
            status: PaymentStatusEnum::REJECTED,
            providerStatus: $providerStatus,
            nextAction: NextActionData::none(),
            reason: $reason,
            metadata: $metadata,
            rawPayload: $rawPayload,
        );
    }

    /**
     * Convert the payment result to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'method' => $this->method,
            'status' => $this->status->value,
            'provider_status' => $this->providerStatus,
            'references' => $this->references?->toArray(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'next_action' => $this->nextAction?->toArray(),
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'raw_payload' => $this->rawPayload,
        ];
    }
}
