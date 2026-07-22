<?php

namespace Equidna\StagHerd\Http\Requests\Payments;

class CancelPaymentRequest extends PaymentFormRequest
{
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
