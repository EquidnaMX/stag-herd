<?php

namespace Equidna\StagHerd\Http\Requests\Payments;

use Equidna\StagHerd\Support\MoneyFormatter;

class RefundPaymentRequest extends PaymentFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => $this->normalizeNullableString($this->input('amount')),
            'reason' => $this->normalizeNullableString($this->input('reason')),
        ]);
    }

    public function refundAmount(): ?int
    {
        $amount = $this->validated('amount');

        if ($amount === null || $amount === '') {
            return null;
        }

        return MoneyFormatter::fromDecimal($amount);
    }

    public function refundReason(): string
    {
        return $this->validated('reason') ?: 'Refund from host UI';
    }
}
