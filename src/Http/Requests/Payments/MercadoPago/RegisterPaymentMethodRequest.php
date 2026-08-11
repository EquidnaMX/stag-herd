<?php

namespace Equidna\StagHerd\Http\Requests\Payments\MercadoPago;

use Illuminate\Validation\Validator;

class RegisterPaymentMethodRequest extends MercadoPagoFormRequest
{
    public function rules(): array
    {
        return [
            'owner_reference' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'string', 'max:255'],
            'card_id' => ['nullable', 'string', 'max:255'],
            'credential_context' => ['nullable', 'string', 'max:255'],

            'mercado_pago' => ['nullable', 'array'],
            'mercado_pago.customer_id' => ['nullable', 'string', 'max:255'],
            'mercado_pago.card_id' => ['nullable', 'string', 'max:255'],
            'mercado_pago.card' => ['nullable', 'array'],
            'mercado_pago.card.id' => ['nullable', 'string', 'max:255'],
            'mercado_pago.card.customer_id' => ['nullable', 'string', 'max:255'],

            'card' => ['nullable', 'array'],
            'card.id' => ['nullable', 'string', 'max:255'],
            'card.customer_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $mercadoPago = $this->input('mercado_pago', []);
        $card = $this->input('card', []);

        if (! is_array($mercadoPago)) {
            $mercadoPago = [];
        }

        if (! is_array($card)) {
            $card = [];
        }

        $nestedCard = data_get($mercadoPago, 'card', []);

        if (! is_array($nestedCard)) {
            $nestedCard = [];
        }

        $this->merge([
            'owner_reference' => $this->normalizeNullableString($this->input('owner_reference')),
            'customer_id' => $this->normalizeNullableString($this->input('customer_id')),
            'card_id' => $this->normalizeNullableString($this->input('card_id')),
            'credential_context' => $this->normalizeNullableString($this->input('credential_context')),
            'mercado_pago' => array_replace($mercadoPago, [
                'customer_id' => $this->normalizeNullableString($mercadoPago['customer_id'] ?? null),
                'card_id' => $this->normalizeNullableString($mercadoPago['card_id'] ?? null),
                'card' => $nestedCard,
            ]),
            'card' => $card,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->customerId() === '') {
                $validator->errors()->add(
                    'customer_id',
                    'customer_id is required when mercado_pago.card.customer_id is not provided.'
                );
            }

            if ($this->cardId() === '') {
                $validator->errors()->add(
                    'card_id',
                    'card_id is required when mercado_pago.card.id is not provided.'
                );
            }
        });
    }

    public function ownerReference(): string
    {
        return (string) $this->validated('owner_reference');
    }

    public function customerId(): string
    {
        return (string) (
            data_get($this->validated('mercado_pago') ?? [], 'customer_id')
            ?? data_get($this->validated('mercado_pago') ?? [], 'card.customer_id')
            ?? $this->validated('customer_id')
            ?? data_get($this->validated('card') ?? [], 'customer_id')
            ?? ''
        );
    }

    public function cardId(): string
    {
        return (string) (
            data_get($this->validated('mercado_pago') ?? [], 'card_id')
            ?? data_get($this->validated('mercado_pago') ?? [], 'card.id')
            ?? $this->validated('card_id')
            ?? data_get($this->validated('card') ?? [], 'id')
            ?? ''
        );
    }

    public function credentialContext(): string
    {
        return (string) ($this->validated('credential_context') ?? 'default');
    }

    /**
     * @return array<string, mixed>
     */
    public function card(): array
    {
        $mercadoPagoCard = data_get($this->validated('mercado_pago') ?? [], 'card');

        if (is_array($mercadoPagoCard) && $mercadoPagoCard !== []) {
            return $this->cleanMetadata($mercadoPagoCard);
        }

        $card = $this->validated('card') ?? [];

        return is_array($card)
            ? $this->cleanMetadata($card)
            : [];
    }
}
