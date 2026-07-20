<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe\Services;

use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeResultMapper;
use Illuminate\Support\Str;

final class StripeCardPaymentService
{
    public function __construct(
        private readonly StripeGateway $gateway,
        private readonly StripeResultMapper $mapper,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createPayment(
        PaymentRequestData $request,
        string $method,
        array $options = [],
    ): PaymentResultData {
        $this->validateBaseRequest($request);

        $response = $this->gateway->createPaymentIntent(
            payload: $this->buildCreatePaymentIntentPayload(
                request: $request,
                options: $options,
            ),
            idempotencyKey: $this->resolveIdempotencyKey($request),
        );

        return $this->mapper->mapPaymentIntentToResult(
            $request,
            $response,
        );
    }

    public function confirmPayment(
        PaymentConfirmationData $request,
        string $method,
    ): PaymentResultData {
        $paymentIntentId = $this->resolvePaymentIntentId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $stripe = $request->metadata['stripe'] ?? [];

        $paymentMethodId = $stripe['payment_method']
            ?? $stripe['payment_method_id']
            ?? $request->metadata['stripe_payment_method_id']
            ?? null;

        $payload = array_filter(
            [
                'payment_method' => $paymentMethodId,
                'return_url' => $stripe['return_url']
                    ?? $request->metadata['return_url']
                    ?? null,
            ],
            fn($value) => $value !== null && $value !== ''
        );

        if ($paymentMethodId) {
            $response = $this->gateway->confirmPaymentIntent(
                paymentIntentId: $paymentIntentId,
                payload: $payload,
                idempotencyKey: $stripe['confirm_idempotency_key']
                    ?? $request->metadata['confirm_idempotency_key']
                    ?? null,
            );
        } else {
            $response = $this->gateway->getPaymentIntent(
                $paymentIntentId
            );
        }

        return $this->mapper->mapPaymentIntentResponseToResult(
            method: $method,
            response: $response,
        );
    }

    public function lookupPayment(
        PaymentLookupData $request,
        string $method,
    ): PaymentResultData {
        return match ($request->lookupType()) {
            'provider_payment_id' => $this->lookupByPaymentIntentId(
                $request,
                $method,
            ),

            'provider_order_id' => throw UnsupportedOperationException::forOperation(
                'lookup',
                sprintf(
                    'Stripe %s lookup requires providerPaymentId. Use PaymentIntent id, for example pi_...',
                    $method,
                )
            ),

            default => throw UnsupportedOperationException::forOperation(
                'lookup',
                sprintf(
                    'Stripe %s lookup requires providerPaymentId.',
                    $method,
                )
            ),
        };
    }

    public function cancelPayment(
        PaymentCancellationData $request,
        string $method,
    ): PaymentResultData {
        $paymentIntentId = $this->resolvePaymentIntentId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $response = $this->gateway->cancelPaymentIntent(
            $paymentIntentId
        );

        return $this->mapper->mapPaymentIntentResponseToResult(
            method: $method,
            response: $response,
        );
    }

    public function refundPayment(
        RefundRequestData $request,
        string $method,
    ): PaymentResultData {
        $paymentIntentId = $this->resolvePaymentIntentId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $payload = array_filter(
            [
                'payment_intent' => $paymentIntentId,
                'amount' => $request->amount,
                'reason' => $this->normalizeRefundReason($request->reason),
            ],
            fn($value) => $value !== null && $value !== ''
        );

        $response = $this->gateway->createRefund(
            payload: $payload,
            idempotencyKey: data_get(
                $request->metadata,
                'stripe.refund_idempotency_key'
            ) ?? data_get(
                $request->metadata,
                'idempotency_key'
            ),
        );

        return $this->mapper->mapRefundResponseToResult(
            $request,
            $response,
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildCreatePaymentIntentPayload(
        PaymentRequestData $request,
        array $options = [],
    ): array {
        $stripe = $request->metadata['stripe'] ?? [];

        $payload = [
            'amount' => $request->amount,
            'currency' => strtolower($request->currency),
            'payment_method_types' => ['card'],
            'description' => $request->description
                ?? $request->externalReference
                ?? ($options['default_description'] ?? 'Payment'),
            'receipt_email' => $request->payerEmail,
            'metadata' => array_filter(
                [
                    'external_reference' => $request->externalReference,
                    'payer_reference' => $request->payerReference,
                    'source' => $request->metadata['source']
                        ?? ($options['default_source'] ?? null),
                ],
                fn($value) => $value !== null && $value !== ''
            ),
        ];

        $optionalFields = [
            'customer',
            'payment_method',
            'capture_method',
            'statement_descriptor',
            'statement_descriptor_suffix',
            'setup_future_usage',
            'application_fee_amount',
            'transfer_group',
            'on_behalf_of',
            'return_url',
        ];

        foreach ($optionalFields as $field) {
            if (array_key_exists($field, $stripe)) {
                $payload[$field] = $stripe[$field];
            }
        }

        if (
            array_key_exists('customer', $options)
            && $options['customer'] !== null
            && $options['customer'] !== ''
        ) {
            $payload['customer'] = $options['customer'];
        }

        if (
            array_key_exists('payment_method', $options)
            && $options['payment_method'] !== null
            && $options['payment_method'] !== ''
        ) {
            $payload['payment_method'] = $options['payment_method'];
        }

        if (
            array_key_exists('confirm', $options)
            && $options['confirm'] !== null
        ) {
            $payload['confirm'] = $options['confirm'];
        }

        if (
            array_key_exists('off_session', $options)
            && $options['off_session'] !== null
        ) {
            $payload['off_session'] = $options['off_session'];
        }

        if (
            ! array_key_exists('return_url', $payload)
            && is_string($request->returnUrl)
            && trim($request->returnUrl) !== ''
        ) {
            $payload['return_url'] = $request->returnUrl;
        }

        if (
            isset($stripe['metadata'])
            && is_array($stripe['metadata'])
        ) {
            $payload['metadata'] = array_replace_recursive(
                $payload['metadata'],
                $stripe['metadata'],
            );
        }

        return array_filter(
            $payload,
            fn($value) => $value !== null
                && $value !== []
                && $value !== '',
        );
    }

    private function lookupByPaymentIntentId(
        PaymentLookupData $request,
        string $method,
    ): PaymentResultData {
        $response = $this->gateway->getPaymentIntent(
            (string) $request->providerPaymentId
        );

        return $this->mapper->mapPaymentIntentResponseToResult(
            method: $method,
            response: $response,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function resolvePaymentIntentId(
        ?string $providerPaymentId,
        array $metadata = [],
    ): string {
        $resolved = $providerPaymentId
            ?? data_get($metadata, 'stripe_payment_intent_id')
            ?? data_get($metadata, 'provider_payment_id');

        if (! $resolved) {
            throw InvalidPaymentPayloadException::missingField(
                'provider_payment_id'
            );
        }

        return (string) $resolved;
    }

    private function resolveIdempotencyKey(
        PaymentRequestData $request,
    ): string {
        $stripe = $request->metadata['stripe'] ?? [];

        $idempotencyKey = $stripe['idempotency_key']
            ?? $request->metadata['idempotency_key']
            ?? null;

        if (
            $idempotencyKey !== null
            && $idempotencyKey !== ''
        ) {
            return substr((string) $idempotencyKey, 0, 255);
        }

        return 'stag-herd-stripe-' . (string) Str::uuid();
    }

    private function validateBaseRequest(
        PaymentRequestData $request,
    ): void {
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
    }

    private function normalizeRefundReason(
        ?string $reason,
    ): ?string {
        return match ($reason) {
            'duplicate',
            'fraudulent',
            'requested_by_customer' => $reason,
            default => null,
        };
    }
}
