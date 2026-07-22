<?php

namespace Equidna\StagHerd\Http\Requests\Payments\Stripe;

class CompleteSetupIntentRequest extends StripeFormRequest
{
    public function rules(): array
    {
        return [
            'setup_intent_id' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'setup_intent_id' => $this->normalizeNullableString($this->input('setup_intent_id')),
            'customer_id' => $this->normalizeNullableString($this->input('customer_id')),
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
}
