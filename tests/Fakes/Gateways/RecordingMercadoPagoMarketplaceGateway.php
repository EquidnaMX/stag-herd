<?php

namespace Equidna\StagHerd\Tests\Fakes\Gateways;

use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Data\MercadoPagoRequestContextData;
use RuntimeException;

final class RecordingMercadoPagoMarketplaceGateway implements MercadoPagoGateway
{
    /** @var array<string, mixed> */
    public array $lastCreatePaymentPayload = [];

    public ?MercadoPagoRequestContextData $lastCreatePaymentContext = null;

    /** @var array<string, mixed> */
    public array $lastCreatePreferencePayload = [];

    public ?MercadoPagoRequestContextData $lastCreatePreferenceContext = null;

    public function createPayment(
        array $payload,
        ?string $idempotencyKey = null,
        ?string $deviceId = null,
        ?MercadoPagoRequestContextData $context = null,
    ): array {
        $this->lastCreatePaymentPayload = $payload;
        $this->lastCreatePaymentContext = $context;

        return [
            'id' => 123,
            'status' => 'approved',
            'transaction_amount' => $payload['transaction_amount'],
            'currency_id' => 'MXN',
            'external_reference' => $payload['external_reference'] ?? null,
            'metadata' => $payload['metadata'] ?? [],
        ];
    }

    public function createPreference(
        array $payload,
        ?MercadoPagoRequestContextData $context = null,
    ): array {
        $this->lastCreatePreferencePayload = $payload;
        $this->lastCreatePreferenceContext = $context;

        return [
            'id' => 'PREF-123',
            'status' => 'created',
            'init_point' => 'https://www.mercadopago.com/init',
            'external_reference' => $payload['external_reference'] ?? null,
            'metadata' => $payload['metadata'] ?? [],
        ];
    }

    public function getPayment(string $providerPaymentId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function searchPayments(array $filters = []): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function cancelPayment(string $providerPaymentId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function refundPayment(string $providerPaymentId, ?int $amount = null, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createPreapprovalPlan(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getPreapprovalPlan(string $planId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createPreapproval(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getPreapproval(string $subscriptionId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function updatePreapproval(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getCustomerCards(string $customerId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function deleteCustomerCard(string $customerId, string $cardId): array
    {
        throw new RuntimeException('Not implemented.');
    }
}
