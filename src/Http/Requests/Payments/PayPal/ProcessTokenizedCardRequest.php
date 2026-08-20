<?php

namespace Equidna\StagHerd\Http\Requests\Payments\PayPal;

use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Validation\Validator;

class ProcessTokenizedCardRequest extends PayPalFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_reference' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'token_id' => ['nullable', 'string', 'max:255'],
            'token_type' => ['nullable', 'string', 'max:64'],
            'return_url' => ['nullable', 'url', 'max:500'],
            'cancel_url' => ['nullable', 'url', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'credential_context' => ['nullable', 'string', 'max:255'],
            'seller_merchant_id' => ['nullable', 'string', 'max:255'],
            'platform_attribution_id' => ['nullable', 'string', 'max:255'],
            'environment' => ['nullable', 'string', 'in:sandbox,live'],
            'external_metadata' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'platform_fee_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => $this->normalizeUpper($this->input('currency')),
            'external_reference' => $this->normalizeNullableString($this->input('external_reference')),
            'payer_reference' => $this->normalizeNullableString($this->input('payer_reference')),
            'payer_email' => $this->normalizeNullableString($this->input('payer_email')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'token_id' => $this->normalizeNullableString($this->input('token_id')),
            'token_type' => $this->normalizeUpper($this->input('token_type')),
            'return_url' => $this->normalizeNullableString($this->input('return_url')),
            'cancel_url' => $this->normalizeNullableString($this->input('cancel_url')),
            'idempotency_key' => $this->normalizeNullableString($this->input('idempotency_key')),
            'credential_context' => $this->normalizeNullableString($this->input('credential_context')),
            'seller_merchant_id' => $this->normalizeNullableString($this->input('seller_merchant_id')),
            'platform_attribution_id' => $this->normalizeNullableString($this->input('platform_attribution_id')),
            'environment' => $this->normalizeNullableString(strtolower((string) $this->input('environment'))),
            'platform_fee_amount' => $this->normalizeNullableString($this->input('platform_fee_amount')),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tokenId = $this->input('token_id');
            $payerReference = $this->input('payer_reference');

            if (!$tokenId && !$payerReference) {
                $validator->errors()->add(
                    'payer_reference',
                    'payer_reference is required when token_id is not provided.'
                );
            }
        });
    }

    public function toPaymentRequestData(): PaymentRequestData
    {
        $data = $this->validated();

        $externalReference = $data['external_reference']
            ?: 'PAYPAL-TOKENIZED-' . now()->format('YmdHis');

        $idempotencyKey = $this->resolvedIdempotencyKey(
            $data['idempotency_key'] ?? null,
            'stag-herd-paypal-tokenized-',
        );

        $metadata = array_replace_recursive(
            $this->cleanMetadata($data['metadata'] ?? []),
            [
                'source' => 'stag-herd-paypal-tokenized-card',
                'external_reference' => $externalReference,
                'paypal' => [
                    'token_id' => $data['token_id'] ?? null,
                    'token_type' => $data['token_type'] ?? 'BILLING_AGREEMENT',
                    'return_url' => $data['return_url'] ?? null,
                    'cancel_url' => $data['cancel_url'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                ],
            ],
        );

        return PaymentRequestData::fromDecimalAmount(
            amount: $data['amount'],
            currency: $data['currency'],
            method: 'tokenized_card',
            provider: 'paypal',
            externalReference: $externalReference,
            payerReference: $data['payer_reference'] ?? null,
            payerEmail: $data['payer_email'] ?? null,
            description: $data['description'] ?? 'Payment with stored PayPal card',
            returnUrl: $data['return_url'] ?? null,
            cancelUrl: $data['cancel_url'] ?? null,
            metadata: $this->cleanMetadata($metadata),
            credentialContext: (string) ($data['credential_context'] ?? 'default'),
            sellerMerchantId: $data['seller_merchant_id'] ?? null,
            platformAttributionId: $data['platform_attribution_id'] ?? null,
            environment: $data['environment'] ?? null,
            externalMetadata: $data['external_metadata'] ?? [],
            platformFeeAmount: $data['platform_fee_amount'] ?? null,
        );
    }
}
