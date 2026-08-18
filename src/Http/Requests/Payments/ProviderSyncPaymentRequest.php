<?php

namespace Equidna\StagHerd\Http\Requests\Payments;

use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;

class ProviderSyncPaymentRequest extends PaymentFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string'],
            'search_type' => ['required', 'string', 'in:provider_payment_id,provider_order_id'],
            'search_value' => ['required', 'string', 'max:255'],

            'method' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],

            'metadata' => ['nullable', 'array'],

            'external_reference' => ['nullable', 'string', 'max:255'],
            'payer_reference' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => $this->normalizeLower($this->input('provider')),
            'method' => $this->normalizeLower($this->input('method')),
            'currency' => $this->normalizeUpper($this->input('currency')),
            'search_type' => $this->normalizeNullableString($this->input('search_type')),
            'search_value' => $this->normalizeNullableString($this->input('search_value')),
            'external_reference' => $this->normalizeNullableString($this->input('external_reference')),
            'payer_reference' => $this->normalizeNullableString($this->input('payer_reference')),
            'payer_email' => $this->normalizeNullableString($this->input('payer_email')),
            'description' => $this->normalizeNullableString($this->input('description')),
        ]);
    }

    public function lookupData(): PaymentLookupData
    {
        $provider = $this->validated('provider');
        $searchType = $this->validated('search_type');
        $searchValue = $this->validated('search_value');

        return new PaymentLookupData(
            provider: $provider,
            providerPaymentId: $searchType === 'provider_payment_id' ? $searchValue : null,
            providerOrderId: $searchType === 'provider_order_id' ? $searchValue : null,
        );
    }

    public function fallbackRequestData(): PaymentRequestData
    {
        $data = $this->validated();

        $externalReference = $data['external_reference'] ?? null;
        $searchType = $data['search_type'];
        $searchValue = $data['search_value'];

        $metadata = $this->cleanMetadata($data['metadata'] ?? []);
        $metadata = array_replace_recursive($metadata, [
            'source' => 'stag-herd-provider-sync-host-ui',
            'sync_reference_type' => $searchType,
            'sync_reference_value' => $searchValue,
        ]);

        if ($externalReference) {
            $metadata['external_reference'] = $externalReference;
        }

        $metadata = $this->cleanMetadata($metadata);

        return PaymentRequestData::fromDecimalAmount(
            amount: $data['amount'],
            currency: $data['currency'],
            method: $data['method'],
            provider: $data['provider'],
            externalReference: $externalReference,
            payerReference: $data['payer_reference'] ?? null,
            payerEmail: $data['payer_email'] ?? null,
            description: $data['description'] ?: 'Payment synced from provider',
            metadata: $metadata,
        );
    }

    public function provider(): string
    {
        return $this->validated('provider');
    }

    public function searchType(): string
    {
        return $this->validated('search_type');
    }

    public function searchValue(): string
    {
        return $this->validated('search_value');
    }
}
