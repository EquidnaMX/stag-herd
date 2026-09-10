<?php

namespace Equidna\StagHerd\Tests\Fakes\Gateways;

use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use RuntimeException;

final class RecordingStripeConnectGateway implements StripeGateway
{
    /** @var array<string, mixed>|null */
    public ?array $lastCreatePaymentIntentPayload = null;

    public function createPaymentIntent(array $payload, ?string $idempotencyKey = null): array
    {
        $this->lastCreatePaymentIntentPayload = $payload;

        return [
            'id' => 'pi_123',
            'status' => 'requires_payment_method',
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
            'client_secret' => 'pi_123_secret_abc',
            'metadata' => $payload['metadata'] ?? [],
        ];
    }

    public function createCheckoutSession(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getCheckoutSession(string $checkoutSessionId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createProduct(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createPrice(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getSubscription(string $subscriptionId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function updateSubscription(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function cancelSubscription(string $subscriptionId, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createBillingPortalSession(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getPaymentIntent(string $paymentIntentId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function confirmPaymentIntent(string $paymentIntentId, array $payload = [], ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function cancelPaymentIntent(string $paymentIntentId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createRefund(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createCustomer(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getCustomer(string $customerId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createSetupIntent(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getSetupIntent(string $setupIntentId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getPaymentMethod(string $paymentMethodId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function detachPaymentMethod(string $paymentMethodId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function updateCustomer(string $customerId, array $payload): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function listCustomerPaymentMethods(string $customerId, string $type = 'card'): array
    {
        throw new RuntimeException('Not implemented.');
    }
}
