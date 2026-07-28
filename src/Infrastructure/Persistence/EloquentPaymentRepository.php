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
    public function storeFromResult(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): Payment {
        $references = $result->references;

        $metadata = $this->mergeMetadata(
            $request->metadata,
            $result->metadata,
            [
                'external_reference' => $request->externalReference
                    ?? ($result->metadata['external_reference'] ?? null),

                'provider_transaction_id' => $references?->providerTransactionId,
                'provider_refund_id' => $references?->providerRefundId,
            ],
        );

        $model = StagHerdPayment::query()->create([
            'provider' => $result->provider,
            'method' => $result->method,

            'amount' => $result->amount ?? $request->amount,
            'currency' => $result->currency ?? $request->currency,

            'status' => $result->status->value,
            'provider_status' => $result->providerStatus,

            'payer_reference' => $request->payerReference,
            'payer_email' => $result->payerEmail ?: $request->payerEmail,

            'provider_payment_id' => $references?->providerPaymentId,
            'provider_order_id' => $references?->providerOrderId
                ?? $request->providerOrderId,

            'metadata' => $metadata,
            'raw_payload' => $result->rawPayload,
        ]);

        return $this->mapToDomain($model);
    }

    public function find(int|string $id): ?Payment
    {
        $model = StagHerdPayment::query()->find($id);

        return $model ? $this->mapToDomain($model) : null;
    }

    public function findByProviderPaymentId(
        string $provider,
        string $providerPaymentId,
    ): ?Payment {
        $model = StagHerdPayment::query()
            ->where('provider', $provider)
            ->where('provider_payment_id', $providerPaymentId)
            ->latest('id')
            ->first();

        return $model ? $this->mapToDomain($model) : null;
    }

    public function findByProviderOrderId(
        string $provider,
        string $providerOrderId,
    ): ?Payment {
        $model = StagHerdPayment::query()
            ->where('provider', $provider)
            ->where('provider_order_id', $providerOrderId)
            ->latest('id')
            ->first();

        return $model ? $this->mapToDomain($model) : null;
    }

    public function findByExternalReference(string $externalReference): ?Payment
    {
        $model = StagHerdPayment::query()
            ->where('metadata->external_reference', $externalReference)
            ->latest('id')
            ->first();

        return $model ? $this->mapToDomain($model) : null;
    }

    public function updateFromResult(
        Payment $payment,
        PaymentResultData $result,
    ): Payment {
        $model = StagHerdPayment::query()->find($payment->id);

        if (! $model) {
            return $payment;
        }

        $references = $result->references;

        $metadata = $this->mergeMetadata(
            $model->metadata ?? [],
            $result->metadata,
            [
                'external_reference' => $result->metadata['external_reference']
                    ?? $payment->externalReference,

                'provider_transaction_id' => $references?->providerTransactionId
                    ?? data_get($model->metadata, 'provider_transaction_id'),

                'provider_refund_id' => $references?->providerRefundId
                    ?? data_get($model->metadata, 'provider_refund_id'),
            ],
        );

        $model->update([
            'amount' => $result->amount ?? $model->amount,
            'currency' => $result->currency ?? $model->currency,

            'status' => $result->status->value,
            'provider_status' => $result->providerStatus ?? $model->provider_status,

            'provider_payment_id' => $references?->providerPaymentId
                ?? $model->provider_payment_id,

            'provider_order_id' => $references?->providerOrderId
                ?? $model->provider_order_id,

            'payer_email' => $result->payerEmail ?: $model->payer_email,

            'metadata' => $metadata,
            'raw_payload' => $result->rawPayload !== []
                ? $result->rawPayload
                : $model->raw_payload,
        ]);

        return $this->mapToDomain($model->refresh());
    }

    private function mapToDomain(StagHerdPayment $model): Payment
    {
        $metadata = $model->metadata ?? [];

        return new Payment(
            id: (string) $model->id,
            provider: $model->provider,
            method: $model->method,
            amount: (int) $model->amount,
            currency: $model->currency,
            status: PaymentStatusEnum::from($model->status),
            providerStatus: $model->provider_status,
            externalReference: data_get($metadata, 'external_reference'),
            payerReference: $model->payer_reference,
            payerEmail: $model->payer_email,
            references: new ProviderReferencesData(
                providerPaymentId: $model->provider_payment_id,
                providerOrderId: $model->provider_order_id,
                providerTransactionId: data_get($metadata, 'provider_transaction_id'),
                providerRefundId: data_get($metadata, 'provider_refund_id'),
            ),
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> ...$metadataGroups
     * @return array<string, mixed>
     */
    private function mergeMetadata(array ...$metadataGroups): array
    {
        $metadata = [];

        foreach ($metadataGroups as $group) {
            foreach ($group as $key => $value) {
                if ($value !== null) {
                    $metadata[$key] = $value;
                }
            }
        }

        return $metadata;
    }
}
