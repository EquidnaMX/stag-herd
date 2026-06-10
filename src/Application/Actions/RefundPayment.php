<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Domain\PaymentStateMachine;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Exceptions\PaymentNotFoundException;
use Equidna\StagHerd\Support\PaymentEventDispatcher;
use Equidna\StagHerd\Support\ProviderRegistry;

final readonly class RefundPayment
{
    public function __construct(
        private ProviderRegistry $providers,
        private PaymentRepository $payments,
    ) {
        //
    }

    public function handle(RefundRequestData $request): Payment
    {
        if ($request->amount !== null && $request->amount <= 0) {
            throw InvalidPaymentPayloadException::invalidAmount($request->amount);
        }

        $payment = $this->resolveLocalPayment($request);

        PaymentStateMachine::assertCanBeRefunded($payment);

        $provider = $this->providers->get($request->provider);

        $result = $provider->refundPayment(
            new RefundRequestData(
                provider: $request->provider,
                method: $payment->method,
                paymentId: $payment->id,
                providerPaymentId: $payment->references?->providerPaymentId
                    ?? $request->providerPaymentId,
                externalReference: $payment->externalReference
                    ?? $request->externalReference,
                amount: $request->amount,
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

    private function resolveLocalPayment(RefundRequestData $request): Payment
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
