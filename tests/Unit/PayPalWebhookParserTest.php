<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Data\WebhookPayloadData;
use Equidna\StagHerd\Exceptions\InvalidWebhookSignatureException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\PayPalWebhookParser;
use Equidna\StagHerd\Data\PayPalRequestContextData;
use Equidna\StagHerd\Tests\TestCase;
use RuntimeException;

class PayPalWebhookParserTest extends TestCase
{
    public function test_it_verifies_and_normalizes_payment_capture_completed_webhook(): void
    {
        config()->set('stag-herd.providers.paypal.credentials.webhook_id', 'WH-123');

        $gateway = new SpyPayPalGateway(true);
        $parser = new PayPalWebhookParser($gateway);

        $webhook = $parser->parse($this->payload());

        $this->assertSame('paypal', $webhook->provider);
        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', $webhook->eventType);
        $this->assertSame('capture', $webhook->resourceType);
        $this->assertSame('CAPTURE-123', $webhook->resourceId);
        $this->assertSame('CAPTURE-123', $webhook->providerPaymentId);
        $this->assertSame('ORDER-456', $webhook->providerOrderId);
        $this->assertSame('WH-123', $gateway->lastVerificationPayload['webhook_id'] ?? null);
        $this->assertSame('PAYMENT.CAPTURE.COMPLETED', data_get($gateway->lastVerificationPayload, 'webhook_event.event_type'));
    }

    public function test_it_rejects_invalid_paypal_signatures(): void
    {
        config()->set('stag-herd.providers.paypal.credentials.webhook_id', 'WH-123');

        $parser = new PayPalWebhookParser(new SpyPayPalGateway(false));

        $this->expectException(InvalidWebhookSignatureException::class);

        $parser->parse($this->payload());
    }

    public function test_it_fails_clearly_for_unsupported_paypal_events(): void
    {
        config()->set('stag-herd.providers.paypal.credentials.webhook_id', 'WH-123');

        $parser = new PayPalWebhookParser(new SpyPayPalGateway(true));
        $payload = $this->payload(eventType: 'PAYMENT.CAPTURE.DENIED');

        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessage('PayPal webhook event [PAYMENT.CAPTURE.DENIED] is not supported.');

        $parser->parse($payload);
    }

    private function payload(string $eventType = 'PAYMENT.CAPTURE.COMPLETED'): WebhookPayloadData
    {
        return new WebhookPayloadData(
            provider: 'paypal',
            payload: [
                'id' => 'WH-EVENT-1',
                'event_type' => $eventType,
                'resource_type' => 'capture',
                'resource' => [
                    'id' => 'CAPTURE-123',
                    'supplementary_data' => [
                        'related_ids' => [
                            'order_id' => 'ORDER-456',
                        ],
                    ],
                ],
            ],
            headers: [
                'paypal-auth-algo' => ['SHA256withRSA'],
                'paypal-cert-url' => ['https://api-m.paypal.com/certs/cert.pem'],
                'paypal-transmission-id' => ['transmission-123'],
                'paypal-transmission-sig' => ['signature-123'],
                'paypal-transmission-time' => ['2026-06-24T12:00:00Z'],
            ],
            query: [],
            rawBody: '{}',
            ipAddress: '127.0.0.1',
        );
    }
}

final class SpyPayPalGateway implements PayPalGateway
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastVerificationPayload = null;

    public function __construct(
        private readonly bool $verificationResult,
    ) {
        //
    }

    public function createOrder(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function getOrder(
        string $orderId,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function captureOrder(
        string $orderId,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function getCapture(
        string $captureId,
        ?PayPalRequestContextData $context = null,
    ): array {
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

    public function getSubscription(
        string $subscriptionId,
        ?PayPalRequestContextData $context = null,
    ): array {
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

    public function getPaymentToken(
        string $paymentTokenId,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function deletePaymentToken(
        string $paymentTokenId,
        ?PayPalRequestContextData $context = null,
    ): array {
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

    public function verifyWebhookSignature(
        array $payload,
        ?PayPalRequestContextData $context = null,
    ): bool {
        $this->lastVerificationPayload = $payload;

        return $this->verificationResult;
    }
}
