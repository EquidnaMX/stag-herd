<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe\Handlers;

use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Contracts\PaymentMethodHandler;
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

final class StripeCardHandler implements PaymentMethodHandler
{
    public function __construct(
        private readonly StripeGateway $gateway,
        private readonly StripeResultMapper $mapper,
    ) {
        //
    }

    public function getMethod(): string
    {
        return 'card';
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        $this->validateCreatePaymentRequest($request);

        $response = $this->gateway->createPaymentIntent(
            payload: $this->buildCreatePaymentIntentPayload($request),
            idempotencyKey: $this->resolveIdempotencyKey($request),
        );

        return $this->mapper->mapPaymentIntentToResult($request, $response);
    }

    public function confirmPayment(PaymentConfirmationData $request): PaymentResultData
    {
        $paymentIntentId = $this->resolvePaymentIntentId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $stripe = $request->metadata['stripe'] ?? [];

        $paymentMethodId = $stripe['payment_method_id']
            ?? $request->metadata['stripe_payment_method_id']
            ?? null;

        $payload = array_filter([
            'payment_method' => $paymentMethodId,
            'return_url' => $stripe['return_url'] ?? $request->metadata['return_url'] ?? null,
        ], fn($value) => $value !== null && $value !== '');

        /*
         * Si mandas payment_method_id desde backend, aquí sí confirma contra Stripe.
         * Si NO lo mandas, solo consulta el PaymentIntent.
         *
         * Esto permite que el frontend confirme con Stripe.js usando client_secret,
         * y después tu backend solo haga lookup para sincronizar estado.
         */
        if ($paymentMethodId) {
            $response = $this->gateway->confirmPaymentIntent(
                paymentIntentId: $paymentIntentId,
                payload: $payload,
                idempotencyKey: $stripe['confirm_idempotency_key']
                    ?? $request->metadata['confirm_idempotency_key']
                    ?? null,
            );
        } else {
            $response = $this->gateway->getPaymentIntent($paymentIntentId);
        }

        return $this->mapper->mapPaymentIntentResponseToResult(
            method: $this->getMethod(),
            response: $response,
        );
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        return match ($request->lookupType()) {
            'provider_payment_id' => $this->lookupByPaymentIntentId($request),

            'provider_order_id' => throw UnsupportedOperationException::forOperation(
                'lookup',
                'Stripe card lookup requires providerPaymentId. Use PaymentIntent id, for example pi_...'
            ),

            default => throw UnsupportedOperationException::forOperation(
                'lookup',
                'Stripe card lookup requires providerPaymentId.'
            ),
        };
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        $paymentIntentId = $this->resolvePaymentIntentId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $response = $this->gateway->cancelPaymentIntent($paymentIntentId);

        return $this->mapper->mapPaymentIntentResponseToResult(
            method: $this->getMethod(),
            response: $response,
        );
    }

    public function refundPayment(RefundRequestData $request): PaymentResultData
    {
        $paymentIntentId = $this->resolvePaymentIntentId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $payload = array_filter([
            'payment_intent' => $paymentIntentId,
            'amount' => $request->amount,
            'reason' => $this->normalizeRefundReason($request->reason),
        ], fn($value) => $value !== null && $value !== '');

        $response = $this->gateway->createRefund(
            payload: $payload,
            idempotencyKey: data_get($request->metadata, 'stripe.refund_idempotency_key')
                ?? data_get($request->metadata, 'idempotency_key'),
        );

        return $this->mapper->mapRefundResponseToResult($request, $response);
    }

    private function validateCreatePaymentRequest(PaymentRequestData $request): void
    {
        if ($request->amount <= 0) {
            throw InvalidPaymentPayloadException::invalidAmount($request->amount);
        }

        if ($request->currency === '') {
            throw InvalidPaymentPayloadException::invalidCurrency($request->currency);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreatePaymentIntentPayload(PaymentRequestData $request): array
    {
        $stripe = $request->metadata['stripe'] ?? [];

        $payload = [
            'amount' => $request->amount,
            'currency' => strtolower($request->currency),
            'payment_method_types' => ['card'],
            'description' => $request->description ?? $request->externalReference ?? 'Payment',
            'receipt_email' => $request->payerEmail,
            'metadata' => array_filter([
                'external_reference' => $request->externalReference,
                'payer_reference' => $request->payerReference,
                'source' => $request->metadata['source'] ?? null,
            ], fn($value) => $value !== null && $value !== ''),
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

        if (isset($stripe['metadata']) && is_array($stripe['metadata'])) {
            $payload['metadata'] = array_replace_recursive(
                $payload['metadata'],
                $stripe['metadata'],
            );
        }

        if (! empty($stripe['confirm'])) {
            $payload['confirm'] = true;
        }

        return array_filter(
            $payload,
            fn($value) => $value !== null && $value !== [] && $value !== '',
        );
    }

    private function lookupByPaymentIntentId(PaymentLookupData $request): PaymentResultData
    {
        $response = $this->gateway->getPaymentIntent((string) $request->providerPaymentId);

        return $this->mapper->mapPaymentIntentResponseToResult(
            method: $this->getMethod(),
            response: $response,
        );
    }

    private function resolvePaymentIntentId(?string $providerPaymentId, array $metadata = []): string
    {
        $resolved = $providerPaymentId
            ?? data_get($metadata, 'stripe_payment_intent_id')
            ?? data_get($metadata, 'provider_payment_id');

        if (! $resolved) {
            throw InvalidPaymentPayloadException::missingField('provider_payment_id');
        }

        return (string) $resolved;
    }

    private function resolveIdempotencyKey(PaymentRequestData $request): string
    {
        $stripe = $request->metadata['stripe'] ?? [];

        $idempotencyKey = $stripe['idempotency_key']
            ?? $request->metadata['idempotency_key']
            ?? null;

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            return substr((string) $idempotencyKey, 0, 255);
        }

        return 'stag-herd-stripe-' . (string) Str::uuid();
    }

    private function normalizeRefundReason(?string $reason): ?string
    {
        return match ($reason) {
            'duplicate', 'fraudulent', 'requested_by_customer' => $reason,
            default => null,
        };
    }
}
