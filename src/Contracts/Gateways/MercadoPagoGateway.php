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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPreference(array $payload): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPreapprovalPlan(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function getPreapprovalPlan(string $planId): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPreapproval(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function getPreapproval(string $subscriptionId): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updatePreapproval(
        string $subscriptionId,
        array $payload,
        ?string $idempotencyKey = null,
    ): array;
}
