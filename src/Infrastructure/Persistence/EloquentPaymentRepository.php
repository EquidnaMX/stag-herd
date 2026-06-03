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

            'external_reference' => $request->externalReference,
            'payer_reference' => $request->payerReference,
            'payer_email' => $request->payerEmail,

            'provider_payment_id' => $references?->providerPaymentId,
            'provider_order_id' => $references?->providerOrderId,
            'provider_transaction_id' => $references?->providerTransactionId,
            'provider_refund_id' => $references?->providerRefundId,

            'metadata' => $result->metadata ?: $request->metadata,
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
            'status' => $result->status->value,
            'provider_status' => $result->providerStatus,

            'provider_payment_id' => $references?->providerPaymentId ?? $model->provider_payment_id,
            'provider_order_id' => $references?->providerOrderId ?? $model->provider_order_id,
            'provider_transaction_id' => $references?->providerTransactionId ?? $model->provider_transaction_id,
            'provider_refund_id' => $references?->providerRefundId ?? $model->provider_refund_id,

            'metadata' => array_merge($model->metadata ?? [], $result->metadata),
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
}
