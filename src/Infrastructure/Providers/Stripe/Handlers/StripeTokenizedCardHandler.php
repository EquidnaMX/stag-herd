<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe\Handlers;

use Equidna\StagHerd\Contracts\ManagesSavedPaymentMethods;
use Equidna\StagHerd\Contracts\PaymentMethodHandler;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Data\SavedPaymentMethodLookupData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\Services\StripeCardPaymentService;

final class StripeTokenizedCardHandler implements PaymentMethodHandler
{
    public function __construct(
        private readonly StripeCardPaymentService $payments,
        private readonly ManagesSavedPaymentMethods $savedPaymentMethods,
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

        $stripe = is_array($request->metadata['stripe'] ?? null)
            ? $request->metadata['stripe']
            : [];

        $customerId = $this->nullableString(
            $stripe['customer'] ?? $stripe['customer_id'] ?? null
        );

        $paymentMethodId = $this->nullableString(
            $stripe['payment_method'] ?? $stripe['payment_method_id'] ?? null
        );

        $offSession = filter_var(
            $stripe['off_session'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        if ($customerId !== null) {
            if ($paymentMethodId === null) {
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

        $ownerReference = trim((string) ($request->payerReference ?? ''));

        if ($ownerReference === '') {
            throw InvalidPaymentPayloadException::missingField(
                'payer_reference'
            );
        }

        if (
            $paymentMethodId !== null
            && ! str_starts_with($paymentMethodId, 'pm_')
        ) {
            throw InvalidPaymentPayloadException::invalidField(
                'metadata.stripe.payment_method',
                'Saved Stripe payment method lookup expects a valid pm_... identifier.'
            );
        }

        $savedPaymentMethod = $this->savedPaymentMethods->resolveUsable(
            new SavedPaymentMethodLookupData(
                provider: 'stripe',
                ownerReference: $ownerReference,
                credentialContext: $request->credentialContext,
                providerPaymentMethodId: $paymentMethodId,
            )
        );

        if (! str_starts_with($savedPaymentMethod->providerCustomerId, 'cus_')) {
            throw InvalidPaymentPayloadException::invalidField(
                'saved_payment_method.provider_customer_id',
                'Saved Stripe payment method resolved an invalid cus_... customer identifier.'
            );
        }

        if (! str_starts_with($savedPaymentMethod->providerPaymentMethodId, 'pm_')) {
            throw InvalidPaymentPayloadException::invalidField(
                'saved_payment_method.provider_payment_method_id',
                'Saved Stripe payment method resolved an invalid pm_... payment method identifier.'
            );
        }

        return [
            $savedPaymentMethod->providerCustomerId,
            $savedPaymentMethod->providerPaymentMethodId,
            $offSession,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
