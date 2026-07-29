<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe\Services;

use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeResultMapper;
use Illuminate\Support\Str;

final class StripeSpeiPaymentService
{
    public function __construct(
        private readonly StripeGateway $gateway,
        private readonly StripeResultMapper $mapper,
        private readonly StripeCustomerService $customers,
    ) {
        //
    }

    public function createPayment(
        PaymentRequestData $request,
        string $method = 'spei',
    ): PaymentResultData {
        $this->validateBaseRequest($request);

        $stripe = $request->metadata['stripe'] ?? [];

        $customerId = $this->customers->ensureExists(
            customerId: $stripe['customer']
                ?? $stripe['customer_id']
                ?? null,
            customerPayload: $this->customers->buildPayload(
                payerReference: $request->payerReference,
                payerEmail: $request->payerEmail,
                payerName: null,
                source: 'stag-herd-stripe-spei',
            ),
        );

        if (! is_string($customerId) || trim($customerId) === '') {
            throw InvalidPaymentPayloadException::missingField(
                'metadata.stripe.customer'
            );
        }

        if (! str_starts_with($customerId, 'cus_')) {
            throw InvalidPaymentPayloadException::invalidField(
                'metadata.stripe.customer',
                'Stripe customer must contain a valid cus_... identifier.'
            );
        }

        $payload = $this->buildCreatePaymentIntentPayload(
            request: $request,
            customerId: $customerId,
        );

        $response = $this->gateway->createPaymentIntent(
            payload: $payload,
            idempotencyKey: $this->resolveIdempotencyKey($request),
        );

        return $this->mapper->mapPaymentIntentToResult(
            $request,
            $response,
        );
    }

    private function buildCreatePaymentIntentPayload(
        PaymentRequestData $request,
        string $customerId,
    ): array {
        $stripe = $request->metadata['stripe'] ?? [];

        $payload = [
            'amount' => $request->amount,
            'currency' => strtolower($request->currency),
            'customer' => $customerId,
            'payment_method_types' => ['customer_balance'],
            'payment_method_data' => [
                'type' => 'customer_balance',
            ],
            'payment_method_options' => [
                'customer_balance' => [
                    'funding_type' => 'bank_transfer',
                    'bank_transfer' => [
                        'type' => 'mx_bank_transfer',
                    ],
                ],
            ],
            'confirm' => true,
            'description' => $request->description
                ?? $request->externalReference
                ?? 'Payment with Stripe SPEI',
            'receipt_email' => $request->payerEmail,
            'return_url' => $stripe['return_url']
                ?? $request->returnUrl
                ?? null,
            'metadata' => array_filter(
                [
                    'external_reference' => $request->externalReference,
                    'payer_reference' => $request->payerReference,
                    'source' => $request->metadata['source']
                        ?? 'stag-herd-stripe-spei',
                    'payment_method_family' => 'spei',
                    'bank_transfer_type' => 'mx_bank_transfer',
                ],
                fn($value) => $value !== null && $value !== ''
            ),
        ];

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
                && $value !== ''
                && $value !== [],
        );
    }

    private function resolveIdempotencyKey(
        PaymentRequestData $request,
    ): string {
        $stripe = $request->metadata['stripe'] ?? [];

        $idempotencyKey = $stripe['idempotency_key']
            ?? $request->metadata['idempotency_key']
            ?? null;

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            return substr((string) $idempotencyKey, 0, 255);
        }

        return 'stag-herd-stripe-spei-' . (string) Str::uuid();
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

        if (strtoupper($request->currency) !== 'MXN') {
            throw InvalidPaymentPayloadException::invalidField(
                'currency',
                'Stripe SPEI payments currently support MXN only.'
            );
        }
    }
}
