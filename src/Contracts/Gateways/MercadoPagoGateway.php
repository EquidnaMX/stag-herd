<?php

namespace Equidna\StagHerd\Contracts\Gateways;

interface MercadoPagoGateway
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPayment(
        array $payload,
        ?string $idempotencyKey = null,
        ?string $deviceId = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function getPayment(string $providerPaymentId): array;

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function searchPayments(array $filters = []): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(string $providerPaymentId): array;

    /**
     * @return array<string, mixed>
     */
    public function refundPayment(
        string $providerPaymentId,
        ?int $amount = null,
        ?string $idempotencyKey = null,
    ): array;
}
