<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Domain\PaymentStateMachine;
use Equidna\StagHerd\Exceptions\PaymentNotFoundException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Support\PaymentEventDispatcher;
use Equidna\StagHerd\Support\ProviderRegistry;

final readonly class LookupPayment
{
    public function __construct(
        private ProviderRegistry $providers,
        private PaymentRepository $payments,
    ) {
        //
    }

    public function handle(PaymentLookupData $lookup): Payment
    {
        return match ($lookup->lookupType()) {
            'payment_id' => $this->lookupByLocalPaymentId($lookup),
            'provider_payment_id' => $this->lookupByProviderPaymentId($lookup),
            'provider_order_id' => $this->lookupByProviderOrderId($lookup),

            default => throw UnsupportedOperationException::forOperation(
                'lookup',
                "Unsupported lookup type [{$lookup->lookupType()}]."
            ),
        };
    }

    private function lookupByLocalPaymentId(PaymentLookupData $lookup): Payment
    {
        $payment = $this->payments->find($lookup->paymentId);

        if (! $payment) {
            throw PaymentNotFoundException::withId($lookup->paymentId);
        }

        $providerPaymentId = $payment->references?->providerPaymentId;

        if (! $providerPaymentId) {
            return $payment;
        }

        $provider = $this->providers->get($payment->provider);

        $result = $provider->lookupPayment(
            new PaymentLookupData(
                provider: $payment->provider,
                method: $payment->method,
                providerPaymentId: $providerPaymentId,
            )
        );

        return $this->applyProviderResult(
            payment: $payment,
            result: $result,
            requireAmount: false,
        );
    }

    private function lookupByProviderPaymentId(PaymentLookupData $lookup): Payment
    {
        $payment = $this->payments->findByProviderPaymentId(
            provider: $lookup->provider,
            providerPaymentId: (string) $lookup->providerPaymentId,
        );

        $provider = $this->providers->get($lookup->provider);

        $result = $provider->lookupPayment(
            new PaymentLookupData(
                provider: $lookup->provider,
                method: $payment?->method ?? $lookup->method,
                providerPaymentId: $lookup->providerPaymentId,
            )
        );

        $providerPaymentId = $result->references?->providerPaymentId
            ?? $lookup->providerPaymentId;

        if (! $providerPaymentId) {
            throw PaymentNotFoundException::withProviderReference(
                $lookup->provider,
                $lookup->lookupValue(),
            );
        }

        $payment ??= $this->payments->findByProviderPaymentId(
            provider: $lookup->provider,
            providerPaymentId: $providerPaymentId,
        );

        if (! $payment) {
            throw PaymentNotFoundException::withProviderReference(
                $lookup->provider,
                $providerPaymentId,
            );
        }

        return $this->applyProviderResult(
            payment: $payment,
            result: $result,
            requireAmount: false,
        );
    }

    private function lookupByProviderOrderId(PaymentLookupData $lookup): Payment
    {
        $payment = $this->payments->findByProviderReference(
            provider: $lookup->provider,
            reference: (string) $lookup->providerOrderId,
        );

        $provider = $this->providers->get($lookup->provider);

        $result = $provider->lookupPayment(
            new PaymentLookupData(
                provider: $lookup->provider,
                method: $payment?->method ?? $lookup->method,
                providerOrderId: $lookup->providerOrderId,
            )
        );

        $providerPaymentId = $result->references?->providerPaymentId;
        $providerOrderId = $result->references?->providerOrderId
            ?? $lookup->providerOrderId;

        if (! $payment && $providerPaymentId) {
            $payment = $this->payments->findByProviderPaymentId(
                provider: $lookup->provider,
                providerPaymentId: $providerPaymentId,
            );
        }

        if (! $payment && $providerOrderId) {
            $payment = $this->payments->findByProviderReference(
                provider: $lookup->provider,
                reference: $providerOrderId,
            );
        }

        if (! $payment) {
            throw PaymentNotFoundException::withProviderReference(
                $lookup->provider,
                $providerPaymentId ?? $providerOrderId ?? $lookup->lookupValue(),
            );
        }

        return $this->applyProviderResult(
            payment: $payment,
            result: $result,
            requireAmount: false,
        );
    }

    private function applyProviderResult(
        Payment $payment,
        PaymentResultData $result,
        bool $requireAmount,
    ): Payment {
        $result->assertMatchesPayment(
            payment: $payment,
            requireAmount: $requireAmount,
        );

        PaymentStateMachine::assertCanApplyProviderResult(
            payment: $payment,
            providerResultStatus: $result->status,
        );

        $updatedPayment = $this->payments->updateFromResult(
            payment: $payment,
            result: $result,
        );

        PaymentEventDispatcher::dispatchForPayment(
            payment: $updatedPayment,
            previousPayment: $payment,
        );

        return $updatedPayment;
    }
}
