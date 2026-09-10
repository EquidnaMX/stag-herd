<?php

namespace Equidna\StagHerd\Tests\Fakes\Gateways;

use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Data\PayPalRequestContextData;
use RuntimeException;

final class RecordingPayPalPlatformFeeGateway implements PayPalGateway
{
    /** @var array<string, mixed>|null */
    public ?array $lastCreateOrderPayload = null;

    public function createOrder(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        $this->lastCreateOrderPayload = $payload;

        return [
            'id' => 'PAYPAL-ORDER-123',
            'status' => 'CREATED',
            'links' => [],
        ];
    }

    public function getOrder(string $orderId, ?PayPalRequestContextData $context = null): array
    {
        throw new RuntimeException('Not implemented.');
    }

    public function captureOrder(
        string $orderId,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function getCapture(string $captureId, ?PayPalRequestContextData $context = null): array
    {
        throw new RuntimeException('Not implemented.');
    }

    public function refundCapture(
        string $captureId,
        ?int $amount = null,
        ?string $currency = null,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function createCatalogProduct(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function createPlan(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function createSubscription(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function getSubscription(string $subscriptionId, ?PayPalRequestContextData $context = null): array
    {
        throw new RuntimeException('Not implemented.');
    }

    public function cancelSubscription(
        string $subscriptionId,
        array $payload = [],
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function getPaymentToken(string $paymentTokenId, ?PayPalRequestContextData $context = null): array
    {
        throw new RuntimeException('Not implemented.');
    }

    public function deletePaymentToken(string $paymentTokenId, ?PayPalRequestContextData $context = null): array
    {
        throw new RuntimeException('Not implemented.');
    }

    public function createPartnerReferral(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function getMerchantIntegration(
        string $partnerMerchantId,
        string $sellerMerchantId,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function verifyWebhookSignature(array $payload, ?PayPalRequestContextData $context = null): bool
    {
        throw new RuntimeException('Not implemented.');
    }
}
