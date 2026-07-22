<?php

namespace Equidna\StagHerd\Http\Requests\Payments;

class ProviderLookupPaymentRequest extends PaymentFormRequest
{
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string'],
            'search_type' => ['required', 'string', 'in:provider_payment_id,provider_order_id'],
            'search_value' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => $this->normalizeLower($this->input('provider')),
            'search_type' => $this->normalizeNullableString($this->input('search_type')),
            'search_value' => $this->normalizeNullableString($this->input('search_value')),
        ]);
    }

    public function provider(): string
    {
        return $this->validated('provider');
    }

    public function searchType(): string
    {
        return $this->validated('search_type');
    }

    public function searchValue(): string
    {
        return $this->validated('search_value');
    }
}
