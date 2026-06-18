<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\ConfirmsPayments;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Domain\PaymentStateMachine;
use Equidna\StagHerd\Exceptions\PaymentNotFoundException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Support\PaymentEventDispatcher;
use Equidna\StagHerd\Support\ProviderRegistry;

final readonly class ConfirmPayment
{
    public function __construct(
        private ProviderRegistry $providers,
        private PaymentRepository $payments,
    ) {
        //
    }

    public function handle(PaymentConfirmationData $request): Payment
    {
        $payment = $this->resolveLocalPayment($request);

        $provider = $this->providers->get($payment->provider);

        if (! $provider instanceof ConfirmsPayments) {
            throw UnsupportedOperationException::forProvider(
                provider: $payment->provider,
                operation: 'confirm',
            );
        }

        $result = $provider->confirmPayment(
            new PaymentConfirmationData(
                provider: $payment->provider,
                method: $payment->method,
                paymentId: (string) $payment->id,
                providerPaymentId: $payment->references?->providerPaymentId
                    ?? $request->providerPaymentId,
                externalReference: $payment->externalReference
                    ?? $request->externalReference,
                reason: $request->reason,
                metadata: array_merge($payment->metadata, $request->metadata, [
                    'method' => $payment->method,
                    'provider' => $payment->provider,
                    'confirmation_source' => 'stag_herd',
                ]),
            )
        );

        $result->assertMatchesPayment(
            payment: $payment,
            requireAmount: false,
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

    private function resolveLocalPayment(PaymentConfirmationData $request): Payment
    {
        if ($request->paymentId) {
            $payment = $this->payments->find($request->paymentId);

            if ($payment) {
                return $payment;
            }
        }

        if ($request->providerPaymentId) {
            $payment = $this->payments->findByProviderPaymentId(
                provider: $request->provider,
                providerPaymentId: $request->providerPaymentId,
            );

            if ($payment) {
                return $payment;
            }
        }

        if ($request->externalReference) {
            $payment = $this->payments->findByExternalReference($request->externalReference);

            if ($payment) {
                return $payment;
            }
        }

        if ($request->paymentId) {
            throw PaymentNotFoundException::withId($request->paymentId);
        }

        if ($request->externalReference) {
            throw PaymentNotFoundException::withExternalReference($request->externalReference);
        }

        throw PaymentNotFoundException::withProviderReference(
            provider: $request->provider,
            reference: $request->providerPaymentId ?? 'unknown',
        );
    }
}
