<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PlatformPaymentContextData;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\Services\StripeCardPaymentService;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeResultMapper;
use Equidna\StagHerd\Tests\TestCase;
use RuntimeException;

final class StripeConnectPaymentTest extends TestCase
{
    public function test_card_payment_intent_adds_stripe_connect_destination_charge_fields(): void
    {
        $gateway = new RecordingStripeConnectGateway();

        $service = new StripeCardPaymentService(
            gateway: $gateway,
            mapper: new StripeResultMapper(),
        );

        $service->createPayment(
            request: new PaymentRequestData(
                amount: 10000,
                currency: 'MXN',
                method: 'card',
                provider: 'stripe',
                externalReference: 'ORDER-123',
                platformContext: new PlatformPaymentContextData(
                    sellerReference: 'acct_seller_123',
                    platformFeeAmount: 1500,
                    providerMetadata: [
                        'stripe' => [
                            'on_behalf_of' => 'acct_seller_123',
                        ],
                    ],
                ),
            ),
            method: 'card',
        );

        $this->assertSame(1500, $gateway->lastCreatePaymentIntentPayload['application_fee_amount']);
        $this->assertSame('acct_seller_123', data_get($gateway->lastCreatePaymentIntentPayload, 'transfer_data.destination'));
        $this->assertSame('acct_seller_123', $gateway->lastCreatePaymentIntentPayload['on_behalf_of']);
    }
}

final class RecordingStripeConnectGateway implements StripeGateway
{
    /** @var array<string, mixed>|null */
    public ?array $lastCreatePaymentIntentPayload = null;

    public function createPaymentIntent(array $payload, ?string $idempotencyKey = null): array
    {
        $this->lastCreatePaymentIntentPayload = $payload;

        return [
            'id' => 'pi_123',
            'status' => 'requires_payment_method',
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
            'client_secret' => 'pi_123_secret_abc',
            'metadata' => $payload['metadata'] ?? [],
        ];
    }

    public function createCheckoutSession(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getCheckoutSession(string $checkoutSessionId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createProduct(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createPrice(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getSubscription(string $subscriptionId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function updateSubscription(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function cancelSubscription(string $subscriptionId, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createBillingPortalSession(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getPaymentIntent(string $paymentIntentId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function confirmPaymentIntent(string $paymentIntentId, array $payload = [], ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function cancelPaymentIntent(string $paymentIntentId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createRefund(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createCustomer(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getCustomer(string $customerId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createSetupIntent(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getSetupIntent(string $setupIntentId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getPaymentMethod(string $paymentMethodId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function detachPaymentMethod(string $paymentMethodId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function updateCustomer(string $customerId, array $payload): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function listCustomerPaymentMethods(string $customerId, string $type = 'card'): array
    {
        throw new RuntimeException('Not implemented.');
    }
}
