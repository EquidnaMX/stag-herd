<?php

namespace Equidna\StagHerd\Tests\Fakes\Gateways;

use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Data\PayPalRequestContextData;

final class NullPayPalGateway implements PayPalGateway
{
    public function createOrder(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function getOrder(
        string $orderId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function captureOrder(
        string $orderId,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function getCapture(
        string $captureId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function refundCapture(
        string $captureId,
        ?int $amount = null,
        ?string $currency = null,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function createCatalogProduct(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function createPlan(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function createSubscription(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function getSubscription(
        string $subscriptionId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function cancelSubscription(
        string $subscriptionId,
        array $payload = [],
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function getPaymentToken(
        string $paymentTokenId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function deletePaymentToken(
        string $paymentTokenId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function createPartnerReferral(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function getMerchantIntegration(
        string $partnerMerchantId,
        string $sellerMerchantId,
        ?PayPalRequestContextData $context = null,
    ): array {
        return [];
    }

    public function verifyWebhookSignature(
        array $payload,
        ?PayPalRequestContextData $context = null,
    ): bool {
        return false;
    }
}
