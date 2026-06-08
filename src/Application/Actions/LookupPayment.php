<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Domain\Payment;
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
                providerPaymentId: $providerPaymentId,
            )
        );

        $updatedPayment = $this->payments->updateFromResult($payment, $result);

        PaymentEventDispatcher::dispatchForPayment(
            payment: $updatedPayment,
            previousPayment: $payment,
        );

        return $updatedPayment;
    }

    private function lookupByProviderPaymentId(PaymentLookupData $lookup): Payment
    {
        $provider = $this->providers->get($lookup->provider);

        $result = $provider->lookupPayment($lookup);

        $providerPaymentId = $result->references?->providerPaymentId
            ?? $lookup->providerPaymentId;

        if (! $providerPaymentId) {
            throw PaymentNotFoundException::withProviderReference(
                $lookup->provider,
                $lookup->lookupValue(),
            );
        }

        $payment = $this->payments->findByProviderPaymentId(
            $lookup->provider,
            $providerPaymentId,
        );

        if (! $payment) {
            throw PaymentNotFoundException::withProviderReference(
                $lookup->provider,
                $providerPaymentId,
            );
        }

        return $this->payments->updateFromResult($payment, $result);
    }

    private function lookupByProviderOrderId(PaymentLookupData $lookup): Payment
    {
        $provider = $this->providers->get($lookup->provider);

        $result = $provider->lookupPayment($lookup);

        $providerPaymentId = $result->references?->providerPaymentId;

        if (! $providerPaymentId) {
            throw PaymentNotFoundException::withProviderReference(
                $lookup->provider,
                $lookup->providerOrderId,
            );
        }

        $payment = $this->payments->findByProviderPaymentId(
            $lookup->provider,
            $providerPaymentId,
        );

        if (! $payment) {
            throw PaymentNotFoundException::withProviderReference(
                $lookup->provider,
                $providerPaymentId,
            );
        }

        return $this->payments->updateFromResult($payment, $result);
    }
}
