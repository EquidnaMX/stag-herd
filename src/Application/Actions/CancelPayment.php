<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Domain\PaymentStateMachine;
use Equidna\StagHerd\Exceptions\PaymentNotFoundException;
use Equidna\StagHerd\Support\PaymentEventDispatcher;
use Equidna\StagHerd\Support\ProviderRegistry;

final readonly class CancelPayment
{
    public function __construct(
        private ProviderRegistry $providers,
        private PaymentRepository $payments,
    ) {
        //
    }

    public function handle(PaymentCancellationData $request): Payment
    {
        $payment = $this->resolveLocalPayment($request);

        PaymentStateMachine::assertCanBeCanceled($payment);

        $provider = $this->providers->get($request->provider);

        $result = $provider->cancelPayment(
            new PaymentCancellationData(
                provider: $request->provider,
                method: $payment->method,
                paymentId: $payment->id,
                providerPaymentId: $payment->references?->providerPaymentId
                    ?? $request->providerPaymentId,
                externalReference: $payment->externalReference
                    ?? $request->externalReference,
                reason: $request->reason,
                metadata: array_merge($payment->metadata, $request->metadata, [
                    'method' => $payment->method,
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

    private function resolveLocalPayment(PaymentCancellationData $request): Payment
    {
        if ($request->paymentId) {
            $payment = $this->payments->find($request->paymentId);

            if ($payment) {
                return $payment;
            }
        }

        throw PaymentNotFoundException::withProviderReference(
            $request->provider,
            $request->providerPaymentId
                ?? $request->externalReference
                ?? (string) $request->paymentId,
        );
    }
}
