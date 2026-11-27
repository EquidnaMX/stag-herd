<?php

namespace Equidna\StagHerd\Contracts\Gateways;

use Equidna\StagHerd\Data\MercadoPagoRequestContextData;

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
        ?MercadoPagoRequestContextData $context = null,
    ): array;

    /** @return array<string, mixed> */
    public function getPayment(string $providerPaymentId): array;

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function searchPayments(array $filters = []): array;

    /** @return array<string, mixed> */
    public function cancelPayment(string $providerPaymentId): array;

    /** @return array<string, mixed> */
    public function refundPayment(
        string $providerPaymentId,
        ?int $amount = null,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPreference(
        array $payload,
        ?MercadoPagoRequestContextData $context = null,
    ): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPreapprovalPlan(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /** @return array<string, mixed> */
    public function getPreapprovalPlan(string $planId): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPreapproval(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /** @return array<string, mixed> */
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

    /** @return array<int, array<string, mixed>> */
    public function getCustomerCards(string $customerId): array;

    /** @return array<string, mixed> */
    public function deleteCustomerCard(string $customerId, string $cardId): array;
}
