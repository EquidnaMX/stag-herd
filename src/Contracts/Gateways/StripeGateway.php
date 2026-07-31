<?php

namespace Equidna\StagHerd\Contracts\Gateways;

interface StripeGateway
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createCheckoutSession(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /** @return array<string, mixed> */
    public function getCheckoutSession(string $checkoutSessionId): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createProduct(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPrice(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /** @return array<string, mixed> */
    public function getSubscription(string $subscriptionId): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateSubscription(
        string $subscriptionId,
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /** @return array<string, mixed> */
    public function cancelSubscription(
        string $subscriptionId,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createBillingPortalSession(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPaymentIntent(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /** @return array<string, mixed> */
    public function getPaymentIntent(
        string $paymentIntentId,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function confirmPaymentIntent(
        string $paymentIntentId,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): array;

    /** @return array<string, mixed> */
    public function cancelPaymentIntent(
        string $paymentIntentId,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createRefund(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createCustomer(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /** @return array<string, mixed> */
    public function getCustomer(
        string $customerId,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createSetupIntent(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /** @return array<string, mixed> */
    public function getSetupIntent(
        string $setupIntentId,
    ): array;

    /** @return array<string, mixed> */
    public function getPaymentMethod(
        string $paymentMethodId,
    ): array;

    /** @return array<string, mixed> */
    public function detachPaymentMethod(
        string $paymentMethodId,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateCustomer(
        string $customerId,
        array $payload,
    ): array;

    /** @return array<string, mixed> */
    public function listCustomerPaymentMethods(
        string $customerId,
        string $type = 'card',
    ): array;
}
