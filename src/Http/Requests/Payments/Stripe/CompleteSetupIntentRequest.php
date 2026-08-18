<?php

namespace Equidna\StagHerd\Http\Requests\Payments\Stripe;

class CompleteSetupIntentRequest extends StripeFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'setup_intent_id' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'string', 'max:255'],
            'credential_context' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'setup_intent_id' => $this->normalizeNullableString($this->input('setup_intent_id')),
            'customer_id' => $this->normalizeNullableString($this->input('customer_id')),
            'credential_context' => $this->normalizeNullableString($this->input('credential_context')),
        ]);
    }

    public function setupIntentId(): string
    {
        return $this->validated('setup_intent_id');
    }

    public function customerId(): string
    {
        return $this->validated('customer_id');
    }

    public function credentialContext(?string $fallback = null): string
    {
        $credentialContext = $this->validated('credential_context');

        if (is_string($credentialContext) && $credentialContext !== '') {
            return $credentialContext;
        }

        if (is_string($fallback) && trim($fallback) !== '') {
            return trim($fallback);
        }

        return 'default';
    }
}
