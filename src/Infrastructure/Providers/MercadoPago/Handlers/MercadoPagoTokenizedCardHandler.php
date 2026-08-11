<?php

namespace Equidna\StagHerd\Infrastructure\Providers\MercadoPago\Handlers;

use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Contracts\ManagesPaymentMethods;
use Equidna\StagHerd\Contracts\PaymentMethodHandler;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentMethodLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Exceptions\PaymentNotFoundException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoResultMapper;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Support\Str;

final class MercadoPagoTokenizedCardHandler implements PaymentMethodHandler
{
    public function __construct(
        private readonly MercadoPagoGateway $gateway,
        private readonly MercadoPagoResultMapper $mapper,
        private readonly ManagesPaymentMethods $paymentMethods,
    ) {
        //
    }

    public function getMethod(): string
    {
        return 'tokenized_card';
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        $resolved = $this->resolveChargeData($request);

        $response = $this->gateway->createPayment(
            payload: $this->buildCreatePaymentPayload($request, $resolved),
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

    /**
     * @return array{token:string,customer_id:string,payment_method_id:string,card_id:?string,issuer_id:mixed,installments:int,payer:array}
     */
    private function resolveChargeData(PaymentRequestData $request): array
    {
        if ($request->amount <= 0) {
            throw InvalidPaymentPayloadException::invalidAmount($request->amount);
        }

        if ($request->currency === '') {
            throw InvalidPaymentPayloadException::invalidCurrency($request->currency);
        }

        $mercadoPago = is_array($request->metadata['mercado_pago'] ?? null)
            ? $request->metadata['mercado_pago']
            : [];

        $token = $this->nullableString($mercadoPago['token'] ?? null);

        if ($token === null) {
            throw InvalidPaymentPayloadException::missingField('metadata.mercado_pago.token');
        }

        $customerId = $this->nullableString($mercadoPago['customer_id'] ?? null);
        $paymentMethodId = $this->nullableString($mercadoPago['payment_method_id'] ?? null);
        $cardId = $this->nullableString($mercadoPago['card_id'] ?? null);
        $payer = is_array($mercadoPago['payer'] ?? null) ? $mercadoPago['payer'] : [];
        $issuerId = $mercadoPago['issuer_id'] ?? null;
        $installments = (int) ($mercadoPago['installments'] ?? 1);

        if ($customerId !== null && $paymentMethodId !== null) {
            return [
                'token' => $token,
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
                'card_id' => $cardId,
                'issuer_id' => $issuerId,
                'installments' => $installments > 0 ? $installments : 1,
                'payer' => $payer,
            ];
        }

        $ownerReference = trim((string) ($request->payerReference ?? ''));

        if ($ownerReference === '') {
            throw InvalidPaymentPayloadException::missingField('payer_reference');
        }

        $paymentMethod = $this->paymentMethods->resolveUsablePaymentMethod(
            new PaymentMethodLookupData(
                provider: 'mercado_pago',
                ownerReference: $ownerReference,
                credentialContext: $request->credentialContext,
            )
        );

        $savedPayload = $paymentMethod->payload;
        $savedMercadoPago = is_array($savedPayload['mercado_pago'] ?? null)
            ? $savedPayload['mercado_pago']
            : [];

        $resolvedCustomerId = $this->nullableString(
            $savedMercadoPago['customer_id']
                ?? $paymentMethod->providerCustomerId
                ?? null
        );

        $resolvedPaymentMethodId = $this->nullableString(
            $savedMercadoPago['payment_method_id']
                ?? null
        );

        $resolvedCardId = $this->nullableString(
            $savedMercadoPago['card_id']
                ?? $paymentMethod->providerPaymentMethodId
                ?? null
        );

        if ($resolvedCustomerId === null) {
            throw InvalidPaymentPayloadException::missingField(
                'payment_method.provider_customer_id'
            );
        }

        if ($resolvedPaymentMethodId === null) {
            throw InvalidPaymentPayloadException::missingField(
                'payment_method.payload.mercado_pago.payment_method_id'
            );
        }

        return [
            'token' => $token,
            'customer_id' => $resolvedCustomerId,
            'payment_method_id' => $resolvedPaymentMethodId,
            'card_id' => $resolvedCardId,
            'issuer_id' => $issuerId ?? ($savedMercadoPago['issuer_id'] ?? null),
            'installments' => $installments > 0 ? $installments : (int) ($savedMercadoPago['installments'] ?? 1),
            'payer' => array_merge(
                is_array($savedMercadoPago['payer'] ?? null) ? $savedMercadoPago['payer'] : [],
                $payer
            ),
        ];
    }

    /**
     * @param array{token:string,customer_id:string,payment_method_id:string,card_id:?string,issuer_id:mixed,installments:int,payer:array} $resolved
     * @return array<string,mixed>
     */
    private function buildCreatePaymentPayload(PaymentRequestData $request, array $resolved): array
    {
        $mercadoPago = $request->metadata['mercado_pago'] ?? [];

        $payload = [
            'transaction_amount' => MoneyFormatter::toDecimal($request->amount),
            'description' => $request->description ?? $request->externalReference ?? 'Payment',
            'external_reference' => $request->externalReference,
            'token' => $resolved['token'],
            'payment_method_id' => $resolved['payment_method_id'],
            'payer' => array_filter(array_replace_recursive(
                [
                    'type' => 'customer',
                    'id' => $resolved['customer_id'],
                    'email' => $request->payerEmail,
                ],
                $resolved['payer'],
            )),
            'metadata' => array_filter([
                'payer_reference' => $request->payerReference,
                'source' => $request->metadata['source'] ?? null,
                'external_reference' => $request->externalReference,
                'card_id' => $resolved['card_id'],
                'customer_id' => $resolved['customer_id'],
            ]),
        ];

        $optionalFields = [
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

        $payload['installments'] = $resolved['installments'];

        if ($resolved['issuer_id'] !== null && $resolved['issuer_id'] !== '') {
            $payload['issuer_id'] = $resolved['issuer_id'];
        }

        foreach ($optionalFields as $field) {
            if (array_key_exists($field, $mercadoPago) && ! array_key_exists($field, $payload)) {
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

        return substr(
            'stag-herd-mp-tokenized-' . Str::uuid(),
            0,
            64,
        );
    }

    private function resolveDeviceId(PaymentRequestData $request): ?string
    {
        return data_get($request->metadata, 'mercado_pago.device_id')
            ?? data_get($request->metadata, 'device_id');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
