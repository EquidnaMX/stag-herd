<?php

namespace Equidna\StagHerd\Infrastructure\Persistence;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Infrastructure\Persistence\Models\StagHerdPayment;

class EloquentPaymentRepository implements PaymentRepository
{
    /**
     * Store a payment from a provider result.
     */
    public function storeFromResult(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): Payment {
        $references = $result->references;

        $model = StagHerdPayment::query()->create([
            'provider' => $result->provider,
            'method' => $result->method,

            'amount' => $result->amount ?? $request->amount,
            'currency' => $result->currency ?? $request->currency,

            'status' => $result->status->value,
            'provider_status' => $result->providerStatus,

            'external_reference' => $request->externalReference
                ?? ($result->metadata['external_reference'] ?? null),

            'payer_reference' => $request->payerReference,
            'payer_email' => $request->payerEmail,

            'provider_payment_id' => $references?->providerPaymentId,
            'provider_order_id' => $references?->providerOrderId,
            'provider_transaction_id' => $references?->providerTransactionId,
            'provider_refund_id' => $references?->providerRefundId,

            'metadata' => array_merge(
                $request->metadata,
                $result->metadata,
            ),

            'raw_payload' => $result->rawPayload,
        ]);

        return $this->mapToDomain($model);
    }

    /**
     * Find a payment by local ID.
     */
    public function find(int|string $id): ?Payment
    {
        $model = StagHerdPayment::query()->find($id);

        if (! $model) {
            return null;
        }

        return $this->mapToDomain($model);
    }

    /**
     * Find a payment by host reference.
     */
    public function findByExternalReference(string $externalReference): ?Payment
    {
        $model = StagHerdPayment::query()
            ->where('external_reference', $externalReference)
            ->latest('id')
            ->first();

        if (! $model) {
            return null;
        }

        return $this->mapToDomain($model);
    }

    /**
     * Find a payment by provider reference.
     */
    public function findByProviderReference(
        string $provider,
        string $reference,
    ): ?Payment {
        $model = StagHerdPayment::query()
            ->where('provider', $provider)
            ->where(function ($query) use ($reference) {
                $query
                    ->where('provider_payment_id', $reference)
                    ->orWhere('provider_order_id', $reference)
                    ->orWhere('provider_transaction_id', $reference)
                    ->orWhere('provider_refund_id', $reference);
            })
            ->latest('id')
            ->first();

        if (! $model) {
            return null;
        }

        return $this->mapToDomain($model);
    }

    /**
     * Find a payment by any known reference.
     *
     * @param array<string, mixed> $references
     */
    public function findByAnyProviderReference(
        string $provider,
        array $references,
    ): ?Payment {
        $values = $this->cleanReferenceValues($references);

        if ($values === []) {
            return null;
        }

        $model = StagHerdPayment::query()
            ->where('provider', $provider)
            ->where(function ($query) use ($values) {
                foreach ($values as $value) {
                    $query
                        ->orWhere('provider_payment_id', $value)
                        ->orWhere('provider_order_id', $value)
                        ->orWhere('provider_transaction_id', $value)
                        ->orWhere('provider_refund_id', $value)
                        ->orWhere('external_reference', $value);
                }
            })
            ->latest('id')
            ->first();

        if (! $model) {
            return null;
        }

        return $this->mapToDomain($model);
    }

    /**
     * Update a payment from a provider result.
     */
    public function updateFromResult(
        Payment $payment,
        PaymentResultData $result,
    ): Payment {
        $model = StagHerdPayment::query()->find($payment->id);

        if (! $model) {
            return $payment;
        }

        $references = $result->references;

        $model->update([
            'amount' => $result->amount ?? $model->amount,
            'currency' => $result->currency ?? $model->currency,

            'status' => $result->status->value,
            'provider_status' => $result->providerStatus,

            'provider_payment_id' => $references?->providerPaymentId ?? $model->provider_payment_id,
            'provider_order_id' => $references?->providerOrderId ?? $model->provider_order_id,
            'provider_transaction_id' => $references?->providerTransactionId ?? $model->provider_transaction_id,
            'provider_refund_id' => $references?->providerRefundId ?? $model->provider_refund_id,

            'external_reference' => $result->metadata['external_reference']
                ?? $model->external_reference,

            'metadata' => array_merge(
                $model->metadata ?? [],
                $result->metadata,
            ),

            'raw_payload' => $result->rawPayload ?: $model->raw_payload,
        ]);

        return $this->mapToDomain($model->refresh());
    }

    /**
     * Map an Eloquent model to a domain payment.
     */
    private function mapToDomain(StagHerdPayment $model): Payment
    {
        return new Payment(
            id: (string) $model->id,
            provider: $model->provider,
            method: $model->method,
            amount: (int) $model->amount,
            currency: $model->currency,
            status: PaymentStatusEnum::from($model->status),
            providerStatus: $model->provider_status,
            externalReference: $model->external_reference,
            payerReference: $model->payer_reference,
            payerEmail: $model->payer_email,
            references: new ProviderReferencesData(
                providerPaymentId: $model->provider_payment_id,
                providerOrderId: $model->provider_order_id,
                providerTransactionId: $model->provider_transaction_id,
                providerRefundId: $model->provider_refund_id,
            ),
            metadata: $model->metadata ?? [],
        );
    }

    /**
     * @param array<string, mixed> $references
     * @return array<int, string>
     */
    private function cleanReferenceValues(array $references): array
    {
        return collect($references)
            ->filter(fn($value) => $value !== null && $value !== '')
            ->map(fn($value) => (string) $value)
            ->unique()
            ->values()
            ->all();
    }
}
