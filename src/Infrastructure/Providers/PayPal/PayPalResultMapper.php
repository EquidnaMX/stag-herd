<?php

namespace Equidna\StagHerd\Infrastructure\Providers\PayPal;

use Equidna\StagHerd\Data\NextActionData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Support\Arr;

final class PayPalResultMapper
{
    public function __construct(
        private readonly PayPalStatusMapper $statusMapper = new PayPalStatusMapper(),
    ) {
        //
    }

    /**
     * @param array<string, mixed> $response
     */
    public function mapCreatedOrderToResult(
        PaymentRequestData $request,
        array $response,
    ): PaymentResultData {
        $providerStatus = $this->nullableString(Arr::get($response, 'status'));
        $orderId = $this->resolveOrderIdFromOrder($response);

        return new PaymentResultData(
            provider: 'paypal',
            method: $request->method,
            status: $this->statusMapper->map($providerStatus),
            providerStatus: $providerStatus,
            references: new ProviderReferencesData(
                providerOrderId: $orderId,
            ),
            amount: $request->amount,
            currency: $request->currency,
            nextAction: $this->resolveNextAction($response),
            metadata: array_filter([
                'external_reference' => $request->externalReference,
                'paypal_order_id' => $orderId,
                'paypal_status' => $providerStatus,
            ]),
            rawPayload: $response,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    public function mapOrderResponseToResult(
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
            provider: 'paypal',
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
    public function mapCapturedOrderToResult(
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
            provider: 'paypal',
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
    public function mapCaptureResponseToResult(
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
            provider: 'paypal',
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
    public function mapRefundResponseToResult(
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
            provider: 'paypal',
            method: (string) ($request->method ?? $request->metadata['method'] ?? 'paypal'),
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
            nextAction: NextActionData::none(),
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

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
