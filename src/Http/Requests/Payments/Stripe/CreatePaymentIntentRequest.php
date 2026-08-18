<?php

namespace Equidna\StagHerd\Http\Requests\Payments\Stripe;

use Illuminate\Validation\Validator;

class CreatePaymentIntentRequest extends StripeFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_reference' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],

            'stripe' => ['nullable', 'array'],
            'stripe.customer' => ['nullable', 'string', 'max:255'],
            'stripe.payment_method' => ['nullable', 'string', 'max:255'],
            'stripe.capture_method' => ['nullable', 'string', 'max:50'],
            'stripe.statement_descriptor' => ['nullable', 'string', 'max:22'],
            'stripe.statement_descriptor_suffix' => ['nullable', 'string', 'max:22'],
            'stripe.setup_future_usage' => ['nullable', 'string', 'max:50'],
            'stripe.save_payment_method' => ['nullable', 'boolean'],
            'stripe.return_url' => ['nullable', 'url', 'max:500'],
            'stripe.metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $stripe = $this->input('stripe', []);

        if (!is_array($stripe)) {
            $stripe = [];
        }

        $stripe['customer'] = $this->normalizeNullableString($stripe['customer'] ?? null);
        $stripe['payment_method'] = $this->normalizeNullableString($stripe['payment_method'] ?? null);
        $stripe['capture_method'] = $this->normalizeNullableString($stripe['capture_method'] ?? null);
        $stripe['statement_descriptor'] = $this->normalizeNullableString($stripe['statement_descriptor'] ?? null);
        $stripe['statement_descriptor_suffix'] = $this->normalizeNullableString($stripe['statement_descriptor_suffix'] ?? null);
        $stripe['setup_future_usage'] = $this->normalizeNullableString($stripe['setup_future_usage'] ?? null);
        $stripe['return_url'] = $this->normalizeNullableString($stripe['return_url'] ?? null);

        $this->merge([
            'currency' => $this->normalizeUpper($this->input('currency')),
            'external_reference' => $this->normalizeNullableString($this->input('external_reference')),
            'payer_reference' => $this->normalizeNullableString($this->input('payer_reference')),
            'payer_email' => $this->normalizeNullableString($this->input('payer_email')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'idempotency_key' => $this->normalizeNullableString($this->input('idempotency_key')),
            'stripe' => $stripe,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->savePaymentMethod() && $this->payerReference() === null) {
                $validator->errors()->add(
                    'payer_reference',
                    'payer_reference is required when save_payment_method is enabled.',
                );
            }
        });
    }

    public function externalReference(): string
    {
        return $this->resolvedExternalReference(
            'STRIPE',
            $this->validated('external_reference'),
        );
    }

    public function idempotencyKey(): string
    {
        return $this->resolvedIdempotencyKey(
            $this->validated('idempotency_key'),
            'stag-herd-stripe-intent-',
        );
    }

    public function savePaymentMethod(): bool
    {
        $stripe = $this->validated('stripe') ?? [];
        $metadata = $this->validated('metadata') ?? [];

        return filter_var(
            $stripe['save_payment_method'] ?? data_get($metadata, 'save_payment_method', false),
            FILTER_VALIDATE_BOOL,
        );
    }

    public function customerId(): ?string
    {
        $stripe = $this->validated('stripe') ?? [];

        return $stripe['customer'] ?? null;
    }

    public function payerReference(): ?string
    {
        return $this->validated('payer_reference');
    }

    public function payerEmail(): ?string
    {
        return $this->validated('payer_email');
    }

    public function description(): string
    {
        return $this->validated('description') ?: 'Payment from Stripe Card Element';
    }

    public function intentMetadata(): array
    {
        $data = $this->validated();
        $metadata = $this->cleanMetadata($data['metadata'] ?? []);
        $stripe = $this->cleanMetadata(($data['stripe']['metadata'] ?? []));

        $intentMetadata = array_filter([
            'external_reference' => $this->externalReference(),
            'payer_reference' => $data['payer_reference'] ?? null,
            'source' => 'stag-herd-stripe-host-ui',
            'id_order' => data_get($metadata, 'id_order'),
            'id_client' => data_get($metadata, 'id_client'),
            'offer_id' => data_get($metadata, 'offer_id'),
            'checkout_type' => data_get($metadata, 'checkout_type'),
            'action' => data_get($metadata, 'action'),
            'save_payment_method' => $this->savePaymentMethod() ? 'true' : 'false',
        ], fn ($value) => $value !== null && $value !== '');

        return array_replace_recursive($intentMetadata, $stripe);
    }

    public function basePayload(?string $customerId = null): array
    {
        $data = $this->validated();
        $stripe = $data['stripe'] ?? [];

        $payload = [
            'amount' => \Equidna\StagHerd\Support\MoneyFormatter::fromDecimal($data['amount']),
            'currency' => strtolower($data['currency']),
            'payment_method_types' => ['card'],
            'description' => $this->description(),
            'receipt_email' => $data['payer_email'] ?? null,
            'metadata' => $this->intentMetadata(),
        ];

        if ($customerId) {
            $payload['customer'] = $customerId;
        }

        if (!empty($stripe['capture_method'])) {
            $payload['capture_method'] = $stripe['capture_method'];
        }

        if (!empty($stripe['statement_descriptor'])) {
            $payload['statement_descriptor'] = $stripe['statement_descriptor'];
        }

        if (!empty($stripe['statement_descriptor_suffix'])) {
            $payload['statement_descriptor_suffix'] = $stripe['statement_descriptor_suffix'];
        }

        if (!empty($stripe['setup_future_usage'])) {
            $payload['setup_future_usage'] = $stripe['setup_future_usage'];
        } elseif ($this->savePaymentMethod()) {
            $payload['setup_future_usage'] = 'off_session';
        }

        return array_filter(
            $payload,
            fn ($value) => $value !== null && $value !== '',
        );
    }
}
