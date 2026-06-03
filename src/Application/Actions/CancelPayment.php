<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Exceptions\PaymentNotFoundException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
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

        if ($payment->status->isFinal()) {
            throw UnsupportedOperationException::forPaymentStatus(
                'cancel',
                $payment->status->value,
            );
        }

        $provider = $this->providers->get($request->provider);

        $result = $provider->cancelPayment(
            new PaymentCancellationData(
                provider: $request->provider,
                paymentId: $payment->id,
                providerPaymentId: $payment->references?->providerPaymentId ?? $request->providerPaymentId,
                externalReference: $payment->externalReference ?? $request->externalReference,
                reason: $request->reason,
                metadata: array_merge($payment->metadata, $request->metadata, [
                    'method' => $payment->method,
                ]),
            )
        );

        return $this->payments->updateFromResult($payment, $result);
    }

    private function resolveLocalPayment(PaymentCancellationData $request): Payment
    {
        if ($request->paymentId) {
            $payment = $this->payments->find($request->paymentId);

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

        if ($request->providerPaymentId) {
            $payment = $this->payments->findByProviderReference(
                $request->provider,
                $request->providerPaymentId,
            );

            if ($payment) {
                return $payment;
            }
        }

        throw PaymentNotFoundException::withProviderReference(
            $request->provider,
            $request->providerPaymentId ?? $request->externalReference ?? (string) $request->paymentId,
        );
    }
}
