<?php

namespace Equidna\StagHerd\Http\Requests\Payments\MercadoPago;

use Equidna\StagHerd\Data\PaymentRequestData;

class ProcessBrickRequest extends MercadoPagoFormRequest
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
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],

            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'device_id' => ['nullable', 'string', 'max:255'],

            'metadata' => ['nullable', 'array'],

            'mercado_pago' => ['nullable', 'array'],
            'mercado_pago.token' => ['nullable', 'string'],
            'mercado_pago.payment_method_id' => ['nullable', 'string'],
            'mercado_pago.issuer_id' => ['nullable'],
            'mercado_pago.installments' => ['nullable'],
            'mercado_pago.payer' => ['nullable', 'array'],
            'mercado_pago.idempotency_key' => ['nullable', 'string', 'max:64'],
            'mercado_pago.device_id' => ['nullable', 'string', 'max:255'],

            'token' => ['nullable', 'string'],
            'payment_method_id' => ['nullable', 'string'],
            'issuer_id' => ['nullable'],
            'installments' => ['nullable'],
            'payer' => ['nullable', 'array'],

            'raw_form_data' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $mercadoPago = $this->input('mercado_pago', []);

        if (!is_array($mercadoPago)) {
            $mercadoPago = [];
        }

        $mercadoPago['token'] = $this->normalizeNullableString($mercadoPago['token'] ?? null);
        $mercadoPago['payment_method_id'] = $this->normalizeNullableString($mercadoPago['payment_method_id'] ?? null);
        $mercadoPago['idempotency_key'] = $this->normalizeNullableString($mercadoPago['idempotency_key'] ?? null);
        $mercadoPago['device_id'] = $this->normalizeNullableString($mercadoPago['device_id'] ?? null);

        $this->merge([
            'provider' => $this->normalizeLower($this->input('provider')),
            'method' => $this->normalizeLower($this->input('method')),
            'currency' => $this->normalizeUpper($this->input('currency')),
            'external_reference' => $this->normalizeNullableString($this->input('external_reference')),
            'payer_email' => $this->normalizeNullableString($this->input('payer_email')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'idempotency_key' => $this->normalizeNullableString($this->input('idempotency_key')),
            'device_id' => $this->normalizeNullableString($this->input('device_id')),
            'token' => $this->normalizeNullableString($this->input('token')),
            'payment_method_id' => $this->normalizeNullableString($this->input('payment_method_id')),
            'mercado_pago' => $mercadoPago,
        ]);
    }

    public function token(): ?string
    {
        $mercadoPago = $this->validated('mercado_pago') ?? [];

        return data_get($mercadoPago, 'token')
            ?? $this->validated('token');
    }

    public function paymentMethodId(): ?string
    {
        $mercadoPago = $this->validated('mercado_pago') ?? [];

        return data_get($mercadoPago, 'payment_method_id')
            ?? $this->validated('payment_method_id');
    }

    public function issuerId(): mixed
    {
        $mercadoPago = $this->validated('mercado_pago') ?? [];

        return data_get($mercadoPago, 'issuer_id')
            ?? $this->validated('issuer_id');
    }

    public function installments(): int
    {
        $mercadoPago = $this->validated('mercado_pago') ?? [];

        return (int) (
            data_get($mercadoPago, 'installments')
            ?? $this->validated('installments')
            ?? 1
        );
    }

    public function payerFromMercadoPago(): array
    {
        $payer = data_get($this->validated('mercado_pago') ?? [], 'payer', []);

        return is_array($payer) ? $payer : [];
    }

    public function payerFromRoot(): array
    {
        $payer = $this->validated('payer') ?? [];

        return is_array($payer) ? $payer : [];
    }

    public function payerEmail(): string
    {
        return $this->validated('payer_email')
            ?? data_get($this->payerFromMercadoPago(), 'email')
            ?? data_get($this->payerFromRoot(), 'email')
            ?? data_get($this->validated('raw_form_data') ?? [], 'payer.email')
            ?? 'cliente@test.com';
    }

    public function externalReference(): string
    {
        return $this->validated('external_reference')
            ?? 'BRICK-' . now()->format('YmdHis');
    }

    public function idempotencyKey(): string
    {
        return $this->resolvedIdempotencyKey(
            data_get($this->validated('mercado_pago') ?? [], 'idempotency_key')
                ?? $this->validated('idempotency_key'),
            '',
            64,
        );
    }

    public function deviceId(): ?string
    {
        return data_get($this->validated('mercado_pago') ?? [], 'device_id')
            ?? $this->validated('device_id');
    }

    public function provider(): string
    {
        return $this->validated('provider') ?? 'mercado_pago';
    }

    public function method(): string
    {
        return $this->validated('method') ?? 'card';
    }

    public function metadata(): array
    {
        $metadata = $this->cleanMetadata($this->validated('metadata') ?? []);

        $metadata = array_replace_recursive($metadata, [
            'source' => 'stag-herd-brick-host-ui',
            'external_reference' => $this->externalReference(),
            'mercado_pago' => array_filter([
                'token' => $this->token(),
                'payment_method_id' => $this->paymentMethodId(),
                'issuer_id' => $this->issuerId(),
                'installments' => $this->installments(),
                'payer' => array_merge(
                    $this->payerFromMercadoPago(),
                    $this->payerFromRoot(),
                    ['email' => $this->payerEmail()],
                ),
                'idempotency_key' => $this->idempotencyKey(),
                'device_id' => $this->deviceId(),
            ], fn ($value) => $value !== null && $value !== ''),
            'raw_form_data' => $this->validated('raw_form_data') ?? null,
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
            payerReference: null,
            payerEmail: $this->payerEmail(),
            description: $this->validated('description') ?? 'Payment from Mercado Pago Brick',
            metadata: $this->metadata(),
        );
    }
}
