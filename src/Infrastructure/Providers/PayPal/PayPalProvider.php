<?php

namespace Equidna\StagHerd\Infrastructure\Providers\PayPal;

use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\PaymentProvider;
use Equidna\StagHerd\Data\NextActionData;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PayPalProvider implements PaymentProvider
{
    public function __construct(
        private readonly PayPalGateway $gateway,
        private readonly PayPalStatusMapper $statusMapper = new PayPalStatusMapper(),
    ) {
        //
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        $this->validateCreatePaymentRequest($request);

        $payload = $this->buildCreateOrderPayload($request);

        $response = $this->gateway->createOrder(
            payload: $payload,
            idempotencyKey: $this->resolveIdempotencyKey($request),
        );

        return $this->mapOrderResponseToResult(
            request: $request,
            response: $response,
        );
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        return match ($request->lookupType()) {
            /*
             * En PayPal, provider_order_id es la referencia principal antes del capture.
             */
            'provider_order_id' => $this->lookupByOrderId($request),

            /*
             * Después del capture, provider_payment_id representa el capture_id.
             */
            'provider_payment_id' => $this->lookupByCaptureId($request),

            default => throw UnsupportedOperationException::forOperation(
                'lookup',
                'PayPal lookup requires providerOrderId or providerPaymentId.'
            ),
        };
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        /*
         * PayPal Orders v2 no se cancela igual que Mercado Pago.
         * Para demo lo dejamos como no soportado.
         */
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

        return $this->mapRefundResponseToResult(
            request: $request,
            captureId: $captureId,
            response: $response,
        );
    }

    /**
     * Método extra para la demo.
     *
     * PayPal necesita:
     * 1. Crear order.
     * 2. Cliente aprueba en approve_url.
     * 3. Capturar order.
     */
    public function captureOrder(
        string $providerOrderId,
        string $method = 'paypal',
        array $metadata = [],
    ): PaymentResultData {
        $response = $this->gateway->captureOrder(
            orderId: $providerOrderId,
            idempotencyKey: $metadata['idempotency_key'] ?? null,
        );

        return $this->mapCapturedOrderResponseToResult(
            method: $method,
            response: $response,
        );
    }

    private function validateCreatePaymentRequest(PaymentRequestData $request): void
    {
        if ($request->amount <= 0) {
            throw InvalidPaymentPayloadException::invalidAmount($request->amount);
        }

        if ($request->currency === '') {
            throw InvalidPaymentPayloadException::invalidCurrency($request->currency);
        }

        if (! $request->returnUrl) {
            throw InvalidPaymentPayloadException::missingField('return_url');
        }

        if (! $request->cancelUrl) {
            throw InvalidPaymentPayloadException::missingField('cancel_url');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreateOrderPayload(PaymentRequestData $request): array
    {
        $paypal = $request->metadata['paypal'] ?? [];

        $amount = [
            'currency_code' => strtoupper($request->currency),
            'value' => MoneyFormatter::toDecimal($request->amount),
        ];

        $payload = [
            'intent' => strtoupper((string) ($paypal['intent'] ?? 'CAPTURE')),

            'purchase_units' => [
                array_filter([
                    'reference_id' => $request->externalReference,
                    'description' => $request->description ?? $request->externalReference ?? 'Payment',
                    'custom_id' => $request->payerReference,
                    'invoice_id' => $paypal['invoice_id'] ?? null,
                    'amount' => $amount,
                ], fn($value) => $value !== null && $value !== ''),
            ],

            'application_context' => array_filter([
                'return_url' => $request->returnUrl,
                'cancel_url' => $request->cancelUrl,
                'brand_name' => $paypal['brand_name'] ?? config('app.name'),
                'landing_page' => $paypal['landing_page'] ?? 'LOGIN',
                'user_action' => $paypal['user_action'] ?? 'PAY_NOW',
                'shipping_preference' => $paypal['shipping_preference'] ?? 'NO_SHIPPING',
            ], fn($value) => $value !== null && $value !== ''),
        ];

        if (isset($paypal['purchase_units']) && is_array($paypal['purchase_units'])) {
            $payload['purchase_units'] = $paypal['purchase_units'];
        }

        if (isset($paypal['application_context']) && is_array($paypal['application_context'])) {
            $payload['application_context'] = array_replace_recursive(
                $payload['application_context'],
                $paypal['application_context'],
            );
        }

        return $payload;
    }

    private function lookupByOrderId(PaymentLookupData $request): PaymentResultData
    {
        $response = $this->gateway->getOrder((string) $request->providerOrderId);

        return $this->mapOrderResponseToResultFromOperation(
            method: 'paypal',
            response: $response,
        );
    }

    private function lookupByCaptureId(PaymentLookupData $request): PaymentResultData
    {
        $response = $this->gateway->getCapture((string) $request->providerPaymentId);

        return $this->mapCaptureResponseToResult(
            method: 'paypal',
            response: $response,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function mapOrderResponseToResult(
        PaymentRequestData $request,
        array $response,
    ): PaymentResultData {
        $providerStatus = $this->nullableString(Arr::get($response, 'status'));

        return new PaymentResultData(
            provider: $this->getName(),
            method: $request->method,
            status: $this->statusMapper->map($providerStatus),
            providerStatus: $providerStatus,
            references: new ProviderReferencesData(
                providerPaymentId: $this->resolveCaptureIdFromOrder($response),
                providerOrderId: $this->nullableString(Arr::get($response, 'id')),
                providerTransactionId: $this->resolveCaptureIdFromOrder($response),
            ),
            amount: $request->amount,
            currency: $request->currency,
            nextAction: $this->resolveNextAction($response),
            reason: null,
            metadata: array_filter([
                'external_reference' => $request->externalReference,
                'paypal_order_id' => Arr::get($response, 'id'),
                'paypal_status' => Arr::get($response, 'status'),
                'paypal_capture_id' => $this->resolveCaptureIdFromOrder($response),
            ]),
            rawPayload: $response,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function mapOrderResponseToResultFromOperation(
        string $method,
        array $response,
    ): PaymentResultData {
        $providerStatus = $this->nullableString(Arr::get($response, 'status'));

        return new PaymentResultData(
            provider: $this->getName(),
            method: $method,
            status: $this->statusMapper->map($providerStatus),
            providerStatus: $providerStatus,
            references: new ProviderReferencesData(
                providerPaymentId: $this->resolveCaptureIdFromOrder($response),
                providerOrderId: $this->nullableString(Arr::get($response, 'id')),
                providerTransactionId: $this->resolveCaptureIdFromOrder($response),
            ),
            amount: $this->resolveAmountFromOrder($response),
            currency: $this->resolveCurrencyFromOrder($response),
            nextAction: $this->resolveNextAction($response),
            metadata: array_filter([
                'paypal_order_id' => Arr::get($response, 'id'),
                'paypal_status' => Arr::get($response, 'status'),
                'paypal_capture_id' => $this->resolveCaptureIdFromOrder($response),
            ]),
            rawPayload: $response,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function mapCapturedOrderResponseToResult(
        string $method,
        array $response,
    ): PaymentResultData {
        $captureId = $this->resolveCaptureIdFromOrder($response);
        $providerStatus = $this->nullableString(
            Arr::get($response, 'purchase_units.0.payments.captures.0.status')
                ?? Arr::get($response, 'status')
        );

        return new PaymentResultData(
            provider: $this->getName(),
            method: $method,
            status: $this->statusMapper->map($providerStatus),
            providerStatus: $providerStatus,
            references: new ProviderReferencesData(
                providerPaymentId: $captureId,
                providerOrderId: $this->nullableString(Arr::get($response, 'id')),
                providerTransactionId: $captureId,
            ),
            amount: $this->resolveAmountFromOrder($response),
            currency: $this->resolveCurrencyFromOrder($response),
            nextAction: NextActionData::none(),
            metadata: array_filter([
                'paypal_order_id' => Arr::get($response, 'id'),
                'paypal_status' => Arr::get($response, 'status'),
                'paypal_capture_id' => $captureId,
            ]),
            rawPayload: $response,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function mapCaptureResponseToResult(
        string $method,
        array $response,
    ): PaymentResultData {
        $providerStatus = $this->nullableString(Arr::get($response, 'status'));

        return new PaymentResultData(
            provider: $this->getName(),
            method: $method,
            status: $this->statusMapper->map($providerStatus),
            providerStatus: $providerStatus,
            references: new ProviderReferencesData(
                providerPaymentId: $this->nullableString(Arr::get($response, 'id')),
                providerTransactionId: $this->nullableString(Arr::get($response, 'id')),
            ),
            amount: Arr::has($response, 'amount.value')
                ? MoneyFormatter::fromDecimal(Arr::get($response, 'amount.value'))
                : null,
            currency: $this->nullableString(Arr::get($response, 'amount.currency_code')),
            nextAction: NextActionData::none(),
            metadata: array_filter([
                'paypal_capture_id' => Arr::get($response, 'id'),
                'paypal_status' => Arr::get($response, 'status'),
            ]),
            rawPayload: $response,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function mapRefundResponseToResult(
        RefundRequestData $request,
        string $captureId,
        array $response,
    ): PaymentResultData {
        return new PaymentResultData(
            provider: $this->getName(),
            method: (string) ($request->metadata['method'] ?? 'paypal'),
            status: PaymentStatusEnum::REFUNDED,
            providerStatus: $this->nullableString(Arr::get($response, 'status')),
            references: new ProviderReferencesData(
                providerPaymentId: $captureId,
                providerTransactionId: $captureId,
                providerRefundId: $this->nullableString(Arr::get($response, 'id')),
            ),
            amount: $request->amount,
            currency: $this->nullableString(Arr::get($response, 'amount.currency_code')),
            reason: $request->reason,
            metadata: array_filter([
                'paypal_capture_id' => $captureId,
                'paypal_refund_id' => Arr::get($response, 'id'),
                'paypal_refund_status' => Arr::get($response, 'status'),
            ]),
            rawPayload: $response,
        );
    }

    private function resolveNextAction(array $response): NextActionData
    {
        $approveUrl = $this->resolveLink($response, 'approve');

        if (! $approveUrl) {
            return NextActionData::none();
        }

        return NextActionData::redirect($approveUrl, [
            'rel' => 'approve',
        ]);
    }

    private function resolveLink(array $response, string $rel): ?string
    {
        $links = Arr::get($response, 'links', []);

        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (($link['rel'] ?? null) === $rel && ! empty($link['href'])) {
                return (string) $link['href'];
            }
        }

        return null;
    }

    private function resolveCaptureIdFromOrder(array $response): ?string
    {
        return $this->nullableString(
            Arr::get($response, 'purchase_units.0.payments.captures.0.id')
        );
    }

    private function resolveAmountFromOrder(array $response): ?int
    {
        $value = Arr::get($response, 'purchase_units.0.amount.value')
            ?? Arr::get($response, 'purchase_units.0.payments.captures.0.amount.value');

        if ($value === null || $value === '') {
            return null;
        }

        return MoneyFormatter::fromDecimal($value);
    }

    private function resolveCurrencyFromOrder(array $response): ?string
    {
        return $this->nullableString(
            Arr::get($response, 'purchase_units.0.amount.currency_code')
                ?? Arr::get($response, 'purchase_units.0.payments.captures.0.amount.currency_code')
        );
    }

    private function resolveCaptureId(
        ?string $providerPaymentId,
        array $metadata = [],
    ): string {
        $resolved = $providerPaymentId
            ?? $metadata['provider_payment_id']
            ?? $metadata['paypal_capture_id']
            ?? null;

        if (! $resolved) {
            throw InvalidPaymentPayloadException::missingField('provider_payment_id / paypal_capture_id');
        }

        return (string) $resolved;
    }

    private function resolveIdempotencyKey(PaymentRequestData $request): string
    {
        $paypal = $request->metadata['paypal'] ?? [];

        if (isset($paypal['idempotency_key'])) {
            return (string) $paypal['idempotency_key'];
        }

        if ($request->externalReference) {
            return 'stag-herd-paypal-' . $request->externalReference;
        }

        return 'stag-herd-paypal-' . (string) Str::uuid();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
