<?php

namespace Equidna\StagHerd\Infrastructure\Providers\MercadoPago;

use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Contracts\PaymentProvider;
use Equidna\StagHerd\Data\NextActionData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MercadoPagoProvider implements PaymentProvider
{
    public function __construct(
        private readonly MercadoPagoGateway $gateway,
        private readonly MercadoPagoStatusMapper $statusMapper = new MercadoPagoStatusMapper(),
    ) {
        //
    }

    public function getName(): string
    {
        return 'mercado_pago';
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        $this->validateCreatePaymentRequest($request);

        $payload = $this->buildCreatePaymentPayload($request);

        $response = $this->gateway->createPayment(
            payload: $payload,
            idempotencyKey: $this->resolveIdempotencyKey($request),
        );

        return $this->mapPaymentResponseToResult($request, $response);
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        $providerPaymentId = $this->resolveProviderPaymentId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $response = $this->gateway->getPayment($providerPaymentId);

        return $this->mapPaymentResponseToResultFromOperation(
            method: (string) ($request->metadata['method'] ?? 'unknown'),
            response: $response,
        );
    }

    public function confirmPayment(PaymentConfirmationData $request): PaymentResultData
    {
        /*
     * Para esta fase, confirmación en Mercado Pago se maneja como consulta
     * del estado real del pago. Si después implementas captura separada,
     * aquí se puede cambiar a capture.
     */
        $providerPaymentId = $this->resolveProviderPaymentId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $response = $this->gateway->getPayment($providerPaymentId);

        return $this->mapPaymentResponseToResultFromOperation(
            method: (string) ($request->metadata['method'] ?? 'unknown'),
            response: $response,
        );
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        $providerPaymentId = $this->resolveProviderPaymentId(
            providerPaymentId: $request->providerPaymentId,
            metadata: $request->metadata,
        );

        $response = $this->gateway->cancelPayment($providerPaymentId);

        return $this->mapPaymentResponseToResultFromOperation(
            method: (string) ($request->metadata['method'] ?? 'unknown'),
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
            idempotencyKey: $request->metadata['idempotency_key'] ?? null,
        );

        return $this->mapRefundResponseToResult(
            request: $request,
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

        $mercadoPago = $request->metadata['mercado_pago'] ?? [];

        if ($request->method === 'card') {
            if (empty($mercadoPago['token'])) {
                throw InvalidPaymentPayloadException::missingField('metadata.mercado_pago.token');
            }

            if (empty($mercadoPago['payment_method_id'])) {
                throw InvalidPaymentPayloadException::missingField('metadata.mercado_pago.payment_method_id');
            }
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
            'payment_method_id' => $this->resolvePaymentMethodId($request),
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

    private function resolvePaymentMethodId(PaymentRequestData $request): string
    {
        $mercadoPago = $request->metadata['mercado_pago'] ?? [];

        if (isset($mercadoPago['payment_method_id'])) {
            return (string) $mercadoPago['payment_method_id'];
        }

        return match ($request->method) {
            'wallet' => 'account_money',
            default => $request->method,
        };
    }

    private function resolveIdempotencyKey(PaymentRequestData $request): string
    {
        $mercadoPago = $request->metadata['mercado_pago'] ?? [];

        if (isset($mercadoPago['idempotency_key'])) {
            return (string) $mercadoPago['idempotency_key'];
        }

        if ($request->externalReference) {
            return 'stag-herd-' . $request->externalReference;
        }

        return 'stag-herd-' . (string) Str::uuid();
    }

    /**
     * @param array<string, mixed> $response
     */
    private function mapPaymentResponseToResult(
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
        );
    }

    /**
     * @param array<string, mixed> $response
     */
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

    private function resolveProviderPaymentId(
        ?string $providerPaymentId,
        array $metadata = [],
    ): string {
        $resolved = $providerPaymentId
            ?? $metadata['provider_payment_id']
            ?? null;

        if (! $resolved) {
            throw InvalidPaymentPayloadException::missingField('provider_payment_id');
        }

        return (string) $resolved;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function mapPaymentResponseToResultFromOperation(
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
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function mapRefundResponseToResult(
        RefundRequestData $request,
        array $response,
    ): PaymentResultData {
        $providerRefundStatus = $this->nullableString(Arr::get($response, 'status', 'refunded'));

        return new PaymentResultData(
            provider: $this->getName(),
            method: (string) ($request->metadata['method'] ?? 'unknown'),
            status: \Equidna\StagHerd\Domain\Enums\PaymentStatusEnum::REFUNDED,
            providerStatus: $providerRefundStatus,
            references: new ProviderReferencesData(
                providerPaymentId: $request->providerPaymentId ?? $request->metadata['provider_payment_id'] ?? null,
                providerRefundId: $this->nullableString(Arr::get($response, 'id')),
            ),
            amount: $request->amount,
            currency: $this->nullableString(Arr::get($response, 'currency_id')),
            reason: $request->reason,
            metadata: array_filter([
                'mercado_pago_refund_id' => Arr::get($response, 'id'),
                'mercado_pago_refund_status' => Arr::get($response, 'status'),
            ]),
            rawPayload: $response,
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
