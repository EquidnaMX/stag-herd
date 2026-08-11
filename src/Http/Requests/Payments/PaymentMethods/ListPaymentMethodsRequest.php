<?php

namespace Equidna\StagHerd\Http\Requests\Payments\PaymentMethods;

use Equidna\StagHerd\Data\PaymentMethodsListData;
use Illuminate\Foundation\Http\FormRequest;

class ListPaymentMethodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'max:255'],
            'owner_reference' => ['required', 'string', 'max:255'],
            'credential_context' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => $this->normalizeNullableString($this->input('provider')),
            'owner_reference' => $this->normalizeNullableString($this->input('owner_reference')),
            'credential_context' => $this->normalizeNullableString($this->input('credential_context')) ?? 'default',
        ]);
    }

    public function toData(): PaymentMethodsListData
    {
        return new PaymentMethodsListData(
            provider: (string) $this->validated('provider'),
            ownerReference: (string) $this->validated('owner_reference'),
            credentialContext: (string) ($this->validated('credential_context') ?? 'default'),
        );
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
