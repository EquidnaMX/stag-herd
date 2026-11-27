<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Application\PayPalSellerOnboardingService;
use Equidna\StagHerd\Support\CredentialContextManager;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\PayPalSellerRepository;
use Equidna\StagHerd\Data\PayPalSellerOnboardingData;
use Equidna\StagHerd\Data\PayPalRequestContextData;
use Equidna\StagHerd\Events\PayPalSellerOnboarded;
use Equidna\StagHerd\Contracts\CredentialResolver;
use Equidna\StagHerd\Data\PayPalSellerData;
use Illuminate\Support\Facades\Event;
use Equidna\StagHerd\Tests\TestCase;
use InvalidArgumentException;

final class PayPalSellerOnboardingServiceTest extends TestCase
{
    public function test_complete_verifies_merchant_integration_saves_seller_and_dispatches_event(): void
    {
        Event::fake([PayPalSellerOnboarded::class]);

        $gateway = new RecordingPayPalMerchantIntegrationGateway();
        $repository = new RecordingPayPalSellerRepository();

        $service = new PayPalSellerOnboardingService(
            gateway: $gateway,
            sellers: $repository,
            credentials: new CredentialContextManager(new StaticPayPalCredentialResolver()),
        );

        $seller = $service->complete(
            sellerMerchantId: 'SELLER-123',
            partnerMerchantId: 'PARTNER-123',
            trackingId: 'TRACK-123',
            ownerReference: 'OWNER-123',
            context: new PayPalRequestContextData(
                credentialContext: 'payment-method-123',
                platformAttributionId: 'BN-CODE',
            ),
            query: [
                'merchantId' => 'SELLER-123',
            ],
        );

        $this->assertSame('PARTNER-123', $gateway->partnerMerchantId);
        $this->assertSame('SELLER-123', $gateway->sellerMerchantId);
        $this->assertSame('payment-method-123', $gateway->context?->credentialContext);

        $this->assertInstanceOf(PayPalSellerOnboardingData::class, $repository->saved);
        $this->assertSame('SELLER-123', $repository->saved->sellerMerchantId);
        $this->assertSame('TRACK-123', $repository->saved->trackingId);
        $this->assertSame('OWNER-123', $repository->saved->ownerReference);
        $this->assertSame('BUSINESS_ACCOUNT', $repository->saved->accountStatus);
        $this->assertSame('GRANTED', $repository->saved->consentStatus);
        $this->assertSame(['PAYMENT', 'REFUND'], $repository->saved->permissions);
        $this->assertSame(['PPCP'], $repository->saved->capabilities);

        $this->assertSame('SELLER-123', $seller->sellerMerchantId);

        Event::assertDispatched(
            PayPalSellerOnboarded::class,
            fn(PayPalSellerOnboarded $event): bool =>
            $event->seller->sellerMerchantId === 'SELLER-123'
                && $event->onboarding->trackingId === 'TRACK-123',
        );
    }

    public function test_complete_requires_seller_merchant_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing PayPal seller merchant id.');

        $service = new PayPalSellerOnboardingService(
            gateway: new RecordingPayPalMerchantIntegrationGateway(),
            sellers: new RecordingPayPalSellerRepository(),
            credentials: new CredentialContextManager(new StaticPayPalCredentialResolver()),
        );

        $service->complete(
            sellerMerchantId: '',
            partnerMerchantId: 'PARTNER-123',
        );
    }

    public function test_complete_uses_configured_partner_merchant_id_when_not_passed(): void
    {
        config()->set('stag-herd.providers.paypal.credentials.partner_merchant_id', 'CONFIG-PARTNER-123');

        $gateway = new RecordingPayPalMerchantIntegrationGateway();

        $service = new PayPalSellerOnboardingService(
            gateway: $gateway,
            sellers: new RecordingPayPalSellerRepository(),
            credentials: new CredentialContextManager(new StaticPayPalCredentialResolver()),
        );

        $service->complete(
            sellerMerchantId: 'SELLER-123',
            partnerMerchantId: null,
        );

        $this->assertSame('CONFIG-PARTNER-123', $gateway->partnerMerchantId);
    }
}

final class RecordingPayPalSellerRepository implements PayPalSellerRepository
{
    public ?PayPalSellerOnboardingData $saved = null;

    public function saveOnboardingResult(PayPalSellerOnboardingData $data): PayPalSellerData
    {
        $this->saved = $data;

        return new PayPalSellerData(
            sellerMerchantId: $data->sellerMerchantId,
            trackingId: $data->trackingId,
            ownerReference: $data->ownerReference,
            accountStatus: $data->accountStatus,
            consentStatus: $data->consentStatus,
            permissions: $data->permissions,
            capabilities: $data->capabilities,
            raw: $data->raw,
        );
    }

    public function findByTrackingId(string $trackingId): ?PayPalSellerData
    {
        return null;
    }

    public function findSellerMerchantIdForOwner(string $ownerReference): ?string
    {
        return null;
    }
}

final class StaticPayPalCredentialResolver implements CredentialResolver
{
    /** @return array<string, mixed> */
    public function resolve(string $provider, string $credentialContext): array
    {
        return [
            'client_id' => 'client-id',
            'secret' => 'client-secret',
        ];
    }
}

final class RecordingPayPalMerchantIntegrationGateway implements PayPalGateway
{
    public ?string $partnerMerchantId = null;

    public ?string $sellerMerchantId = null;

    public ?PayPalRequestContextData $context = null;

    /** @return array<string, mixed> */
    public function getMerchantIntegration(
        string $partnerMerchantId,
        string $sellerMerchantId,
        ?PayPalRequestContextData $context = null,
    ): array {
        $this->partnerMerchantId = $partnerMerchantId;
        $this->sellerMerchantId = $sellerMerchantId;
        $this->context = $context;

        return [
            'account_status' => 'BUSINESS_ACCOUNT',
            'consent_status' => 'GRANTED',
            'permissions' => [
                'PAYMENT',
                'REFUND',
            ],
            'capabilities' => [
                'PPCP',
            ],
        ];
    }

    public function createOrder(array $payload, ?string $idempotencyKey = null, ?PayPalRequestContextData $context = null): array
    {
        return [];
    }

    public function getOrder(string $orderId, ?PayPalRequestContextData $context = null): array
    {
        return [];
    }

    public function captureOrder(string $orderId, ?string $idempotencyKey = null, ?PayPalRequestContextData $context = null): array
    {
        return [];
    }

    public function getCapture(string $captureId, ?PayPalRequestContextData $context = null): array
    {
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

    public function createCatalogProduct(array $payload, ?string $idempotencyKey = null, ?PayPalRequestContextData $context = null): array
    {
        return [];
    }

    public function createPlan(array $payload, ?string $idempotencyKey = null, ?PayPalRequestContextData $context = null): array
    {
        return [];
    }

    public function createSubscription(array $payload, ?string $idempotencyKey = null, ?PayPalRequestContextData $context = null): array
    {
        return [];
    }

    public function getSubscription(string $subscriptionId, ?PayPalRequestContextData $context = null): array
    {
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

    public function getPaymentToken(string $paymentTokenId, ?PayPalRequestContextData $context = null): array
    {
        return [];
    }

    public function deletePaymentToken(string $paymentTokenId, ?PayPalRequestContextData $context = null): array
    {
        return [];
    }

    public function createPartnerReferral(array $payload, ?string $idempotencyKey = null, ?PayPalRequestContextData $context = null): array
    {
        return [];
    }

    public function verifyWebhookSignature(array $payload, ?PayPalRequestContextData $context = null): bool
    {
        return true;
    }
}
