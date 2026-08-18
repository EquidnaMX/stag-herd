<?php

namespace Equidna\StagHerd\Http\Requests\Payments;

class CancelPaymentRequest extends PaymentFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => $this->normalizeNullableString($this->input('reason')),
        ]);
    }

    public function cancelReason(): string
    {
        return $this->validated('reason') ?: 'Cancelled from host UI';
    }
}
