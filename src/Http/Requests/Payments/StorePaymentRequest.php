<?php

namespace Equidna\StagHerd\Http\Requests\Payments;

use Equidna\StagHerd\Data\PaymentRequestData;

class StorePaymentRequest extends PaymentFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string'],
            'method' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],

            'metadata' => ['nullable', 'array'],

            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_reference' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'return_url' => ['nullable', 'url', 'max:500'],
            'cancel_url' => ['nullable', 'url', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => $this->normalizeLower($this->input('provider')),
            'method' => $this->normalizeLower($this->input('method')),
            'currency' => $this->normalizeUpper($this->input('currency')),
            'external_reference' => $this->normalizeNullableString($this->input('external_reference')),
            'payer_reference' => $this->normalizeNullableString($this->input('payer_reference')),
            'payer_email' => $this->normalizeNullableString($this->input('payer_email')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'return_url' => $this->normalizeNullableString($this->input('return_url')),
            'cancel_url' => $this->normalizeNullableString($this->input('cancel_url')),
        ]);
    }

    public function toPaymentRequestData(): PaymentRequestData
    {
        $data = $this->validated();

        $provider = $data['provider'];
        $metadata = $this->cleanMetadata($data['metadata'] ?? []);

        $metadata = array_replace_recursive($metadata, [
            'source' => 'stag-herd-host-ui',
        ]);

        $externalReference = $data['external_reference']
            ?? strtoupper($provider) . '-' . now()->format('YmdHis');

        if ($data['external_reference'] ?? null) {
            $metadata['external_reference'] = $data['external_reference'];
        }

        return PaymentRequestData::fromDecimalAmount(
            amount: $data['amount'],
            currency: $data['currency'],
            method: $data['method'],
            provider: $provider,
            externalReference: $externalReference,
            payerReference: $data['payer_reference'] ?? null,
            payerEmail: $data['payer_email'] ?? null,
            description: $data['description'] ?? 'Payment created from host UI',
            returnUrl: $data['return_url'] ?? null,
            cancelUrl: $data['cancel_url'] ?? null,
            metadata: $metadata,
        );
    }
}
