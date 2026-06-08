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
}
