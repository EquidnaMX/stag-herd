<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe\Services;

use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Exceptions\ProviderCommunicationException;
use Illuminate\Support\Str;

final class StripeCustomerService
{
    public function __construct(
        private readonly StripeGateway $stripeGateway,
    ) {
        //
    }

    public function buildPayload(
        ?string $payerReference,
        ?string $payerEmail,
        ?string $payerName = null,
        string $source = 'stag-herd-stripe',
    ): array {
        return array_filter(
            [
                'email' => $payerEmail ?: null,
                'name' => $payerName ?: null,
                'metadata' => array_filter(
                    [
                        'payer_reference' => $payerReference ?: null,
                        'source' => $source,
                    ],
                    fn ($value) => $value !== null && $value !== '',
                ),
            ],
            fn ($value) => $value !== null && $value !== '' && $value !== [],
        );
    }

    public function ensureExists(
        ?string $customerId,
        array $customerPayload,
    ): ?string {
        $customerId = $this->normalizeCustomerId($customerId);

        if ($customerId === null) {
            $created = $this->create($customerPayload);

            return data_get($created, 'id');
        }

        try {
            $customer = $this->stripeGateway->getCustomer($customerId);

            return data_get($customer, 'id', $customerId);
        } catch (ProviderCommunicationException $exception) {
            if ((int) $exception->getCode() === 404) {
                $created = $this->create($customerPayload);

                return data_get($created, 'id');
            }

            throw $exception;
        }
    }

    private function normalizeCustomerId(?string $customerId): ?string
    {
        if (!is_string($customerId)) {
            return null;
        }

        $customerId = trim($customerId);

        if ($customerId === '') {
            return null;
        }

        if (strtolower($customerId) === 'null') {
            return null;
        }

        if (!str_starts_with($customerId, 'cus_')) {
            return null;
        }

        return $customerId;
    }

    private function create(array $payload): array
    {
        return $this->stripeGateway->createCustomer(
            payload: $payload,
            idempotencyKey: 'stag-herd-stripe-customer-' . (string) Str::uuid(),
        );
    }
}
