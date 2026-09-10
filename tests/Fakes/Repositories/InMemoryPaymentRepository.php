<?php

namespace Equidna\StagHerd\Tests\Fakes\Repositories;

use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Domain\Payment;

class InMemoryPaymentRepository implements PaymentRepository
{
    /**
     * @var array<string, Payment>
     */
    private array $payments = [];

    private int $nextId = 1;

    public function storeFromResult(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): Payment {
        $payment = new Payment(
            id: (string) $this->nextId++,
            provider: $result->provider,
            method: $result->method,
            amount: $result->amount ?? $request->amount,
            currency: $result->currency ?? $request->currency,
            status: $result->status,
            providerStatus: $result->providerStatus,
            externalReference: $request->externalReference,
            payerReference: $request->payerReference,
            payerEmail: $request->payerEmail,
            references: $result->references ?? new ProviderReferencesData(),
            metadata: $result->metadata ?: $request->metadata,
        );

        $this->payments[$payment->id] = $payment;

        return $payment;
    }

    public function find(int|string $id): ?Payment
    {
        return $this->payments[(string) $id] ?? null;
    }

    public function findByProviderPaymentId(
        string $provider,
        string $providerPaymentId,
    ): ?Payment {
        foreach ($this->payments as $payment) {
            if ($payment->provider !== $provider) {
                continue;
            }

            if ($payment->references?->providerPaymentId === $providerPaymentId) {
                return $payment;
            }
        }

        return null;
    }

    public function findByExternalReference(string $externalReference): ?Payment
    {
        foreach ($this->payments as $payment) {
            if ($payment->externalReference === $externalReference) {
                return $payment;
            }
        }

        return null;
    }

    public function findByProviderOrderId(
        string $provider,
        string $providerOrderId,
    ): ?Payment {
        foreach ($this->payments as $payment) {
            if ($payment->provider !== $provider) {
                continue;
            }

            if ($payment->references?->providerOrderId === $providerOrderId) {
                return $payment;
            }
        }

        return null;
    }

    public function updateFromResult(
        Payment $payment,
        PaymentResultData $result,
    ): Payment {
        $updated = new Payment(
            id: $payment->id,
            provider: $payment->provider,
            method: $payment->method,
            amount: $result->amount ?? $payment->amount,
            currency: $result->currency ?? $payment->currency,
            status: $result->status,
            providerStatus: $result->providerStatus,
            externalReference: $payment->externalReference,
            payerReference: $payment->payerReference,
            payerEmail: $payment->payerEmail,
            references: $result->references ?? $payment->references,
            metadata: array_merge($payment->metadata, $result->metadata),
        );

        $this->payments[$payment->id] = $updated;

        return $updated;
    }
}
