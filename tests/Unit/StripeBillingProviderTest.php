<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Contracts\CredentialResolver;
use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Data\BillingLineItemData;
use Equidna\StagHerd\Data\CheckoutRequestData;
use Equidna\StagHerd\Data\SubscriptionCancellationData;
use Equidna\StagHerd\Domain\Enums\CheckoutMode;
use Equidna\StagHerd\Domain\Enums\CheckoutStatusEnum;
use Equidna\StagHerd\Domain\Enums\SubscriptionStatusEnum;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeApiAdapter;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeBillingProvider;
use Equidna\StagHerd\Support\CredentialContextManager;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;

final class StripeBillingProviderTest extends TestCase
{
    public function test_it_creates_a_hosted_subscription_checkout(): void
    {
        /** @var StripeGateway&MockInterface $gateway */
        $gateway = Mockery::mock(StripeGateway::class);
        /** @var Expectation $expectation */
        $expectation = $gateway->expects('createCheckoutSession');
        $expectation->once()
            ->withArgs(function (array $payload, ?string $idempotencyKey): bool {
                return $payload['mode'] === 'subscription'
                    && $payload['line_items'][0]['price'] === 'price_pro'
                    && $payload['client_reference_id'] === 'purchase-1'
                    && $idempotencyKey === 'checkout-purchase-1';
            })
            ->andReturn([
                'id' => 'cs_123',
                'object' => 'checkout.session',
                'mode' => 'subscription',
                'status' => 'open',
                'url' => 'https://checkout.stripe.com/c/pay/cs_123',
                'client_reference_id' => 'purchase-1',
                'expires_at' => 1_800_000_000,
            ]);

        $checkout = (new StripeBillingProvider($gateway))->createCheckout(new CheckoutRequestData(
            provider: 'stripe',
            mode: CheckoutMode::SUBSCRIPTION,
            credentialContext: 'payment-method-1',
            lineItems: [new BillingLineItemData('price_pro')],
            successUrl: 'https://portal.test/success',
            cancelUrl: 'https://portal.test/cancel',
            externalReference: 'purchase-1',
            metadata: ['purchase_uuid' => 'purchase-1'],
            idempotencyKey: 'checkout-purchase-1',
        ));

        $this->assertSame('cs_123', $checkout->id);
        $this->assertSame(CheckoutMode::SUBSCRIPTION, $checkout->mode);
        $this->assertSame(CheckoutStatusEnum::OPEN, $checkout->status);
        $this->assertSame('payment-method-1', $checkout->credentialContext);
    }

    public function test_it_schedules_subscription_cancellation_at_period_end(): void
    {
        /** @var StripeGateway&MockInterface $gateway */
        $gateway = Mockery::mock(StripeGateway::class);
        /** @var Expectation $expectation */
        $expectation = $gateway->expects('updateSubscription');
        $expectation->once()
            ->with('sub_123', ['cancel_at_period_end' => 'true'], 'cancel-sub-123')
            ->andReturn([
                'id' => 'sub_123',
                'status' => 'active',
                'cancel_at_period_end' => true,
                'customer' => 'cus_123',
                'items' => ['data' => [[
                    'price' => ['id' => 'price_pro'],
                    'current_period_start' => 1_700_000_000,
                    'current_period_end' => 1_702_592_000,
                ]]],
            ]);

        $subscription = (new StripeBillingProvider($gateway))->cancelSubscription(
            new SubscriptionCancellationData(
                provider: 'stripe',
                credentialContext: 'payment-method-1',
                subscriptionId: 'sub_123',
                atPeriodEnd: true,
                idempotencyKey: 'cancel-sub-123',
            ),
        );

        $this->assertSame(SubscriptionStatusEnum::ACTIVE, $subscription->status);
        $this->assertTrue($subscription->cancelAtPeriodEnd);
        $this->assertSame('price_pro', $subscription->priceReference);
    }

    public function test_the_stripe_adapter_sends_the_pinned_api_version(): void
    {
        config()->set('stag-herd.providers.stripe.credentials.secret_key', 'sk_test_only');
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_123',
            ]),
        ]);

        (new StripeApiAdapter())->createCheckoutSession([
            'mode' => 'payment',
        ], 'checkout-1');

        Http::assertSent(fn ($request): bool => $request->hasHeader(
            'Stripe-Version',
            '2026-02-25.clover',
        ) && $request->hasHeader('Idempotency-Key', 'checkout-1'));
    }

    public function test_credential_context_is_request_scoped_and_restored(): void
    {
        config()->set('stag-herd.providers.stripe.credentials', ['secret_key' => 'base']);
        $resolver = new class () implements CredentialResolver {
            public function resolve(string $provider, string $credentialContext): array
            {
                return ['secret_key' => $provider.'-'.$credentialContext];
            }
        };

        $manager = new CredentialContextManager($resolver);
        $during = $manager->run(
            'stripe',
            'tenant-a',
            fn (): string => (string) config('stag-herd.providers.stripe.credentials.secret_key'),
        );

        $this->assertSame('stripe-tenant-a', $during);
        $this->assertSame('base', config('stag-herd.providers.stripe.credentials.secret_key'));
    }
}
