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
        if (! $request->providerOrderId) {
            throw InvalidPaymentPayloadException::missingField('provider_order_id');
        }

        return $this->captureOrder(
            providerOrderId: $request->providerOrderId,
            method: $request->method,
            metadata: [
                ...$request->metadata,
                'idempotency_key' => $request->metadata['idempotency_key']
                    ?? 'stag-herd-paypal-capture-' . $request->providerOrderId,
            ],
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

        return $this->mapRefundResponseToResult(
            request: $request,
            captureId: $captureId,
            response: $response,
        );
    }

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
            fallbackOrderId: $providerOrderId,
        );
    }

    private function lookupByOrderId(PaymentLookupData $request): PaymentResultData
    {
        $response = $this->gateway->getOrder((string) $request->providerOrderId);

        return $this->mapOrderResponseToResultFromOperation(
            method: 'paypal',
            response: $response,
            fallbackOrderId: $request->providerOrderId,
        );
    }

    private function lookupByCaptureId(PaymentLookupData $request): PaymentResultData
    {
        $response = $this->gateway->getCapture((string) $request->providerPaymentId);

        return $this->mapCaptureResponseToResult(
            method: 'paypal',
            response: $response,
            fallbackCaptureId: $request->providerPaymentId,
            fallbackOrderId: $request->providerOrderId,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function mapOrderResponseToResultFromOperation(
        string $method,
        array $response,
        ?string $fallbackOrderId = null,
    ): PaymentResultData {
        $providerStatus = $this->nullableString(Arr::get($response, 'status'));

        $orderId = $this->resolveOrderIdFromOrder(
            response: $response,
            fallbackOrderId: $fallbackOrderId,
        );

        $captureId = $this->resolveCaptureIdFromOrder($response);

        return new PaymentResultData(
            provider: $this->getName(),
            method: $method,
            status: $this->statusMapper->map($providerStatus),
            providerStatus: $providerStatus,
            references: new ProviderReferencesData(
                providerPaymentId: $captureId,
                providerOrderId: $orderId,
                providerTransactionId: $captureId,
            ),
            amount: $this->resolveAmountFromOrder($response),
            currency: $this->resolveCurrencyFromOrder($response),
            nextAction: $this->resolveNextAction($response),
            metadata: array_filter([
                'paypal_order_id' => $orderId,
                'paypal_capture_id' => $captureId,
                'paypal_status' => $providerStatus,
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
        ?string $fallbackOrderId = null,
    ): PaymentResultData {
        $orderId = $this->resolveOrderIdFromOrder(
            response: $response,
            fallbackOrderId: $fallbackOrderId,
        );

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
                providerOrderId: $orderId,
                providerTransactionId: $captureId,
            ),
            amount: $this->resolveAmountFromOrder($response),
            currency: $this->resolveCurrencyFromOrder($response),
            nextAction: NextActionData::none(),
            metadata: array_filter([
                'paypal_order_id' => $orderId,
                'paypal_capture_id' => $captureId,
                'paypal_status' => $providerStatus,
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
        ?string $fallbackCaptureId = null,
        ?string $fallbackOrderId = null,
    ): PaymentResultData {
        $providerStatus = $this->nullableString(Arr::get($response, 'status'));

        $captureId = $this->resolveCaptureIdFromCapture(
            response: $response,
            fallbackCaptureId: $fallbackCaptureId,
        );

        $orderId = $this->resolveOrderIdFromCapture(
            response: $response,
            fallbackOrderId: $fallbackOrderId,
        );

        return new PaymentResultData(
            provider: $this->getName(),
            method: $method,
            status: $this->statusMapper->map($providerStatus),
            providerStatus: $providerStatus,
            references: new ProviderReferencesData(
                providerPaymentId: $captureId,
                providerOrderId: $orderId,
                providerTransactionId: $captureId,
            ),
            amount: $this->resolveAmountFromCapture($response),
            currency: $this->resolveCurrencyFromCapture($response),
            nextAction: NextActionData::none(),
            metadata: array_filter([
                'paypal_order_id' => $orderId,
                'paypal_capture_id' => $captureId,
                'paypal_status' => $providerStatus,
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
        $orderId = $this->nullableString(
            $request->metadata['provider_order_id']
                ?? $request->metadata['paypal_order_id']
                ?? null
        );

        return new PaymentResultData(
            provider: $this->getName(),
            method: (string) ($request->metadata['method'] ?? 'paypal'),
            status: PaymentStatusEnum::REFUNDED,
            providerStatus: $this->nullableString(Arr::get($response, 'status')),
            references: new ProviderReferencesData(
                providerPaymentId: $captureId,
                providerOrderId: $orderId,
                providerTransactionId: $captureId,
                providerRefundId: $this->nullableString(Arr::get($response, 'id')),
            ),
            amount: null,
            currency: $this->nullableString(Arr::get($response, 'amount.currency_code')),
            reason: $request->reason,
            metadata: array_filter([
                'paypal_order_id' => $orderId,
                'paypal_capture_id' => $captureId,
                'paypal_refund_id' => Arr::get($response, 'id'),
                'paypal_refund_amount' => Arr::get($response, 'amount.value'),
                'paypal_refund_currency' => Arr::get($response, 'amount.currency_code'),
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

    private function resolveOrderIdFromOrder(
        array $response,
        ?string $fallbackOrderId = null,
    ): ?string {
        return $this->nullableString(
            Arr::get($response, 'id')
                ?? Arr::get($response, 'order_id')
                ?? Arr::get($response, 'resource.id')
                ?? $fallbackOrderId
        );
    }

    private function resolveCaptureIdFromOrder(array $response): ?string
    {
        return $this->nullableString(
            Arr::get($response, 'purchase_units.0.payments.captures.0.id')
                ?? Arr::get($response, 'resource.purchase_units.0.payments.captures.0.id')
        );
    }

    private function resolveOrderIdFromCapture(
        array $response,
        ?string $fallbackOrderId = null,
    ): ?string {
        return $this->nullableString(
            Arr::get($response, 'supplementary_data.related_ids.order_id')
                ?? Arr::get($response, 'resource.supplementary_data.related_ids.order_id')
                ?? Arr::get($response, 'order_id')
                ?? $fallbackOrderId
        );
    }

    private function resolveCaptureIdFromCapture(
        array $response,
        ?string $fallbackCaptureId = null,
    ): ?string {
        return $this->nullableString(
            Arr::get($response, 'id')
                ?? Arr::get($response, 'resource.id')
                ?? Arr::get($response, 'capture_id')
                ?? $fallbackCaptureId
        );
    }

    private function resolveAmountFromOrder(array $response): ?int
    {
        $value = Arr::get($response, 'purchase_units.0.payments.captures.0.amount.value')
            ?? Arr::get($response, 'purchase_units.0.amount.value')
            ?? Arr::get($response, 'resource.purchase_units.0.payments.captures.0.amount.value')
            ?? Arr::get($response, 'resource.purchase_units.0.amount.value');

        if ($value === null || $value === '') {
            return null;
        }

        return MoneyFormatter::fromDecimal($value);
    }

    private function resolveCurrencyFromOrder(array $response): ?string
    {
        return $this->nullableString(
            Arr::get($response, 'purchase_units.0.payments.captures.0.amount.currency_code')
                ?? Arr::get($response, 'purchase_units.0.amount.currency_code')
                ?? Arr::get($response, 'resource.purchase_units.0.payments.captures.0.amount.currency_code')
                ?? Arr::get($response, 'resource.purchase_units.0.amount.currency_code')
        );
    }

    private function resolveAmountFromCapture(array $response): ?int
    {
        $value = Arr::get($response, 'amount.value')
            ?? Arr::get($response, 'resource.amount.value');

        if ($value === null || $value === '') {
            return null;
        }

        return MoneyFormatter::fromDecimal($value);
    }

    private function resolveCurrencyFromCapture(array $response): ?string
    {
        return $this->nullableString(
            Arr::get($response, 'amount.currency_code')
                ?? Arr::get($response, 'resource.amount.currency_code')
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
            throw InvalidPaymentPayloadException::missingField(
                'provider_payment_id / paypal_capture_id'
            );
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
