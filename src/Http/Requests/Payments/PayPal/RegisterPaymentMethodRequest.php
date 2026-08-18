<?php

namespace Equidna\StagHerd\Http\Requests\Payments\PayPal;

use Illuminate\Validation\Validator;

class RegisterPaymentMethodRequest extends PayPalFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'owner_reference' => ['required', 'string', 'max:255'],
            'payment_token_id' => ['nullable', 'string', 'max:255'],
            'credential_context' => ['nullable', 'string', 'max:255'],

            'payment_token' => ['nullable', 'array'],
            'payment_token.id' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $paymentToken = $this->input('payment_token', []);

        if (!is_array($paymentToken)) {
            $paymentToken = [];
        }

        $this->merge([
            'owner_reference' => $this->normalizeNullableString($this->input('owner_reference')),
            'payment_token_id' => $this->normalizeNullableString($this->input('payment_token_id')),
            'credential_context' => $this->normalizeNullableString($this->input('credential_context')),
            'payment_token' => $paymentToken,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $paymentTokenId = $this->input('payment_token_id')
                ?? data_get($this->input('payment_token'), 'id');

            if (!is_string($paymentTokenId) || trim($paymentTokenId) === '') {
                $validator->errors()->add(
                    'payment_token_id',
                    'payment_token_id is required when payment_token.id is not provided.'
                );
            }
        });
    }

    public function ownerReference(): string
    {
        return (string) $this->validated('owner_reference');
    }

    public function paymentTokenId(): string
    {
        return (string) (
            $this->validated('payment_token_id')
            ?? data_get($this->input('payment_token'), 'id')
        );
    }

    public function credentialContext(): string
    {
        return (string) ($this->validated('credential_context') ?? 'default');
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentToken(): array
    {
        $paymentToken = $this->input('payment_token', []);

        return is_array($paymentToken)
            ? $this->cleanMetadata($paymentToken)
            : [];
    }
}
