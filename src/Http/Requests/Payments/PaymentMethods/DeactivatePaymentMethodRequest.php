<?php

namespace Equidna\StagHerd\Http\Requests\Payments\PaymentMethods;

use Equidna\StagHerd\Data\PaymentMethodDeactivateData;
use Illuminate\Foundation\Http\FormRequest;

class DeactivatePaymentMethodRequest extends FormRequest
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
            'provider_payment_method_id' => ['required', 'string', 'max:255'],
            'credential_context' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => $this->normalizeNullableString($this->input('provider')),
            'owner_reference' => $this->normalizeNullableString($this->input('owner_reference')),
            'provider_payment_method_id' => $this->normalizeNullableString($this->input('provider_payment_method_id')),
            'credential_context' => $this->normalizeNullableString($this->input('credential_context')) ?? 'default',
        ]);
    }

    public function toData(): PaymentMethodDeactivateData
    {
        return new PaymentMethodDeactivateData(
            provider: (string) $this->validated('provider'),
            ownerReference: (string) $this->validated('owner_reference'),
            providerPaymentMethodId: (string) $this->validated('provider_payment_method_id'),
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
