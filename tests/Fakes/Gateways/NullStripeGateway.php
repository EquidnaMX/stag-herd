<?php

namespace Equidna\StagHerd\Tests\Fakes\Gateways;

use Equidna\StagHerd\Contracts\Gateways\StripeGateway;

final class NullStripeGateway implements StripeGateway
{
    public function createCheckoutSession(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getCheckoutSession(string $checkoutSessionId): array
    {
        return [];
    }

    public function createProduct(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function createPrice(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getSubscription(string $subscriptionId): array
    {
        return [];
    }

    public function updateSubscription(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function cancelSubscription(string $subscriptionId, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function createBillingPortalSession(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function createPaymentIntent(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getPaymentIntent(string $paymentIntentId): array
    {
        return [];
    }

    public function confirmPaymentIntent(string $paymentIntentId, array $payload = [], ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function cancelPaymentIntent(string $paymentIntentId): array
    {
        return [];
    }

    public function createRefund(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function createCustomer(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getCustomer(string $customerId): array
    {
        return [];
    }

    public function createSetupIntent(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getSetupIntent(string $setupIntentId): array
    {
        return [];
    }

    public function getPaymentMethod(string $paymentMethodId): array
    {
        return [];
    }

    public function detachPaymentMethod(string $paymentMethodId): array
    {
        return [];
    }

    public function updateCustomer(string $customerId, array $payload): array
    {
        return [];
    }

    public function listCustomerPaymentMethods(string $customerId, string $type = 'card'): array
    {
        return [];
    }
}
