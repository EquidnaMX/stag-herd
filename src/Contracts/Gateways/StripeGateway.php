<?php

namespace Equidna\StagHerd\Contracts\Gateways;

interface StripeGateway
{
    public function createPaymentIntent(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    public function getPaymentIntent(
        string $paymentIntentId,
    ): array;

    public function confirmPaymentIntent(
        string $paymentIntentId,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): array;

    public function cancelPaymentIntent(
        string $paymentIntentId,
    ): array;

    public function createRefund(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    public function createCustomer(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    public function getCustomer(
        string $customerId,
    ): array;

    public function createSetupIntent(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    public function getSetupIntent(
        string $setupIntentId,
    ): array;

    public function getPaymentMethod(
        string $paymentMethodId,
    ): array;

    public function detachPaymentMethod(
        string $paymentMethodId,
    ): array;

    public function updateCustomer(
        string $customerId,
        array $payload,
    ): array;
}
