<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe\Handlers;

use Equidna\StagHerd\Contracts\ExtractsPaymentMethodFromPayment;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\Services\StripeCardPaymentService;
use Equidna\StagHerd\Contracts\PaymentMethodHandler;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;

final class StripeCardHandler implements PaymentMethodHandler, ExtractsPaymentMethodFromPayment
{
    public function __construct(
        private readonly StripeCardPaymentService $payments,
    ) {
        //
    }

    public function getMethod(): string
    {
        return 'card';
    }

    public function createPayment(
        PaymentRequestData $request,
    ): PaymentResultData {
        return $this->payments->createPayment(
            request: $request,
            method: $this->getMethod(),
            options: [
                'default_description' => 'Payment',
                'default_source' => null,
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

    public function paymentMethodFromPayment(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): ?PaymentMethodRegisterData {
        $ownerReference = $this->firstString([
            $request->payerReference,
            data_get($request->metadata, 'payer_reference'),
            data_get($request->metadata, 'id_client'),
            data_get($request->metadata, 'method_data.payer_reference'),
            data_get($request->metadata, 'method_data.metadata.payer_reference'),
        ]);

        $customerId = $this->firstString([
            data_get($result->metadata, 'stripe_customer_id'),
            data_get($request->metadata, 'stripe_customer_id'),
            data_get($request->metadata, 'method_data.metadata.stripe_customer_id'),
            data_get($result->rawPayload, 'customer'),
        ]);

        $paymentMethodId = $this->firstString([
            data_get($result->metadata, 'stripe_payment_method_id'),
            data_get($request->metadata, 'stripe_payment_method_id'),
            data_get($request->metadata, 'method_data.metadata.stripe_payment_method_id'),
            data_get($result->rawPayload, 'payment_method'),
        ]);

        if ($ownerReference === null || $customerId === null || $paymentMethodId === null) {
            return null;
        }

        $card = $this->cardData($request, $result);

        return new PaymentMethodRegisterData(
            provider: 'stripe',
            ownerReference: $ownerReference,
            providerCustomerId: $customerId,
            providerPaymentMethodId: $paymentMethodId,
            credentialContext: $request->credentialContext,
            fingerprint: $this->firstString([
                data_get($result->metadata, 'stripe_payment_method_fingerprint'),
                data_get($card, 'fingerprint'),
            ]),
            displayName: $this->firstString([
                data_get($request->metadata, 'card_display_name'),
                data_get($request->metadata, 'method_data.metadata.card_display_name'),
            ]),
            brand: $this->firstString([data_get($card, 'brand')]),
            last4: $this->firstString([data_get($card, 'last4')]),
            expMonth: $this->firstInt([data_get($card, 'exp_month')]),
            expYear: $this->firstInt([data_get($card, 'exp_year')]),
            payload: [
                'payment_result' => $result->toArray(),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function cardData(PaymentRequestData $request, PaymentResultData $result): array
    {
        $card = data_get($result->metadata, 'card');

        if (is_array($card)) {
            return $card;
        }

        $card = data_get($request->metadata, 'card');

        if (is_array($card)) {
            return $card;
        }

        $card = data_get($request->metadata, 'method_data.metadata.card');

        return is_array($card) ? $card : [];
    }

    /** @param array<int, mixed> $values */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /** @param array<int, mixed> $values */
    private function firstInt(array $values): ?int
    {
        foreach ($values as $value) {
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }
}
