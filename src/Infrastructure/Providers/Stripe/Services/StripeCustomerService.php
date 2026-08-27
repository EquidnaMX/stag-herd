<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe\Services;

use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Exceptions\ProviderCommunicationException;

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
                    fn($value) => $value !== null && $value !== '',
                ),
            ],
            fn($value) => $value !== null && $value !== '' && $value !== [],
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
        $payerReference = trim((string) data_get($payload, 'metadata.payer_reference', ''));
        $email = strtolower(trim((string) data_get($payload, 'email', '')));

        $dedupeSource = $payerReference !== ''
            ? 'payer_reference:' . $payerReference
            : 'email:' . $email;

        if ($dedupeSource === 'email:') {
            $dedupeSource = 'payload:' . hash(
                'sha256',
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
            );
        }

        return $this->stripeGateway->createCustomer(
            payload: $payload,
            idempotencyKey: 'stag-herd-stripe-customer-' . hash('sha256', $dedupeSource),
        );
    }
}
