<?php

namespace Equidna\StagHerd\Http\Requests\Payments\Stripe;

class ConfirmPaymentIntentRequest extends StripeFormRequest
{
    public function rules(): array
    {
        return [
            'provider_payment_id' => ['required', 'string', 'max:255'],
            'stripe_status' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider_payment_id' => $this->normalizeNullableString($this->input('provider_payment_id')),
            'stripe_status' => $this->normalizeNullableString($this->input('stripe_status')),
            'payer_email' => $this->normalizeNullableString($this->input('payer_email')),
            'external_reference' => $this->normalizeNullableString($this->input('external_reference')),
            'payer_reference' => $this->normalizeNullableString($this->input('payer_reference')),
            'description' => $this->normalizeNullableString($this->input('description')),
        ]);
    }

    public function providerPaymentId(): string
    {
        return $this->validated('provider_payment_id');
    }

    public function stripeStatus(): ?string
    {
        return $this->validated('stripe_status');
    }

    public function inputMetadata(): array
    {
        $metadata = $this->validated('metadata') ?? [];

        return is_array($metadata)
            ? $this->cleanMetadata($metadata)
            : [];
    }

    public function payerEmail(): ?string
    {
        return $this->validated('payer_email');
    }

    public function externalReference(): ?string
    {
        return $this->validated('external_reference');
    }

    public function payerReference(): ?string
    {
        return $this->validated('payer_reference');
    }

    public function description(): ?string
    {
        return $this->validated('description');
    }
}
