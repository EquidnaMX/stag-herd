<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Domain\PaymentStateMachine;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Exceptions\PaymentNotFoundException;
use Equidna\StagHerd\Support\PaymentEventDispatcher;
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
        if ($lookup->lookupType() === 'payment_id') {
            throw InvalidPaymentPayloadException::invalidField(
                'paymentId',
                'SyncPayment only accepts providerPaymentId or providerOrderId. Use LookupPayment to refresh a local payment.'
            );
        }

        return $this->syncFromProviderReference(
            lookup: $lookup,
            fallbackRequest: $fallbackRequest,
        );
    }

    private function syncFromProviderReference(
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

        $externalResult->assertMatchesRequest(
            request: $fallbackRequest,
            requireAmount: true,
        );

        $providerPaymentId = $externalResult->references?->providerPaymentId
            ?? $lookup->providerPaymentId;

        $providerOrderId = $externalResult->references?->providerOrderId
            ?? $lookup->providerOrderId;

        $localPayment = null;

        if ($providerPaymentId) {
            $localPayment = $this->payments->findByProviderPaymentId(
                provider: $lookup->provider,
                providerPaymentId: $providerPaymentId,
            );
        }

        if (! $localPayment && $providerOrderId) {
            $localPayment = $this->payments->findByProviderOrderId(
                provider: $lookup->provider,
                providerOrderId: $providerOrderId,
            );
        }

        if (! $localPayment) {
            $payment = $this->payments->storeFromResult(
                request: $fallbackRequest,
                result: $externalResult,
            );

            PaymentEventDispatcher::dispatchForPayment($payment);

            return $payment;
        }

        $externalResult->assertMatchesPayment(
            payment: $localPayment,
            requireAmount: true,
        );

        PaymentStateMachine::assertCanApplyProviderResult(
            payment: $localPayment,
            providerResultStatus: $externalResult->status,
        );

        $updatedPayment = $this->payments->updateFromResult(
            payment: $localPayment,
            result: $externalResult,
        );

        PaymentEventDispatcher::dispatchForPayment(
            payment: $updatedPayment,
            previousPayment: $localPayment,
        );

        return $updatedPayment;
    }

    private function resolveLookupReference(PaymentLookupData $lookup): string
    {
        return $lookup->providerPaymentId
            ?? $lookup->providerOrderId
            ?? $lookup->paymentId
            ?? 'unknown';
    }
}
