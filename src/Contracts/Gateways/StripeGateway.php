<?php

namespace Equidna\StagHerd\Contracts\Gateways;

interface StripeGateway
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPaymentIntent(array $payload, ?string $idempotencyKey = null): array;

    /**
     * @return array<string, mixed>
     */
    public function getPaymentIntent(string $paymentIntentId): array;

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
    public function cancelPaymentIntent(string $paymentIntentId): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createRefund(array $payload, ?string $idempotencyKey = null): array;
}
