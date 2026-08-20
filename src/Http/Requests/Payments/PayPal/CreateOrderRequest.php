<?php

namespace Equidna\StagHerd\Http\Requests\Payments\PayPal;

use Equidna\StagHerd\Data\PayPalRequestContextData;
use Equidna\StagHerd\Support\MoneyFormatter;

class CreateOrderRequest extends PayPalFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'return_url' => ['nullable', 'url', 'max:500'],
            'cancel_url' => ['nullable', 'url', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'metadata' => ['nullable', 'array'],

            'paypal' => ['nullable', 'array'],
            'paypal.intent' => ['nullable', 'string'],
            'paypal.brand_name' => ['nullable', 'string'],
            'paypal.landing_page' => ['nullable', 'string'],
            'paypal.user_action' => ['nullable', 'string'],
            'paypal.shipping_preference' => ['nullable', 'string'],
            'paypal.invoice_id' => ['nullable', 'string'],
            'paypal.return_url' => ['nullable', 'url', 'max:500'],
            'paypal.cancel_url' => ['nullable', 'url', 'max:500'],

            'credential_context' => ['nullable', 'string', 'max:100'],
            'seller_merchant_id' => ['nullable', 'string', 'max:255'],
            'platform_attribution_id' => ['nullable', 'string', 'max:255'],
            'environment' => ['nullable', 'string', 'in:sandbox,live'],
            'external_metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $paypal = $this->input('paypal', []);

        if (!is_array($paypal)) {
            $paypal = [];
        }

        $paypal['intent'] = $this->normalizeNullableString($paypal['intent'] ?? null);
        $paypal['brand_name'] = $this->normalizeNullableString($paypal['brand_name'] ?? null);
        $paypal['landing_page'] = $this->normalizeNullableString($paypal['landing_page'] ?? null);
        $paypal['user_action'] = $this->normalizeNullableString($paypal['user_action'] ?? null);
        $paypal['shipping_preference'] = $this->normalizeNullableString($paypal['shipping_preference'] ?? null);
        $paypal['invoice_id'] = $this->normalizeNullableString($paypal['invoice_id'] ?? null);
        $paypal['return_url'] = $this->normalizeNullableString($paypal['return_url'] ?? null);
        $paypal['cancel_url'] = $this->normalizeNullableString($paypal['cancel_url'] ?? null);

        $this->merge([
            'currency' => $this->normalizeUpper($this->input('currency')),
            'external_reference' => $this->normalizeNullableString($this->input('external_reference')),
            'payer_email' => $this->normalizeNullableString($this->input('payer_email')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'return_url' => $this->normalizeNullableString($this->input('return_url')),
            'cancel_url' => $this->normalizeNullableString($this->input('cancel_url')),
            'idempotency_key' => $this->normalizeNullableString($this->input('idempotency_key')),
            'credential_context' => $this->normalizeNullableString($this->input('credential_context')) ?? 'default',
            'seller_merchant_id' => $this->normalizeNullableString($this->input('seller_merchant_id')),
            'platform_attribution_id' => $this->normalizeNullableString($this->input('platform_attribution_id')),
            'environment' => $this->normalizeNullableString(strtolower((string) $this->input('environment'))),
            'paypal' => $paypal,
        ]);
    }

    public function externalReference(): string
    {
        return $this->validated('external_reference')
            ?? 'PAYPAL-' . now()->format('YmdHis');
    }

    public function currency(): string
    {
        return $this->validated('currency');
    }

    public function amountInMinorUnits(): int
    {
        return MoneyFormatter::fromDecimal($this->validated('amount'));
    }

    public function idempotencyKey(): string
    {
        return $this->resolvedIdempotencyKey(
            $this->validated('idempotency_key'),
            'stag-herd-paypal-order-',
        );
    }

    public function metadata(): array
    {
        $metadata = $this->validated('metadata') ?? [];

        return is_array($metadata)
            ? $this->cleanMetadata($metadata)
            : [];
    }

    public function resolvedReturnUrl(): string
    {
        return $this->validated('return_url')
            ?? data_get($this->validated('paypal') ?? [], 'return_url')
            ?? $this->resolveHostUrl();
    }

    public function resolvedCancelUrl(): string
    {
        return $this->validated('cancel_url')
            ?? data_get($this->validated('paypal') ?? [], 'cancel_url')
            ?? $this->resolveHostUrl();
    }

    public function purchaseUnit(): array
    {
        $metadata = $this->metadata();
        $externalReference = $this->externalReference();

        $purchaseUnit = array_filter([
            'reference_id' => $externalReference,
            'description' => $this->validated('description') ?? 'Payment from PayPal Buttons',
            'custom_id' => data_get($metadata, 'id_client'),
            'invoice_id' => data_get($this->validated('paypal') ?? [], 'invoice_id'),
            'amount' => [
                'currency_code' => $this->currency(),
                'value' => MoneyFormatter::toDecimal($this->amountInMinorUnits()),
            ],
        ], fn($value) => $value !== null && $value !== '');

        $sellerMerchantId = $this->validated('seller_merchant_id');

        if (is_string($sellerMerchantId) && trim($sellerMerchantId) !== '') {
            $purchaseUnit['payee'] = [
                'merchant_id' => trim($sellerMerchantId),
            ];
        }

        return $purchaseUnit;
    }

    public function payload(): array
    {
        $paypal = $this->validated('paypal') ?? [];

        return [
            'intent' => strtoupper((string) ($paypal['intent'] ?? 'CAPTURE')),
            'purchase_units' => [
                $this->purchaseUnit(),
            ],
            'application_context' => array_filter([
                'return_url' => $this->resolvedReturnUrl(),
                'cancel_url' => $this->resolvedCancelUrl(),
                'brand_name' => $paypal['brand_name'] ?? config('app.name'),
                'landing_page' => $paypal['landing_page'] ?? 'LOGIN',
                'user_action' => $paypal['user_action'] ?? 'PAY_NOW',
                'shipping_preference' => $paypal['shipping_preference'] ?? 'NO_SHIPPING',
            ], fn($value) => $value !== null && $value !== ''),
        ];
    }

    public function checkoutContext(): array
    {
        return [
            'amount' => $this->validated('amount'),
            'currency' => $this->currency(),
            'external_reference' => $this->externalReference(),
            'payer_email' => $this->validated('payer_email') ?? 'cliente@test.com',
            'description' => $this->validated('description') ?? 'Payment from PayPal Buttons',
            'credential_context' => $this->validated('credential_context') ?? 'default',
            'seller_merchant_id' => $this->validated('seller_merchant_id'),
            'platform_attribution_id' => $this->validated('platform_attribution_id'),
            'environment' => $this->validated('environment'),
            'external_metadata' => $this->validated('external_metadata') ?? [],
            'metadata' => array_replace_recursive($this->metadata(), [
                'paypal_create_idempotency_key' => $this->idempotencyKey(),
            ]),
        ];
    }

    public function paypalContext(): PayPalRequestContextData
    {
        return PayPalRequestContextData::fromArray([
            'credential_context' => $this->validated('credential_context') ?? 'default',
            'seller_merchant_id' => $this->validated('seller_merchant_id'),
            'platform_attribution_id' => $this->validated('platform_attribution_id'),
            'environment' => $this->validated('environment'),
            'external_metadata' => $this->validated('external_metadata') ?? [],
        ]);
    }
}
