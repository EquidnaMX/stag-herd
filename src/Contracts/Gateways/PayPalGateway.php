<?php

namespace Equidna\StagHerd\Contracts\Gateways;

use Equidna\StagHerd\Data\PayPalRequestContextData;

interface PayPalGateway
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOrder(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array;

    /** @return array<string, mixed> */
    public function getOrder(
        string $orderId,
        ?PayPalRequestContextData $context = null,
    ): array;

    /** @return array<string, mixed> */
    public function captureOrder(
        string $orderId,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array;

    /** @return array<string, mixed> */
    public function getCapture(
        string $captureId,
        ?PayPalRequestContextData $context = null,
    ): array;

    /** @return array<string, mixed> */
    public function refundCapture(
        string $captureId,
        ?int $amount = null,
        ?string $currency = null,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createCatalogProduct(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPlan(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createSubscription(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array;

    /** @return array<string, mixed> */
    public function getSubscription(
        string $subscriptionId,
        ?PayPalRequestContextData $context = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function cancelSubscription(
        string $subscriptionId,
        array $payload = [],
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array;

    /** @return array<string, mixed> */
    public function getPaymentToken(
        string $paymentTokenId,
        ?PayPalRequestContextData $context = null,
    ): array;

    /** @return array<string, mixed> */
    public function deletePaymentToken(
        string $paymentTokenId,
        ?PayPalRequestContextData $context = null,
    ): array;

    /**
     * Verifies a PayPal webhook signature against PayPal's API.
     *
     * @param array<string, mixed> $payload
     */
    public function verifyWebhookSignature(
        array $payload,
        ?PayPalRequestContextData $context = null,
    ): bool;
}
