<?php

namespace Equidna\StagHerd\Infrastructure\Providers\MercadoPago;

use Equidna\StagHerd\Data\NextActionData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Support\Arr;

final class MercadoPagoResultMapper
{
    public function __construct(
        private readonly MercadoPagoStatusMapper $statusMapper = new MercadoPagoStatusMapper(),
    ) {
        //
    }

    public function mapPaymentResponseToResult(
        PaymentRequestData $request,
        array $response,
    ): PaymentResultData {
        $providerStatus = $this->nullableString(Arr::get($response, 'status'));

        return new PaymentResultData(
            provider: 'mercado_pago',
            method: $request->method,
            status: $this->statusMapper->map($providerStatus),
            providerStatus: $providerStatus,
            references: new ProviderReferencesData(
                providerPaymentId: $this->nullableString(Arr::get($response, 'id')),
                providerOrderId: $this->nullableString(Arr::get($response, 'order.id')),
                providerTransactionId: $this->nullableString(Arr::get($response, 'transaction_details.transaction_id')),
            ),
            amount: $request->amount,
            currency: $request->currency,
            nextAction: $this->resolveNextAction($response),
            reason: $this->nullableString(Arr::get($response, 'status_detail')),
            metadata: array_filter([
                'external_reference' => $request->externalReference,
                'mercado_pago_status_detail' => Arr::get($response, 'status_detail'),
                'mercado_pago_payment_type_id' => Arr::get($response, 'payment_type_id'),
                'mercado_pago_payment_method_id' => Arr::get($response, 'payment_method_id'),
                'mercado_pago_date_approved' => Arr::get($response, 'date_approved'),
            ]),
            rawPayload: $response,
            payerEmail: $this->resolvePayerEmail($response),
        );
    }

    public function mapPaymentResponseToResultFromOperation(
        string $method,
        array $response,
    ): PaymentResultData {
        $providerStatus = $this->nullableString(Arr::get($response, 'status'));

        return new PaymentResultData(
            provider: 'mercado_pago',
            method: $method,
            status: $this->statusMapper->map($providerStatus),
            providerStatus: $providerStatus,
            references: new ProviderReferencesData(
                providerPaymentId: $this->nullableString(Arr::get($response, 'id')),
                providerOrderId: $this->nullableString(Arr::get($response, 'order.id')),
                providerTransactionId: $this->nullableString(Arr::get($response, 'transaction_details.transaction_id')),
            ),
            amount: Arr::has($response, 'transaction_amount')
                ? MoneyFormatter::fromDecimal(Arr::get($response, 'transaction_amount'))
                : null,
            currency: $this->nullableString(Arr::get($response, 'currency_id')),
            nextAction: $this->resolveNextAction($response),
            reason: $this->nullableString(Arr::get($response, 'status_detail')),
            metadata: array_filter([
                'mercado_pago_status_detail' => Arr::get($response, 'status_detail'),
                'mercado_pago_payment_type_id' => Arr::get($response, 'payment_type_id'),
                'mercado_pago_payment_method_id' => Arr::get($response, 'payment_method_id'),
                'mercado_pago_date_approved' => Arr::get($response, 'date_approved'),
            ]),
            rawPayload: $response,
            payerEmail: $this->resolvePayerEmail($response),
        );
    }

    public function mapPreferenceResponseToResult(
        PaymentRequestData $request,
        array $response,
    ): PaymentResultData {
        $preferenceId = $this->nullableString(Arr::get($response, 'id'));
        $initPoint = $this->nullableString(Arr::get($response, 'init_point'))
            ?? $this->nullableString(Arr::get($response, 'sandbox_init_point'));

        return PaymentResultData::pending(
            provider: 'mercado_pago',
            method: $request->method,
            providerStatus: 'preference_created',
            references: new ProviderReferencesData(
                providerOrderId: $preferenceId,
            ),
            amount: $request->amount,
            currency: $request->currency,
            nextAction: $initPoint
                ? NextActionData::redirect($initPoint)
                : NextActionData::none(),
            metadata: array_filter([
                'external_reference' => $request->externalReference,
                'mercado_pago_preference_id' => $preferenceId,
            ]),
            rawPayload: $response,
            payerEmail: $this->resolvePayerEmail($response) ?? $request->payerEmail,
        );
    }

    public function mapRefundResponseToResult(
        RefundRequestData $request,
        array $response,
    ): PaymentResultData {
        return new PaymentResultData(
            provider: 'mercado_pago',
            method: (string) ($request->method ?? data_get($request->metadata, 'method', 'unknown')),
            status: PaymentStatusEnum::REFUNDED,
            providerStatus: $this->nullableString(Arr::get($response, 'status', 'refunded')),
            references: new ProviderReferencesData(
                providerPaymentId: $request->providerPaymentId ?? data_get($request->metadata, 'provider_payment_id'),
                providerRefundId: $this->nullableString(Arr::get($response, 'id')),
            ),
            amount: $request->amount,
            currency: $this->nullableString(Arr::get($response, 'currency_id')),
            nextAction: NextActionData::none(),
            reason: $request->reason,
            metadata: array_filter([
                'mercado_pago_refund_id' => Arr::get($response, 'id'),
                'mercado_pago_refund_status' => Arr::get($response, 'status'),
            ]),
            rawPayload: $response,
            payerEmail: $this->resolvePayerEmail($response),
        );
    }

    private function resolvePayerEmail(array $response): ?string
    {
        return $this->nullableString(
            Arr::get($response, 'payer.email')
                ?? Arr::get($response, 'additional_info.payer.email')
        );
    }

    private function resolveNextAction(array $response): NextActionData
    {
        $redirectUrl = Arr::get($response, 'point_of_interaction.transaction_data.ticket_url')
            ?? Arr::get($response, 'transaction_details.external_resource_url')
            ?? Arr::get($response, 'init_point')
            ?? Arr::get($response, 'sandbox_init_point');

        if (! $redirectUrl) {
            return NextActionData::none();
        }

        return NextActionData::redirect((string) $redirectUrl);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
