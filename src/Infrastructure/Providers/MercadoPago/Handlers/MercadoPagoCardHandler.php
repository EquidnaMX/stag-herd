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
use Equidna\StagHerd\Exceptions\PaymentNotFoundException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoResultMapper;
use Equidna\StagHerd\Support\MoneyFormatter;
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

        $response = $this->gateway->createPayment(
            payload: $this->buildCreatePaymentPayload($request),
            idempotencyKey: $this->resolveIdempotencyKey($request),
            deviceId: $this->resolveDeviceId($request),
        );

        return $this->mapper->mapPaymentResponseToResult($request, $response);
    }

    public function confirmPayment(PaymentConfirmationData $request): PaymentResultData
    {
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
            idempotencyKey: data_get($request->metadata, 'mercado_pago.idempotency_key')
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
                'external_reference' => $request->externalReference,
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
        $response = $this->gateway->searchPayments([
            'order.id' => $request->providerOrderId,
        ]);

        $payment = $this->resolveFirstPaymentFromSearchResponse($response);

        if (! $payment) {
            throw PaymentNotFoundException::withProviderReference(
                'mercado_pago',
                (string) $request->providerOrderId,
            );
        }

        return $this->mapper->mapPaymentResponseToResultFromOperation(
            method: $this->getMethod(),
            response: $payment,
        );
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>|null
     */
    private function resolveFirstPaymentFromSearchResponse(array $response): ?array
    {
        $results = $response['results'] ?? [];

        if (! is_array($results) || $results === []) {
            return null;
        }

        usort($results, function (array $a, array $b) {
            return strtotime((string) ($b['date_created'] ?? '')) <=>
                strtotime((string) ($a['date_created'] ?? ''));
        });

        return $results[0] ?? null;
    }

    private function resolveProviderPaymentId(
        ?string $providerPaymentId,
        array $metadata = [],
    ): string {
        $resolved = $providerPaymentId
            ?? data_get($metadata, 'provider_payment_id');

        if (! $resolved) {
            throw InvalidPaymentPayloadException::missingField('provider_payment_id');
        }

        return (string) $resolved;
    }

    private function resolveIdempotencyKey(PaymentRequestData $request): string
    {
        $mercadoPago = $request->metadata['mercado_pago'] ?? [];

        $idempotencyKey = $mercadoPago['idempotency_key']
            ?? $request->metadata['idempotency_key']
            ?? null;

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            return substr((string) $idempotencyKey, 0, 64);
        }

        return 'stag-herd-' . (string) Str::uuid();
    }

    private function resolveDeviceId(PaymentRequestData $request): ?string
    {
        $mercadoPago = $request->metadata['mercado_pago'] ?? [];

        $deviceId = $mercadoPago['device_id']
            ?? $request->metadata['device_id']
            ?? null;

        if ($deviceId === null || $deviceId === '') {
            return null;
        }

        return (string) $deviceId;
    }
}
