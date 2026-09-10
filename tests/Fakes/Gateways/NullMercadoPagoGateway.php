<?php

namespace Equidna\StagHerd\Tests\Fakes\Gateways;

use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Data\MercadoPagoRequestContextData;

final class NullMercadoPagoGateway implements MercadoPagoGateway
{
    public function createPayment(
        array $payload,
        ?string $idempotencyKey = null,
        ?string $deviceId = null,
        ?MercadoPagoRequestContextData $context = null,
    ): array {
        return [];
    }

    public function getPayment(string $providerPaymentId): array
    {
        return [];
    }

    public function searchPayments(array $filters = []): array
    {
        return [];
    }

    public function cancelPayment(string $providerPaymentId): array
    {
        return [];
    }

    public function refundPayment(string $providerPaymentId, ?int $amount = null, ?string $idempotencyKey = null): array
    {
        return [];
    }
    public function createPreference(
        array $payload,
        ?MercadoPagoRequestContextData $context = null,
    ): array {
        return [];
    }

    public function createPreapprovalPlan(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getPreapprovalPlan(string $planId): array
    {
        return [];
    }

    public function createPreapproval(array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getPreapproval(string $subscriptionId): array
    {
        return [];
    }

    public function updatePreapproval(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array
    {
        return [];
    }

    public function getCustomerCards(string $customerId): array
    {
        return [];
    }

    public function deleteCustomerCard(string $customerId, string $cardId): array
    {
        return [];
    }
}
