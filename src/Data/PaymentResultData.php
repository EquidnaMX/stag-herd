<?php

namespace Equidna\StagHerd\Data;

use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;

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

    public static function pending(
        string $provider,
        string $method,
        ?string $providerStatus = null,
        ?ProviderReferencesData $references = null,
        ?int $amount = null,
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

    public static function rejected(
        string $provider,
        string $method,
        ?string $providerStatus = null,
        ?string $reason = null,
        ?ProviderReferencesData $references = null,
        ?int $amount = null,
        ?string $currency = null,
        array $metadata = [],
        array $rawPayload = [],
    ): self {
        return new self(
            provider: $provider,
            method: $method,
            status: PaymentStatusEnum::REJECTED,
            providerStatus: $providerStatus,
            references: $references,
            amount: $amount,
            currency: $currency,
            nextAction: NextActionData::none(),
            reason: $reason,
            metadata: $metadata,
            rawPayload: $rawPayload,
        );
    }

    public function assertMatchesRequest(
        PaymentRequestData $request,
        bool $requireAmount = false,
    ): void {
        $reference = $this->reference()
            ?? $request->externalReference
            ?? $request->providerOrderId
            ?? $request->payerReference;

        $this->assertMatchesExpectedValues(
            expectedAmount: $request->amount,
            expectedCurrency: $request->currency,
            provider: $request->provider,
            reference: $reference,
            requireAmount: $requireAmount,
        );
    }

    public function assertMatchesPayment(
        Payment $payment,
        bool $requireAmount = false,
    ): void {
        $reference = $this->reference()
            ?? $payment->references?->providerPaymentId
            ?? $payment->references?->providerOrderId
            ?? $payment->externalReference
            ?? $payment->id;

        $this->assertMatchesExpectedValues(
            expectedAmount: $payment->amount,
            expectedCurrency: $payment->currency,
            provider: $payment->provider,
            reference: $reference,
            requireAmount: $requireAmount,
        );
    }

    private function assertMatchesExpectedValues(
        int $expectedAmount,
        ?string $expectedCurrency,
        string $provider,
        string|int|null $reference,
        bool $requireAmount,
    ): void {
        if ($this->amount === null) {
            if ($requireAmount) {
                throw InvalidPaymentPayloadException::amountMissingFromProvider(
                    provider: $provider,
                    reference: $reference,
                );
            }

            return;
        }

        if ((int) $this->amount !== $expectedAmount) {
            throw InvalidPaymentPayloadException::amountMismatch(
                expectedAmount: $expectedAmount,
                providerAmount: (int) $this->amount,
                provider: $provider,
                reference: $reference,
            );
        }

        if (
            $this->currency !== null
            && $expectedCurrency !== null
            && strtoupper($this->currency) !== strtoupper($expectedCurrency)
        ) {
            throw InvalidPaymentPayloadException::currencyMismatch(
                expectedCurrency: $expectedCurrency,
                providerCurrency: $this->currency,
                provider: $provider,
                reference: $reference,
            );
        }
    }

    public function reference(): ?string
    {
        return $this->references?->providerPaymentId
            ?? $this->references?->providerOrderId;
    }

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
