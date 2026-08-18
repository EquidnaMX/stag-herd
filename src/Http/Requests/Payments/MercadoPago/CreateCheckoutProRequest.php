<?php

namespace Equidna\StagHerd\Http\Requests\Payments\MercadoPago;

use Equidna\StagHerd\Data\PaymentRequestData;

class CreateCheckoutProRequest extends MercadoPagoFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string'],
            'method' => ['nullable', 'string'],

            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_reference' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],

            'return_url' => ['required', 'url', 'max:500'],
            'cancel_url' => ['nullable', 'url', 'max:500'],

            'metadata' => ['nullable', 'array'],

            'mercado_pago' => ['nullable', 'array'],
            'mercado_pago.return_url' => ['nullable', 'url', 'max:500'],
            'mercado_pago.pending_url' => ['nullable', 'url', 'max:500'],
            'mercado_pago.cancel_url' => ['nullable', 'url', 'max:500'],
            'mercado_pago.notification_url' => ['nullable', 'url', 'max:500'],
            'mercado_pago.auto_return' => ['nullable', 'string', 'max:50'],
            'mercado_pago.statement_descriptor' => ['nullable', 'string', 'max:22'],
            'mercado_pago.binary_mode' => ['nullable', 'boolean'],
            'mercado_pago.expires' => ['nullable', 'boolean'],
            'mercado_pago.date_of_expiration' => ['nullable', 'string', 'max:100'],
            'mercado_pago.payment_methods' => ['nullable', 'array'],
            'mercado_pago.payer' => ['nullable', 'array'],
            'mercado_pago.metadata' => ['nullable', 'array'],
            'mercado_pago.payload' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $mercadoPago = $this->input('mercado_pago', []);

        if (!is_array($mercadoPago)) {
            $mercadoPago = [];
        }

        $mercadoPago['return_url'] = $this->normalizeNullableString($mercadoPago['return_url'] ?? null);
        $mercadoPago['pending_url'] = $this->normalizeNullableString($mercadoPago['pending_url'] ?? null);
        $mercadoPago['cancel_url'] = $this->normalizeNullableString($mercadoPago['cancel_url'] ?? null);
        $mercadoPago['notification_url'] = $this->normalizeNullableString($mercadoPago['notification_url'] ?? null);
        $mercadoPago['auto_return'] = $this->normalizeNullableString($mercadoPago['auto_return'] ?? null);
        $mercadoPago['statement_descriptor'] = $this->normalizeNullableString($mercadoPago['statement_descriptor'] ?? null);
        $mercadoPago['date_of_expiration'] = $this->normalizeNullableString($mercadoPago['date_of_expiration'] ?? null);

        $this->merge([
            'provider' => $this->normalizeLower($this->input('provider')),
            'method' => $this->normalizeLower($this->input('method')),
            'currency' => $this->normalizeUpper($this->input('currency')),
            'external_reference' => $this->normalizeNullableString($this->input('external_reference')),
            'payer_reference' => $this->normalizeNullableString($this->input('payer_reference')),
            'payer_email' => $this->normalizeNullableString($this->input('payer_email')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'return_url' => $this->normalizeNullableString($this->input('return_url')),
            'cancel_url' => $this->normalizeNullableString($this->input('cancel_url')),
            'mercado_pago' => $mercadoPago,
        ]);
    }

    public function provider(): string
    {
        return $this->validated('provider') ?? 'mercado_pago';
    }

    public function method(): string
    {
        return $this->validated('method') ?? 'checkout_pro';
    }

    public function externalReference(): string
    {
        return $this->validated('external_reference')
            ?? 'MP-CHECKOUT-PRO-' . now()->format('YmdHis');
    }

    public function metadata(): array
    {
        $metadata = $this->cleanMetadata($this->validated('metadata') ?? []);
        $mercadoPago = $this->validated('mercado_pago') ?? [];

        $metadata = array_replace_recursive($metadata, [
            'source' => 'stag-herd-mercado-pago-checkout-pro',
            'external_reference' => $this->externalReference(),
            'mercado_pago' => array_filter([
                'return_url' => data_get($mercadoPago, 'return_url'),
                'pending_url' => data_get($mercadoPago, 'pending_url'),
                'cancel_url' => data_get($mercadoPago, 'cancel_url'),
                'notification_url' => data_get($mercadoPago, 'notification_url'),
                'auto_return' => data_get($mercadoPago, 'auto_return'),
                'statement_descriptor' => data_get($mercadoPago, 'statement_descriptor'),
                'binary_mode' => data_get($mercadoPago, 'binary_mode'),
                'expires' => data_get($mercadoPago, 'expires'),
                'date_of_expiration' => data_get($mercadoPago, 'date_of_expiration'),
                'payment_methods' => data_get($mercadoPago, 'payment_methods'),
                'payer' => data_get($mercadoPago, 'payer'),
                'metadata' => data_get($mercadoPago, 'metadata'),
                'payload' => data_get($mercadoPago, 'payload'),
            ], fn ($value) => $value !== null && $value !== ''),
        ]);

        return $this->cleanMetadata($metadata);
    }

    public function toPaymentRequestData(): PaymentRequestData
    {
        return PaymentRequestData::fromDecimalAmount(
            amount: $this->validated('amount'),
            currency: $this->validated('currency'),
            method: $this->method(),
            provider: $this->provider(),
            externalReference: $this->externalReference(),
            payerReference: $this->validated('payer_reference'),
            payerEmail: $this->validated('payer_email'),
            description: $this->validated('description') ?? 'Payment from Mercado Pago Checkout Pro',
            returnUrl: $this->validated('return_url'),
            cancelUrl: $this->validated('cancel_url'),
            metadata: $this->metadata(),
        );
    }
}
