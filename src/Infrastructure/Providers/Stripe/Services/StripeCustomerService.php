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
        if (! $customerId) {
            $customer = $this->create($customerPayload);

            return data_get($customer, 'id');
        }

        try {
            $customer = $this->stripeGateway->getCustomer($customerId);

            if (data_get($customer, 'deleted') === true) {
                $customer = $this->create($customerPayload);

                return data_get($customer, 'id');
            }

            $normalizedCustomerId = data_get($customer, 'id', $customerId);

            if (
                is_string($normalizedCustomerId)
                && $normalizedCustomerId !== ''
                && ! empty($customerPayload)
            ) {
                $this->stripeGateway->updateCustomer(
                    $normalizedCustomerId,
                    $customerPayload,
                );
            }

            return $normalizedCustomerId;
        } catch (ProviderCommunicationException $exception) {
            $status = data_get($exception->getErrors(), 'status');
            $code = data_get($exception->getErrors(), 'response.error.code');
            $param = data_get($exception->getErrors(), 'response.error.param');

            if ($status === 404 || ($status === 400 && $code === 'resource_missing' && $param === 'customer')) {
                $customer = $this->create($customerPayload);

                return data_get($customer, 'id');
            }

            throw $exception;
        }
    }

    private function create(array $payload): array
    {
        return $this->stripeGateway->createCustomer(
            payload: $payload,
            idempotencyKey: 'stag-herd-stripe-customer-' . (string) Str::uuid(),
        );
    }
}
