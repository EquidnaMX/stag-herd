<?php

namespace Equidna\StagHerd\Infrastructure\Providers\PayPal\Handlers;

use Equidna\StagHerd\Contracts\ExtractsPaymentMethodFromPayment;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\PaymentMethodHandler;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\PayPalResultMapper;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Support\Str;

final class PayPalCheckoutHandler implements PaymentMethodHandler, ExtractsPaymentMethodFromPayment
{
    public function __construct(
        private readonly PayPalGateway $gateway,
        private readonly PayPalResultMapper $mapper,
    ) {
        //
    }

    public function getMethod(): string
    {
        return 'paypal';
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        if ($request->providerOrderId) {
            return $this->captureExistingOrder($request);
        }

        $this->validateCreateOrderRequest($request);

        $response = $this->gateway->createOrder(
            payload: $this->buildCreateOrderPayload($request),
            idempotencyKey: $this->resolveIdempotencyKey(
                prefix: 'stag-herd-paypal-order-',
                reference: $request->externalReference,
                metadata: $request->metadata,
            ),
            context: $request->paypalContext(),
        );

        return $this->mapper->mapCreatedOrderToResult(
            request: $request,
            response: $response,
        );
    }

    public function confirmPayment(PaymentConfirmationData $request): PaymentResultData
    {
        $orderId = $this->resolveOrderId(
            providerOrderId: null,
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $response = $this->gateway->captureOrder(
            orderId: $orderId,
            idempotencyKey: $request->metadata['idempotency_key']
                ?? 'stag-herd-paypal-capture-' . $orderId,
        );

        return $this->mapper->mapCapturedOrderToResult(
            method: $this->getMethod(),
            response: $response,
            fallbackOrderId: $orderId,
        );
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        return match ($request->lookupType()) {
            'provider_order_id' => $this->lookupByOrderId($request),
            'provider_payment_id' => $this->lookupByCaptureId($request),

            default => throw UnsupportedOperationException::forOperation(
                'lookup',
                'PayPal lookup requires providerOrderId or providerPaymentId.'
            ),
        };
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        throw UnsupportedOperationException::forOperation(
            'cancel',
            'PayPal order cancellation is not implemented in this MVP.'
        );
    }

    public function refundPayment(RefundRequestData $request): PaymentResultData
    {
        $captureId = $this->resolveCaptureId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $currency = (string) (
            $request->metadata['currency']
            ?? $request->metadata['paypal_currency']
            ?? 'MXN'
        );

        $response = $this->gateway->refundCapture(
            captureId: $captureId,
            amount: $request->amount,
            currency: $currency,
            idempotencyKey: $request->metadata['idempotency_key'] ?? null,
        );

        return $this->mapper->mapRefundResponseToResult(
            request: $request,
            captureId: $captureId,
            response: $response,
        );
    }

    public function paymentMethodFromPayment(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): ?PaymentMethodRegisterData {
        $paypal = is_array(data_get($request->metadata, 'paypal'))
            ? data_get($request->metadata, 'paypal')
            : [];

        $paymentToken = $this->arrayFromFirst([
            data_get($paypal, 'payment_token'),
            data_get($request->metadata, 'payment_token'),
            data_get($result->rawPayload, 'payment_source.card.attributes.vault'),
            data_get($result->rawPayload, 'payment_source.token'),
        ]);

        $ownerReference = $this->firstString([
            $request->payerReference,
            data_get($request->metadata, 'payer_reference'),
        ]);
        $paymentTokenId = $this->firstString([
            data_get($paypal, 'payment_token_id'),
            data_get($paypal, 'token_id'),
            data_get($paymentToken, 'id'),
        ]);
        $customerId = $this->firstString([
            data_get($paymentToken, 'customer.id'),
            data_get($paymentToken, 'customer_id'),
            data_get($paymentToken, 'customer'),
        ]);

        if ($ownerReference === null || $paymentTokenId === null || $customerId === null) {
            return null;
        }

        $card = is_array(data_get($paymentToken, 'payment_source.card'))
            ? data_get($paymentToken, 'payment_source.card')
            : [];

        return new PaymentMethodRegisterData(
            provider: 'paypal',
            ownerReference: $ownerReference,
            providerCustomerId: $customerId,
            providerPaymentMethodId: $paymentTokenId,
            credentialContext: $request->credentialContext,
            type: 'tokenized_card',
            brand: $this->firstString([
                data_get($card, 'brand'),
                data_get($card, 'type'),
                data_get($card, 'network'),
            ]),
            last4: $this->firstString([
                data_get($card, 'last_digits'),
                data_get($card, 'last4'),
            ]),
            payload: [
                'paypal' => [
                    'token_id' => $paymentTokenId,
                    'token_type' => data_get($paymentToken, 'type', 'PAYMENT_TOKEN'),
                    'customer_id' => $customerId,
                    'payment_token' => $paymentToken,
                ],
                'payment_result' => $result->toArray(),
            ],
        );
    }

    private function captureExistingOrder(PaymentRequestData $request): PaymentResultData
    {
        $response = $this->gateway->captureOrder(
            orderId: $request->providerOrderId,
            idempotencyKey: $request->metadata['idempotency_key']
                ?? 'stag-herd-paypal-capture-' . $request->providerOrderId,
            context: $request->paypalContext(),
        );

        return $this->mapper->mapCapturedOrderToResult(
            method: $request->method,
            response: $response,
            fallbackOrderId: $request->providerOrderId,
        );
    }

    private function lookupByOrderId(PaymentLookupData $request): PaymentResultData
    {
        $response = $this->gateway->getOrder((string) $request->providerOrderId);

        return $this->mapper->mapOrderResponseToResult(
            method: $request->method ?? $this->getMethod(),
            response: $response,
            fallbackOrderId: $request->providerOrderId,
        );
    }

    private function lookupByCaptureId(PaymentLookupData $request): PaymentResultData
    {
        $response = $this->gateway->getCapture((string) $request->providerPaymentId);

        return $this->mapper->mapCaptureResponseToResult(
            method: $request->method ?? $this->getMethod(),
            response: $response,
            fallbackCaptureId: $request->providerPaymentId,
            fallbackOrderId: $request->providerOrderId,
        );
    }

    private function validateCreateOrderRequest(PaymentRequestData $request): void
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
    private function buildCreateOrderPayload(PaymentRequestData $request): array
    {
        $paypal = $request->metadata['paypal'] ?? [];

        if (isset($paypal['payload']) && is_array($paypal['payload'])) {
            $payload = $paypal['payload'];

            if (isset($payload['purchase_units']) && is_array($payload['purchase_units'])) {
                $payload['purchase_units'] = $this->applyPayeeMerchantId(
                    purchaseUnits: $payload['purchase_units'],
                    sellerMerchantId: $request->sellerMerchantId,
                );

                $payload['purchase_units'] = $this->applyPlatformFeeAmount(
                    purchaseUnits: $payload['purchase_units'],
                    request: $request,
                );
            }

            return $payload;
        }

        $purchaseUnits = $paypal['purchase_units'] ?? [
            array_filter([
                'reference_id' => $request->externalReference,
                'description' => $request->description ?? $request->externalReference ?? 'Payment',
                'custom_id' => $request->payerReference,
                'invoice_id' => $paypal['invoice_id'] ?? null,

                'amount' => [
                    'currency_code' => strtoupper($request->currency),
                    'value' => MoneyFormatter::toDecimal($request->amount),
                ],
            ], fn($value) => $value !== null && $value !== ''),
        ];

        $purchaseUnits = $this->applyPayeeMerchantId(
            purchaseUnits: $purchaseUnits,
            sellerMerchantId: $request->sellerMerchantId,
        );

        $purchaseUnits = $this->applyPlatformFeeAmount(
            purchaseUnits: $purchaseUnits,
            request: $request,
        );

        $applicationContext = array_filter([
            'return_url' => $paypal['return_url'] ?? $request->returnUrl,
            'cancel_url' => $paypal['cancel_url'] ?? $request->cancelUrl,
            'brand_name' => $paypal['brand_name'] ?? config('app.name'),
            'landing_page' => $paypal['landing_page'] ?? 'LOGIN',
            'user_action' => $paypal['user_action'] ?? 'PAY_NOW',
            'shipping_preference' => $paypal['shipping_preference'] ?? 'NO_SHIPPING',
        ], fn($value) => $value !== null && $value !== '');

        $payload = [
            'intent' => strtoupper((string) ($paypal['intent'] ?? 'CAPTURE')),
            'purchase_units' => $purchaseUnits,
        ];

        if ($applicationContext !== []) {
            $payload['application_context'] = $applicationContext;
        }

        if (isset($paypal['payment_source']) && is_array($paypal['payment_source'])) {
            $payload['payment_source'] = $paypal['payment_source'];
        }

        return $payload;
    }

    private function resolveOrderId(
        ?string $providerOrderId,
        ?string $providerPaymentId = null,
        array $metadata = [],
    ): string {
        $resolved = $providerOrderId
            ?? $metadata['provider_order_id']
            ?? $metadata['paypal_order_id']
            ?? $metadata['order_id']
            ?? null;

        /*
         * En algunos flujos el action de confirm solo trae providerPaymentId.
         * Si el Payment local todavía no tiene capture id, ese valor puede ser el order id.
         */
        if (!$resolved && $providerPaymentId) {
            $resolved = $providerPaymentId;
        }

        if (!$resolved) {
            throw InvalidPaymentPayloadException::missingField(
                'provider_order_id / paypal_order_id'
            );
        }

        return (string) $resolved;
    }

    private function resolveCaptureId(
        ?string $providerPaymentId,
        array $metadata = [],
    ): string {
        $resolved = $providerPaymentId
            ?? $metadata['provider_payment_id']
            ?? $metadata['paypal_capture_id']
            ?? null;

        if (!$resolved) {
            throw InvalidPaymentPayloadException::missingField(
                'provider_payment_id / paypal_capture_id'
            );
        }

        return (string) $resolved;
    }

    private function resolveIdempotencyKey(
        string $prefix,
        ?string $reference,
        array $metadata = [],
    ): string {
        $paypal = $metadata['paypal'] ?? [];

        if (isset($paypal['idempotency_key'])) {
            return (string) $paypal['idempotency_key'];
        }

        if (isset($metadata['idempotency_key'])) {
            return (string) $metadata['idempotency_key'];
        }

        if ($reference) {
            return $prefix . $reference;
        }

        return $prefix . (string) Str::uuid();
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

    /**
     * @param array<int, mixed> $values
     * @return array<string, mixed>
     */
    private function arrayFromFirst(array $values): array
    {
        foreach ($values as $value) {
            if (is_array($value) && $value !== []) {
                return $value;
            }
        }

        return [];
    }

    /**
     * @param array<int, mixed> $purchaseUnits
     * @return array<int, mixed>
     */
    private function applyPayeeMerchantId(array $purchaseUnits, ?string $sellerMerchantId): array
    {
        if ($sellerMerchantId === null || trim($sellerMerchantId) === '') {
            return $purchaseUnits;
        }

        return array_map(
            function (mixed $purchaseUnit) use ($sellerMerchantId): mixed {
                if (!is_array($purchaseUnit)) {
                    return $purchaseUnit;
                }

                $purchaseUnit['payee'] = array_replace(
                    is_array($purchaseUnit['payee'] ?? null) ? $purchaseUnit['payee'] : [],
                    [
                        'merchant_id' => trim($sellerMerchantId),
                    ],
                );

                return $purchaseUnit;
            },
            $purchaseUnits,
        );
    }

    /**
     * @param array<int, mixed> $purchaseUnits
     * @return array<int, mixed>
     */
    private function applyPlatformFeeAmount(array $purchaseUnits, PaymentRequestData $request): array
    {
        if ($request->platformFeeAmount === null || $request->platformFeeAmount <= 0) {
            return $purchaseUnits;
        }

        if (!isset($purchaseUnits[0]) || !is_array($purchaseUnits[0])) {
            return $purchaseUnits;
        }

        $purchaseUnits[0]['payment_instruction'] = array_replace(
            is_array($purchaseUnits[0]['payment_instruction'] ?? null)
                ? $purchaseUnits[0]['payment_instruction']
                : [],
            [
                'platform_fees' => [
                    [
                        'amount' => [
                            'currency_code' => strtoupper($request->currency),
                            'value' => MoneyFormatter::toDecimalString($request->platformFeeAmount),
                        ],
                    ],
                ],
            ],
        );

        return $purchaseUnits;
    }
}
