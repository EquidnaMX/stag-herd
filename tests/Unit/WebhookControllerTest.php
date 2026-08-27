<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Application\Actions\LookupPayment;
use Equidna\StagHerd\Application\Actions\ProcessPaymentWebhook;
use Equidna\StagHerd\Contracts\PaymentProvider;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\PayPalRequestContextData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Data\WebhookPayloadData;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Support\ProviderRegistry;
use Equidna\StagHerd\Tests\TestCase;
use RuntimeException;

class WebhookControllerTest extends TestCase
{
    public function test_it_returns_401_for_invalid_paypal_signatures(): void
    {
        $this->registerPaypalWebhookAction(new ControllerSpyPayPalGateway(false));

        $response = $this->postJson('/stag-herd/webhooks/paypal', $this->requestPayload(), $this->requestHeaders());

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid webhook signature.',
                'provider' => 'paypal',
            ]);
    }

    public function test_it_returns_400_for_unsupported_paypal_events(): void
    {
        $this->registerPaypalWebhookAction(new ControllerSpyPayPalGateway(true));

        $payload = $this->requestPayload();
        $payload['event_type'] = 'PAYMENT.CAPTURE.DENIED';

        $response = $this->postJson('/stag-herd/webhooks/paypal', $payload, $this->requestHeaders());

        $response->assertStatus(400);
        $this->assertStringContainsString(
            'PayPal webhook event [PAYMENT.CAPTURE.DENIED] is not supported.',
            (string) $response->json('message'),
        );
    }

    private function registerPaypalWebhookAction(ControllerSpyPayPalGateway $gateway): void
    {
        config()->set('stag-herd.providers.paypal.credentials.webhook_id', 'WH-123');

        $registry = new ProviderRegistry();
        $registry->register(new FakePaypalProvider());

        $this->app->instance(ProviderRegistry::class, $registry);
        $this->app->instance(PaymentRepository::class, new NullWebhookControllerPaymentRepository());
        $this->app->instance(\Equidna\StagHerd\Contracts\Gateways\PayPalGateway::class, $gateway);
        $this->app->instance(ProcessPaymentWebhook::class, new ProcessPaymentWebhook(
            lookupPayment: new LookupPayment($registry, new NullWebhookControllerPaymentRepository()),
            idempotency: new ControllerWebhookIdempotencyStore(),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        return [
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-CERT-URL' => 'https://api-m.paypal.com/certs/cert.pem',
            'PAYPAL-TRANSMISSION-ID' => 'transmission-123',
            'PAYPAL-TRANSMISSION-SIG' => 'signature-123',
            'PAYPAL-TRANSMISSION-TIME' => '2026-06-24T12:00:00Z',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(): array
    {
        return [
            'id' => 'WH-EVENT-1',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource_type' => 'capture',
            'resource' => [
                'id' => 'CAPTURE-123',
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => 'ORDER-456',
                    ],
                ],
            ],
        ];
    }
}

final class FakePaypalProvider implements PaymentProvider
{
    public function getName(): string
    {
        return 'paypal';
    }

    public function getMethods(): array
    {
        return ['paypal'];
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        throw UnsupportedOperationException::forOperation('lookup', 'Not implemented.');
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function refundPayment(RefundRequestData $request): PaymentResultData
    {
        throw new RuntimeException('Not implemented.');
    }
}

final class ControllerSpyPayPalGateway implements \Equidna\StagHerd\Contracts\Gateways\PayPalGateway
{
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
        return $this->verificationResult;
    }
}

final class ControllerWebhookIdempotencyStore implements \Equidna\StagHerd\Contracts\WebhookIdempotencyStore
{
    public function claim(string $key, int $ttlSeconds): bool
    {
        return true;
    }

    public function markProcessed(string $key, int $ttlSeconds): void
    {
        //
    }

    public function releaseIfProcessing(string $key): void
    {
        //
    }
}

final class NullWebhookControllerPaymentRepository implements PaymentRepository
{
    public function storeFromResult(PaymentRequestData $request, PaymentResultData $result): \Equidna\StagHerd\Domain\Payment
    {
        throw new RuntimeException('Not implemented.');
    }

    public function find(int|string $id): ?\Equidna\StagHerd\Domain\Payment
    {
        return null;
    }

    public function findByProviderPaymentId(string $provider, string $providerPaymentId): ?\Equidna\StagHerd\Domain\Payment
    {
        return null;
    }

    public function findByProviderOrderId(string $provider, string $providerOrderId): ?\Equidna\StagHerd\Domain\Payment
    {
        return null;
    }

    public function findByExternalReference(string $externalReference): ?\Equidna\StagHerd\Domain\Payment
    {
        return null;
    }

    public function updateFromResult(\Equidna\StagHerd\Domain\Payment $payment, PaymentResultData $result): \Equidna\StagHerd\Domain\Payment
    {
        throw new RuntimeException('Not implemented.');
    }
}
