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
use Equidna\StagHerd\Infrastructure\Providers\Stripe\Services\StripeSpeiPaymentService;

final class StripeSpeiHandler implements PaymentMethodHandler
{
    public function __construct(
        private readonly StripeSpeiPaymentService $speiPayments,
        private readonly StripeCardPaymentService $payments,
    ) {
        //
    }

    public function getMethod(): string
    {
        return 'spei';
    }

    public function createPayment(
        PaymentRequestData $request,
    ): PaymentResultData {
        return $this->speiPayments->createPayment(
            request: $request,
            method: $this->getMethod(),
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
