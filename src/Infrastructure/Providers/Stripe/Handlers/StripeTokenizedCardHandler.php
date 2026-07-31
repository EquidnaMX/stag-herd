<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe\Handlers;

use Equidna\StagHerd\Contracts\PaymentMethodHandler;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\Services\StripeCardPaymentService;

final class StripeTokenizedCardHandler implements PaymentMethodHandler
{
    public function __construct(
        private readonly StripeCardPaymentService $payments,
    ) {
        //
    }

    public function getMethod(): string
    {
        return 'tokenized_card';
    }

    public function createPayment(
        PaymentRequestData $request,
    ): PaymentResultData {
        [$customerId, $paymentMethodId, $offSession] = $this->validateCreatePaymentRequest(
            $request
        );

        return $this->payments->createPayment(
            request: $request,
            method: $this->getMethod(),
            options: [
                'default_description' => 'Tokenized card payment',
                'default_source' => 'stag-herd-tokenized-card',
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'confirm' => 'true',
                'off_session' => $offSession ? true : null,
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

    /**
     * @return array{0: string, 1: string, 2: bool}
     */
    private function validateCreatePaymentRequest(
        PaymentRequestData $request,
    ): array {
        if ($request->amount <= 0) {
            throw InvalidPaymentPayloadException::invalidAmount(
                $request->amount
            );
        }

        if ($request->currency === '') {
            throw InvalidPaymentPayloadException::invalidCurrency(
                $request->currency
            );
        }

        $stripe = $request->metadata['stripe'] ?? [];

        $customerId = $stripe['customer']
            ?? $stripe['customer_id']
            ?? null;

        $paymentMethodId = $stripe['payment_method']
            ?? $stripe['payment_method_id']
            ?? null;

        $offSession = filter_var(
            $stripe['off_session'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        if (
            ! is_string($customerId)
            || trim($customerId) === ''
        ) {
            throw InvalidPaymentPayloadException::missingField(
                'metadata.stripe.customer'
            );
        }

        if (
            ! is_string($paymentMethodId)
            || trim($paymentMethodId) === ''
        ) {
            throw InvalidPaymentPayloadException::missingField(
                'metadata.stripe.payment_method'
            );
        }

        if (! str_starts_with($customerId, 'cus_')) {
            throw InvalidPaymentPayloadException::invalidField(
                'metadata.stripe.customer',
                'Stripe customer must contain a valid cus_... identifier.'
            );
        }

        if (! str_starts_with($paymentMethodId, 'pm_')) {
            throw InvalidPaymentPayloadException::invalidField(
                'metadata.stripe.payment_method',
                'Stripe payment method must contain a valid pm_... identifier.'
            );
        }

        return [$customerId, $paymentMethodId, $offSession];
    }
}
