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

final class MercadoPagoCheckoutProHandler implements PaymentMethodHandler
{
    public function __construct(
        private readonly MercadoPagoGateway $gateway,
        private readonly MercadoPagoResultMapper $mapper,
    ) {
        //
    }

    public function getMethod(): string
    {
        return 'checkout_pro';
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        $this->validateCreatePaymentRequest($request);

        $response = $this->gateway->createPreference(
            $this->buildCreatePreferencePayload($request),
        );

        return $this->mapper->mapPreferenceResponseToResult($request, $response);
    }

    public function confirmPayment(PaymentConfirmationData $request): PaymentResultData
    {
        throw UnsupportedOperationException::forOperation(
            'confirm',
            'Mercado Pago Checkout Pro confirmation must be resolved through lookup/webhook flow.'
        );
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        return match ($request->lookupType()) {
            'provider_payment_id' => $this->lookupByProviderPaymentId($request),
            'provider_order_id' => $this->lookupByProviderOrderId($request),

            default => throw UnsupportedOperationException::forOperation(
                'lookup',
                'Mercado Pago Checkout Pro lookup requires providerPaymentId or providerOrderId.'
            ),
        };
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        throw UnsupportedOperationException::forOperation(
            'cancel',
            'Mercado Pago Checkout Pro cancellation is not implemented.'
        );
    }

    public function refundPayment(RefundRequestData $request): PaymentResultData
    {
        throw UnsupportedOperationException::forOperation(
            'refund',
            'Mercado Pago Checkout Pro refund is not implemented yet.'
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

        if (!$request->returnUrl) {
            throw InvalidPaymentPayloadException::missingField('return_url');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreatePreferencePayload(PaymentRequestData $request): array
    {
        $mercadoPago = $request->metadata['mercado_pago'] ?? [];

        if (isset($mercadoPago['payload']) && is_array($mercadoPago['payload'])) {
            return $mercadoPago['payload'];
        }

        $payload = [
            'items' => [[
                'title' => $request->description ?? $request->externalReference ?? 'Payment',
                'quantity' => 1,
                'currency_id' => strtoupper($request->currency),
                'unit_price' => (float) MoneyFormatter::toDecimal($request->amount),
            ]],
            'payer' => array_filter([
                'email' => $request->payerEmail,
            ]),
            'external_reference' => $request->externalReference,
            'back_urls' => array_filter([
                'success' => $mercadoPago['return_url'] ?? $request->returnUrl,
                'pending' => $mercadoPago['pending_url'] ?? $request->returnUrl,
                'failure' => $mercadoPago['cancel_url'] ?? $request->cancelUrl ?? $request->returnUrl,
            ]),
            'auto_return' => $mercadoPago['auto_return'] ?? 'approved',
            'notification_url' => $mercadoPago['notification_url'] ?? null,
            'statement_descriptor' => $mercadoPago['statement_descriptor'] ?? null,
            'expires' => $mercadoPago['expires'] ?? null,
            'date_of_expiration' => $mercadoPago['date_of_expiration'] ?? null,
            'binary_mode' => $mercadoPago['binary_mode'] ?? null,
            'metadata' => array_filter([
                'payer_reference' => $request->payerReference,
                'source' => $request->metadata['source'] ?? null,
                'external_reference' => $request->externalReference,
            ]),
        ];

        if (isset($mercadoPago['payer']) && is_array($mercadoPago['payer'])) {
            $payload['payer'] = array_replace_recursive(
                $payload['payer'],
                $mercadoPago['payer'],
            );
        }

        if (isset($mercadoPago['metadata']) && is_array($mercadoPago['metadata'])) {
            $payload['metadata'] = array_replace(
                $payload['metadata'],
                $mercadoPago['metadata'],
            );
        }

        foreach (
            [
                'payment_methods',
                'marketplace',
                'marketplace_fee',
                'differential_pricing',
                'shipments',
                'purpose',
            ] as $optionalField
        ) {
            if (array_key_exists($optionalField, $mercadoPago)) {
                $payload[$optionalField] = $mercadoPago[$optionalField];
            }
        }

        return array_filter(
            $payload,
            fn ($value) => $value !== null && $value !== [] && $value !== ''
        );
    }

    private function lookupByProviderPaymentId(PaymentLookupData $request): PaymentResultData
    {
        $response = $this->gateway->getPayment((string) $request->providerPaymentId);

        return $this->mapper->mapPaymentResponseToResultFromOperation(
            method: $request->method ?? $this->getMethod(),
            response: $response,
        );
    }

    private function lookupByProviderOrderId(PaymentLookupData $request): PaymentResultData
    {
        $response = $this->gateway->searchPayments([
            'external_reference' => $request->providerOrderId,
            'sort' => 'date_created',
            'criteria' => 'desc',
            'limit' => 1,
        ]);

        $results = $response['results'] ?? [];

        if (!is_array($results) || $results === []) {
            throw UnsupportedOperationException::forOperation(
                'lookup',
                'Mercado Pago Checkout Pro lookup by providerOrderId did not return payments.'
            );
        }

        return $this->mapper->mapPaymentResponseToResultFromOperation(
            method: $request->method ?? $this->getMethod(),
            response: $results[0],
        );
    }
}
