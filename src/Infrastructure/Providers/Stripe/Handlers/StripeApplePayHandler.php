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
use Illuminate\Support\Facades\Log;

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
        string $method,
        array $options = [],
    ): PaymentResultData {
        Log::info('Stripe createPayment started', [
            'method' => $method,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'external_reference' => $request->externalReference,
            'payer_reference' => $request->payerReference,
            'payer_email' => $request->payerEmail,
            'return_url' => $request->returnUrl,
            'metadata_keys' => array_keys($request->metadata ?? []),
            'has_stripe_metadata' => isset($request->metadata['stripe']),
            'stripe_metadata_keys' => array_keys($request->metadata['stripe'] ?? []),
            'has_payment_method' => !empty(($request->metadata['stripe'] ?? [])['payment_method']),
            'has_payment_method_id' => !empty(($request->metadata['stripe'] ?? [])['payment_method_id']),
            'idempotency_key' => ($request->metadata['stripe']['idempotency_key'] ?? $request->metadata['idempotency_key'] ?? null),
            'options' => $options,
        ]);

        try {
            $this->validateBaseRequest($request);

            $payload = $this->buildCreatePaymentIntentPayload(
                request: $request,
                options: $options,
            );

            $idempotencyKey = $this->resolveIdempotencyKey($request);

            Log::info('Stripe createPayment payload built', [
                'method' => $method,
                'external_reference' => $request->externalReference,
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
            ]);

            $response = $this->gateway->createPaymentIntent(
                payload: $payload,
                idempotencyKey: $idempotencyKey,
            );

            Log::info('Stripe createPayment gateway response', [
                'method' => $method,
                'external_reference' => $request->externalReference,
                'payment_intent_id' => $response['id'] ?? null,
                'status' => $response['status'] ?? null,
                'client_secret_present' => !empty($response['client_secret'] ?? null),
                'response' => $response,
            ]);

            $result = $this->mapper->mapPaymentIntentToResult(
                $request,
                $response,
            );

            Log::info('Stripe createPayment mapped result', [
                'method' => $method,
                'external_reference' => $request->externalReference,
                'provider_payment_id' => $result->providerPaymentId ?? null,
                'provider_status' => $result->providerStatus ?? null,
                'status' => $result->status ?? null,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Stripe createPayment failed', [
                'method' => $method,
                'external_reference' => $request->externalReference,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
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
