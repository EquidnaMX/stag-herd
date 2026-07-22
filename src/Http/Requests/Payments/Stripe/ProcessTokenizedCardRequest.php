<?php

namespace Equidna\StagHerd\Http\Requests\Payments\Stripe;

use Equidna\StagHerd\Data\PaymentRequestData;

class ProcessTokenizedCardRequest extends StripeFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_reference' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['required', 'string', 'max:255', 'regex:/^cus_/'],
            'payment_method_id' => ['required', 'string', 'max:255', 'regex:/^pm_/'],
            'off_session' => ['nullable', 'boolean'],
            'return_url' => ['nullable', 'url', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
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
            'customer_id' => $this->normalizeNullableString($this->input('customer_id')),
            'payment_method_id' => $this->normalizeNullableString($this->input('payment_method_id')),
            'return_url' => $this->normalizeNullableString($this->input('return_url')),
            'idempotency_key' => $this->normalizeNullableString($this->input('idempotency_key')),
        ]);
    }

    public function toPaymentRequestData(): PaymentRequestData
    {
        $data = $this->validated();

        $externalReference = $this->resolvedExternalReference(
            'STRIPE-TOKENIZED',
            $data['external_reference'] ?? null,
        );

        $idempotencyKey = $this->resolvedIdempotencyKey(
            $data['idempotency_key'] ?? null,
            'stag-herd-stripe-tokenized-',
        );

        $metadata = array_replace_recursive(
            $this->cleanMetadata($data['metadata'] ?? []),
            [
                'source' => 'stag-herd-stripe-tokenized-card',
                'external_reference' => $externalReference,
                'stripe' => [
                    'customer' => $data['customer_id'],
                    'payment_method' => $data['payment_method_id'],
                    'off_session' => (bool) ($data['off_session'] ?? false),
                    'return_url' => $data['return_url'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                ],
            ],
        );

        return PaymentRequestData::fromDecimalAmount(
            amount: $data['amount'],
            currency: $data['currency'],
            method: 'tokenized_card',
            provider: 'stripe',
            externalReference: $externalReference,
            payerReference: $data['payer_reference'] ?? null,
            payerEmail: $data['payer_email'] ?? null,
            description: $data['description'] ?? 'Payment with stored Stripe card',
            returnUrl: $data['return_url'] ?? null,
            metadata: $this->cleanMetadata($metadata),
        );
    }
}
