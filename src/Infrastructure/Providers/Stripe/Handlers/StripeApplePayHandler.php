<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe\Handlers;

use Equidna\StagHerd\Contracts\PaymentMethodHandler;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\Services\StripeCardPaymentService;

final class StripeApplePayHandler implements PaymentMethodHandler
{
    public function __construct(
        private readonly StripeCardPaymentService $payments,
    ) {
        //
    }

    public function getMethod(): string
    {
        return 'apple_pay';
    }

    public function createPayment(
        PaymentRequestData $request,
    ): PaymentResultData {
        return $this->payments->createPayment(
            request: $request,
            method: $this->getMethod(),
            options: [
                'default_description' => 'Apple Pay payment',
                'default_source' => 'stag-herd-stripe-apple-pay',
                'confirm' => 'true',
            ],
        );
    }

    public function confirmPayment(
        PaymentConfirmationData $request,
    ): PaymentResultData {
        return $this->payments->confirmPayment(
            request: $request,
            method: $this->getMethod(),
        );
    }

    public function lookupPayment(
        PaymentLookupData $request,
    ): PaymentResultData {
        return $this->payments->lookupPayment(
            request: $request,
            method: $this->getMethod(),
        );
    }

    public function cancelPayment(
        PaymentCancellationData $request,
    ): PaymentResultData {
        return $this->payments->cancelPayment(
            request: $request,
            method: $this->getMethod(),
        );
    }

    public function refundPayment(
        RefundRequestData $request,
    ): PaymentResultData {
        return $this->payments->refundPayment(
            request: $request,
            method: $this->getMethod(),
        );
    }
}
