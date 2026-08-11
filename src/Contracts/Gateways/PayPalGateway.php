<?php

namespace Equidna\StagHerd\Contracts\Gateways;

interface PayPalGateway
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOrder(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function getOrder(string $orderId): array;

    /**
     * @return array<string, mixed>
     */
    public function captureOrder(
        string $orderId,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function getCapture(string $captureId): array;

    /**
     * @return array<string, mixed>
     */
    public function refundCapture(
        string $captureId,
        ?int $amount = null,
        ?string $currency = null,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createCatalogProduct(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPlan(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createSubscription(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /** @return array<string, mixed> */
    public function getSubscription(string $subscriptionId): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function cancelSubscription(
        string $subscriptionId,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function getPaymentToken(string $paymentTokenId): array;

    /**
     * @return array<string, mixed>
     */
    public function deletePaymentToken(string $paymentTokenId): array;

    /**
     * Verifies a PayPal webhook signature against PayPal's API.
     *
     * @param array<string, mixed> $payload
     */
    public function verifyWebhookSignature(array $payload): bool;
}
