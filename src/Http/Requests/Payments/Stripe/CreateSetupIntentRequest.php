<?php

namespace Equidna\StagHerd\Http\Requests\Payments\Stripe;

class CreateSetupIntentRequest extends StripeFormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'string', 'max:255'],
            'payer_reference' => ['required', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'return_url' => ['nullable', 'url', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_id' => $this->normalizeNullableString($this->input('customer_id')),
            'payer_reference' => $this->normalizeNullableString($this->input('payer_reference')),
            'payer_email' => $this->normalizeNullableString($this->input('payer_email')),
            'payer_name' => $this->normalizeNullableString($this->input('payer_name')),
            'return_url' => $this->normalizeNullableString($this->input('return_url')),
            'idempotency_key' => $this->normalizeNullableString($this->input('idempotency_key')),
        ]);
    }

    public function customerId(): ?string
    {
        return $this->validated('customer_id');
    }

    public function payerReference(): string
    {
        return $this->validated('payer_reference');
    }

    public function payerEmail(): ?string
    {
        return $this->validated('payer_email');
    }

    public function payerName(): ?string
    {
        return $this->validated('payer_name');
    }

    public function returnUrl(): ?string
    {
        return $this->validated('return_url');
    }

    public function idempotencyKey(): string
    {
        return $this->resolvedIdempotencyKey(
            $this->validated('idempotency_key'),
            'stag-herd-stripe-setup-',
        );
    }

    public function customMetadata(): array
    {
        $metadata = $this->validated('metadata') ?? [];

        return is_array($metadata)
            ? $this->cleanMetadata($metadata)
            : [];
    }
}
