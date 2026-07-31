<?php

namespace Equidna\StagHerd\Http\Requests\Payments\Stripe;

use Equidna\StagHerd\Data\PaymentRequestData;

class ProcessSpeiPaymentRequest extends StripeFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3', 'in:MXN'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_reference' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'string', 'max:255', 'regex:/^cus_/'],
            'return_url' => ['nullable', 'url', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'stripe_customer_id' => ['nullable', 'string', 'max:255', 'regex:/^cus_/'],
            'id_client' => ['nullable'],
            'id_order' => ['nullable'],
            'offer_id' => ['nullable'],
            'payment_method' => ['nullable', 'string'],
            'method_data' => ['nullable', 'array'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.stripe_customer_id' => ['nullable', 'string', 'max:255', 'regex:/^cus_/'],
            'stripe' => ['nullable', 'array'],
            'stripe.customer' => ['nullable', 'string', 'max:255', 'regex:/^cus_/'],
            'stripe.return_url' => ['nullable', 'url', 'max:500'],
            'stripe.idempotency_key' => ['nullable', 'string', 'max:255'],
            'stripe.metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $currency = $this->input('currency');
        $customerId = $this->input('customer_id')
            ?: $this->input('stripe_customer_id')
            ?: $this->input('customer.stripe_customer_id')
            ?: $this->input('stripe.customer');

        $payerEmail = $this->input('payer_email')
            ?: $this->input('customer.email');

        $payerReference = $this->input('payer_reference')
            ?: $this->input('id_client')
            ?: data_get($this->input('metadata', []), 'id_client');

        $returnUrl = $this->input('return_url')
            ?: $this->input('stripe.return_url');

        $idempotencyKey = $this->input('idempotency_key')
            ?: $this->input('stripe.idempotency_key');

        if ($currency === null || trim((string) $currency) === '') {
            $currency = 'MXN';
        }

        $this->merge([
            'currency' => $this->normalizeUpper($currency),
            'external_reference' => $this->normalizeNullableString($this->input('external_reference')),
            'payer_reference' => $this->normalizeNullableString($payerReference),
            'payer_email' => $this->normalizeNullableString($payerEmail),
            'description' => $this->normalizeNullableString($this->input('description')),
            'customer_id' => $this->normalizeNullableString($customerId),
            'return_url' => $this->normalizeNullableString($returnUrl),
            'idempotency_key' => $this->normalizeNullableString($idempotencyKey),
        ]);
    }

    public function toPaymentRequestData(): PaymentRequestData
    {
        $data = $this->validated();

        $externalReference = $this->resolvedExternalReference(
            'STRIPE-SPEI',
            $data['external_reference'] ?? null,
        );

        $idempotencyKey = $this->resolvedIdempotencyKey(
            $data['idempotency_key'] ?? null,
            'stag-herd-stripe-spei-',
        );

        $baseMetadata = $this->cleanMetadata($data['metadata'] ?? []);
        $stripeMetadata = $this->cleanMetadata($data['stripe']['metadata'] ?? []);

        $orderId = $data['id_order'] ?? data_get($baseMetadata, 'id_order');
        $clientId = $data['id_client'] ?? data_get($baseMetadata, 'id_client');
        $offerId = $data['offer_id'] ?? data_get($baseMetadata, 'offer_id');

        $metadata = array_replace_recursive(
            $baseMetadata,
            [
                'id_order' => $orderId ? (string) $orderId : null,
                'id_client' => $clientId ? (string) $clientId : null,
                'offer_id' => $offerId ? (string) $offerId : null,
                'source' => 'stag-herd-stripe-spei',
                'external_reference' => $externalReference,
                'payment_method_family' => 'spei',
                'payment_flow_submode' => 'spei',
                'stripe_customer_id' => $data['customer_id'] ?? null,
                'stripe' => array_filter([
                    'customer' => $data['customer_id'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                    'return_url' => $data['return_url'] ?? null,
                    'metadata' => array_replace_recursive(
                        [
                            'payment_method_family' => 'spei',
                            'bank_transfer_type' => 'mx_bank_transfer',
                            'source' => 'stag-herd-stripe-spei',
                        ],
                        $stripeMetadata,
                    ),
                ], fn($value) => $value !== null && $value !== '' && $value !== []),
            ],
        );

        return PaymentRequestData::fromDecimalAmount(
            amount: $data['amount'],
            currency: $data['currency'],
            method: 'spei',
            provider: 'stripe',
            externalReference: $externalReference,
            payerReference: $data['payer_reference'] ?? null,
            payerEmail: $data['payer_email'] ?? null,
            description: $data['description'] ?? 'Payment with Stripe SPEI',
            returnUrl: $data['return_url'] ?? null,
            metadata: $this->cleanMetadata($metadata),
        );
    }
}
