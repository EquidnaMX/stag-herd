<?php

namespace Equidna\StagHerd\Infrastructure\Providers\MercadoPago\Handlers;

use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Contracts\PaymentMethodHandler;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoResultMapper;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class MercadoPagoCardHandler implements PaymentMethodHandler
{
    public function __construct(
        private readonly MercadoPagoGateway $gateway,
        private readonly MercadoPagoResultMapper $mapper,
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

        if ($this->usesOrdersApi($request)) {
            $response = $this->gateway->createOrder(
                payload: $this->buildCreateOrderPayload($request),
                idempotencyKey: $this->resolveIdempotencyKey($request),
                deviceId: $this->resolveDeviceId($request),
            );

            return $this->mapper->mapOrderResponseToResult($request, $response);
        }

        $response = $this->gateway->createPayment(
            payload: $this->buildCreatePaymentPayload($request),
            idempotencyKey: $this->resolveIdempotencyKey($request),
            deviceId: $this->resolveDeviceId($request),
        );

        return $this->mapper->mapPaymentResponseToResult($request, $response);
    }

    public function confirmPayment(PaymentConfirmationData $request): PaymentResultData
    {
        $providerOrderId = $request->providerOrderId
            ?? data_get($request->metadata, 'provider_order_id')
            ?? data_get($request->metadata, 'mercado_pago_order_id');

        if ($providerOrderId) {
            $response = $this->gateway->getOrder((string) $providerOrderId);

            return $this->mapper->mapOrderResponseToResultFromOperation(
                method: $this->getMethod(),
                response: $response,
            );
        }

        $providerPaymentId = $this->resolveProviderPaymentId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $response = $this->gateway->getPayment($providerPaymentId);

        return $this->mapper->mapPaymentResponseToResultFromOperation(
            method: $this->getMethod(),
            response: $response,
        );
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        return match ($request->lookupType()) {
            'provider_payment_id' => $this->lookupByProviderPaymentId($request),
            'provider_order_id' => $this->lookupByProviderOrderId($request),

            default => throw UnsupportedOperationException::forOperation(
                'lookup',
                'Mercado Pago card lookup requires providerPaymentId or providerOrderId.'
            ),
        };
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        $providerPaymentId = $this->resolveProviderPaymentId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $response = $this->gateway->cancelPayment($providerPaymentId);

        return $this->mapper->mapPaymentResponseToResultFromOperation(
            method: $this->getMethod(),
            response: $response,
        );
    }

    public function refundPayment(RefundRequestData $request): PaymentResultData
    {
        $providerPaymentId = $this->resolveProviderPaymentId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $response = $this->gateway->refundPayment(
            providerPaymentId: $providerPaymentId,
            amount: $request->amount,
            idempotencyKey: data_get($request->metadata, 'idempotency_key'),
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

        $mercadoPago = $request->metadata['mercado_pago'] ?? [];

        if (empty($mercadoPago['token'])) {
            throw InvalidPaymentPayloadException::missingField('metadata.mercado_pago.token');
        }

        if (empty($mercadoPago['payment_method_id'])) {
            throw InvalidPaymentPayloadException::missingField('metadata.mercado_pago.payment_method_id');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreateOrderPayload(PaymentRequestData $request): array
    {
        $mercadoPago = $request->metadata['mercado_pago'] ?? [];
        $paymentMethod = (array) ($mercadoPago['payment_method'] ?? []);

        $payload = [
            'type' => 'online',
            'external_reference' => substr((string) ($request->externalReference ?: 'ORDER-' . Str::uuid()), 0, 64),
            'processing_mode' => $mercadoPago['processing_mode']
                ?? config('stag-herd.providers.mercado_pago.orders.processing_mode', 'automatic'),
            'capture_mode' => $mercadoPago['capture_mode']
                ?? config('stag-herd.providers.mercado_pago.orders.capture_mode', 'automatic_async'),
            'total_amount' => MoneyFormatter::toDecimal($request->amount),
            'description' => $request->description ?? $request->externalReference ?? 'Payment',
            'payer' => array_filter([
                'email' => $request->payerEmail,
                'identification' => data_get($mercadoPago, 'payer.identification'),
            ], fn($value) => $value !== null && $value !== '' && $value !== []),
            'transactions' => [
                'payments' => [
                    [
                        'amount' => MoneyFormatter::toDecimal($request->amount),
                        'payment_method' => array_filter([
                            'id' => (string) ($paymentMethod['id'] ?? $mercadoPago['payment_method_id']),
                            'type' => (string) ($paymentMethod['type']
                                ?? $mercadoPago['payment_type_id']
                                ?? $mercadoPago['payment_method_type']
                                ?? 'credit_card'),
                            'token' => (string) $mercadoPago['token'],
                            'installments' => (int) ($paymentMethod['installments']
                                ?? $mercadoPago['installments']
                                ?? 1),
                        ], fn($value) => $value !== null && $value !== ''),
                    ],
                ],
            ],
        ];

        $config = $mercadoPago['config']
            ?? config('stag-herd.providers.mercado_pago.orders.config', [
                'online' => [
                    'transaction_security' => [
                        'validation' => 'on_fraud_risk',
                        'liability_shift' => 'required',
                    ],
                ],
            ]);

        if (is_array($config) && $config !== []) {
            $payload['config'] = $config;
        }

        foreach (['notification_url', 'statement_descriptor', 'marketplace'] as $field) {
            if (array_key_exists($field, $mercadoPago)) {
                $payload[$field] = $mercadoPago[$field];
            }
        }

        return array_filter(
            $payload,
            fn($value) => $value !== null && $value !== [] && $value !== '',
        );
    }

    /**
     * Legacy/direct Payments API payload.
     *
     * @return array<string, mixed>
     */
    private function buildCreatePaymentPayload(PaymentRequestData $request): array
    {
        $mercadoPago = $request->metadata['mercado_pago'] ?? [];

        $payload = [
            'transaction_amount' => MoneyFormatter::toDecimal($request->amount),
            'description' => $request->description ?? $request->externalReference ?? 'Payment',
            'external_reference' => $request->externalReference,
            'payment_method_id' => (string) $mercadoPago['payment_method_id'],
            'payer' => array_filter([
                'email' => $request->payerEmail,
            ]),
            'metadata' => array_filter([
                'payer_reference' => $request->payerReference,
                'source' => $request->metadata['source'] ?? null,
            ]),
        ];

        $optionalFields = [
            'token',
            'installments',
            'issuer_id',
            'capture',
            'statement_descriptor',
            'notification_url',
            'additional_info',
            'binary_mode',
            'application_fee',
            'campaign_id',
            'coupon_amount',
            'differential_pricing_id',
        ];

        foreach ($optionalFields as $field) {
            if (array_key_exists($field, $mercadoPago)) {
                $payload[$field] = $mercadoPago[$field];
            }
        }

        if (isset($mercadoPago['payer']) && is_array($mercadoPago['payer'])) {
            $payload['payer'] = array_replace_recursive(
                $payload['payer'],
                $mercadoPago['payer'],
            );
        }

        return array_filter(
            $payload,
            fn($value) => $value !== null && $value !== [],
        );
    }

    private function lookupByProviderPaymentId(PaymentLookupData $request): PaymentResultData
    {
        $response = $this->gateway->getPayment((string) $request->providerPaymentId);

        return $this->mapper->mapPaymentResponseToResultFromOperation(
            method: $this->getMethod(),
            response: $response,
        );
    }

    private function lookupByProviderOrderId(PaymentLookupData $request): PaymentResultData
    {
        $response = $this->gateway->getOrder((string) $request->providerOrderId);

        return $this->mapper->mapOrderResponseToResultFromOperation(
            method: $this->getMethod(),
            response: $response,
        );
    }

    private function resolveProviderPaymentId(
        ?string $providerPaymentId,
        array $metadata = [],
    ): string {
        $resolved = $providerPaymentId
            ?? data_get($metadata, 'provider_payment_id')
            ?? data_get($metadata, 'mercado_pago_payment_id');

        if (! $resolved) {
            throw InvalidPaymentPayloadException::missingField('provider_payment_id');
        }

        return (string) $resolved;
    }

    private function resolveIdempotencyKey(PaymentRequestData $request): string
    {
        $mercadoPago = $request->metadata['mercado_pago'] ?? [];

        if (isset($mercadoPago['idempotency_key'])) {
            return substr((string) $mercadoPago['idempotency_key'], 0, 64);
        }

        if (isset($request->metadata['idempotency_key'])) {
            return substr((string) $request->metadata['idempotency_key'], 0, 64);
        }

        if ($request->externalReference) {
            return substr('stag-herd-' . $request->externalReference, 0, 64);
        }

        return 'stag-herd-' . (string) Str::uuid();
    }

    private function resolveDeviceId(PaymentRequestData $request): ?string
    {
        return data_get($request->metadata, 'mercado_pago.device_id')
            ?? data_get($request->metadata, 'device_id')
            ?? data_get($request->metadata, 'mp_device_session_id');
    }

    private function usesOrdersApi(PaymentRequestData $request): bool
    {
        $mercadoPago = $request->metadata['mercado_pago'] ?? [];

        $flow = strtolower((string) (
            $mercadoPago['flow']
            ?? config('stag-herd.providers.mercado_pago.checkout_flow', 'orders')
        ));

        return $flow !== 'payments';
    }
}
