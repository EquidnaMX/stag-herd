<?php

namespace Equidna\StagHerd\Contracts\Gateways;

interface StripeGateway
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPaymentIntent(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createSetupIntent(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function getSetupIntent(
        string $setupIntentId,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function getPaymentMethod(
        string $paymentMethodId,
    ): array;
}