<?php

namespace Equidna\StagHerd\Http\Requests\Payments\PayPal;

use Equidna\StagHerd\Data\PayPalRequestContextData;
use Equidna\StagHerd\Data\PaymentRequestData;

class CaptureOrderRequest extends PayPalFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider_order_id' => ['required', 'string', 'max:255'],

            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'metadata' => ['nullable', 'array'],

            'credential_context' => ['nullable', 'string', 'max:100'],
            'seller_merchant_id' => ['nullable', 'string', 'max:255'],
            'platform_attribution_id' => ['nullable', 'string', 'max:255'],
            'environment' => ['nullable', 'string', 'in:sandbox,live'],
            'external_metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider_order_id' => $this->normalizeNullableString($this->input('provider_order_id')),
            'currency' => $this->normalizeUpper($this->input('currency')),
            'external_reference' => $this->normalizeNullableString($this->input('external_reference')),
            'payer_email' => $this->normalizeNullableString($this->input('payer_email')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'idempotency_key' => $this->normalizeNullableString($this->input('idempotency_key')),
            'credential_context' => $this->normalizeNullableString($this->input('credential_context')) ?? 'default',
            'seller_merchant_id' => $this->normalizeNullableString($this->input('seller_merchant_id')),
            'platform_attribution_id' => $this->normalizeNullableString($this->input('platform_attribution_id')),
            'environment' => $this->normalizeNullableString(strtolower((string) $this->input('environment'))),
        ]);
    }

    public function providerOrderId(): string
    {
        return $this->validated('provider_order_id');
    }

    public function captureIdempotencyKey(): string
    {
        return $this->resolvedIdempotencyKey(
            $this->validated('idempotency_key'),
            'stag-herd-paypal-capture-' . $this->providerOrderId(),
        );
    }

    public function metadata(): array
    {
        $metadata = $this->validated('metadata') ?? [];

        $metadata = is_array($metadata)
            ? $this->cleanMetadata($metadata)
            : [];

        $metadata = array_replace_recursive($metadata, [
            'source' => 'stag-herd-paypal-host-ui-after-capture',
            'paypal_order_id' => $this->providerOrderId(),
            'idempotency_key' => $this->captureIdempotencyKey(),
        ]);

        return $this->cleanMetadata($metadata);
    }

    public function toPaymentRequestData(string $method): PaymentRequestData
    {
        return PaymentRequestData::fromDecimalAmount(
            amount: $this->validated('amount'),
            currency: $this->validated('currency'),
            method: $method,
            provider: 'paypal',
            providerOrderId: $this->providerOrderId(),
            externalReference: $this->validated('external_reference') ?? $this->providerOrderId(),
            payerReference: data_get($this->metadata(), 'id_client'),
            payerEmail: $this->validated('payer_email') ?? 'cliente@test.com',
            description: $this->validated('description') ?? 'Captured PayPal payment',
            credentialContext: $this->validated('credential_context') ?? 'default',
            sellerMerchantId: $this->validated('seller_merchant_id'),
            platformAttributionId: $this->validated('platform_attribution_id'),
            environment: $this->validated('environment'),
            externalMetadata: $this->validated('external_metadata') ?? [],
            metadata: $this->metadata(),
        );
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
