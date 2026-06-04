<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Exceptions\PaymentNotFoundException;
use Equidna\StagHerd\Support\ProviderRegistry;

final readonly class SyncPayment
{
    public function __construct(
        private ProviderRegistry $providers,
        private PaymentRepository $payments,
    ) {
        //
    }

    public function handle(
        PaymentLookupData $lookup,
        PaymentRequestData $fallbackRequest,
    ): Payment {
        $provider = $this->providers->get($lookup->provider);

        $externalResult = $provider->lookupPayment($lookup);

        if (! $externalResult instanceof PaymentResultData) {
            throw PaymentNotFoundException::withProviderReference(
                $lookup->provider,
                $this->resolveLookupReference($lookup),
            );
        }

        $references = array_filter([
            'provider_payment_id' => $externalResult->references?->providerPaymentId,
            'provider_order_id' => $externalResult->references?->providerOrderId,
            'provider_transaction_id' => $externalResult->references?->providerTransactionId,
            'provider_refund_id' => $externalResult->references?->providerRefundId,
            'external_reference' => $externalResult->metadata['external_reference']
                ?? $fallbackRequest->externalReference
                ?? $lookup->externalReference,
        ]);

        $localPayment = $this->payments->findByAnyProviderReference(
            provider: $lookup->provider,
            references: $references,
        );

        if (! $localPayment) {
            return $this->payments->storeFromResult(
                request: $fallbackRequest,
                result: $externalResult,
            );
        }

        return $this->payments->updateFromResult(
            payment: $localPayment,
            result: $externalResult,
        );
    }

    private function resolveLookupReference(PaymentLookupData $lookup): string
    {
        return $lookup->providerPaymentId
            ?? $lookup->externalReference
            ?? $lookup->paymentId
            ?? 'unknown';
    }
}
