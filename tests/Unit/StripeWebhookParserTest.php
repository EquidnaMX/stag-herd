<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Data\WebhookPayloadData;
use Equidna\StagHerd\Exceptions\InvalidWebhookSignatureException;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeWebhookParser;
use Equidna\StagHerd\Tests\TestCase;

final class StripeWebhookParserTest extends TestCase
{
    public function test_it_normalizes_checkout_payment_state_and_real_event_identity(): void
    {
        config()->set('stag-herd.providers.stripe.credentials.webhook_secret', 'whsec_test');
        config()->set('stag-herd.providers.stripe.webhooks.tolerance_seconds', 300);
        $timestamp = time();
        $rawBody = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawBody, 'whsec_test');

        $webhook = (new StripeWebhookParser())->parse(new WebhookPayloadData(
            provider: 'stripe',
            payload: $this->payload(),
            headers: ['stripe-signature' => "t={$timestamp},v1={$signature}"],
            query: [],
            rawBody: $rawBody,
            ipAddress: '127.0.0.1',
            credentialContext: 'payment-method-uuid',
        ));

        $this->assertSame('evt_checkout_paid', $webhook->providerEventId);
        $this->assertSame('payment-method-uuid', $webhook->credentialContext);
        $this->assertSame('checkout_session', $webhook->resourceType);
        $this->assertSame('paid', $webhook->paymentStatus);
        $this->assertSame('sub_123', $webhook->subscriptionId);
        $this->assertSame(
            'stag-herd:webhook:stripe:payment-method-uuid:evt_checkout_paid',
            $webhook->idempotencyKey('stag-herd:webhook'),
        );
    }

    public function test_it_requires_a_configured_signature_secret(): void
    {
        config()->set('stag-herd.providers.stripe.credentials.webhook_secret', null);

        $this->expectException(InvalidWebhookSignatureException::class);

        (new StripeWebhookParser())->parse(new WebhookPayloadData(
            provider: 'stripe',
            payload: $this->payload(),
            headers: ['stripe-signature' => 't=1,v1=invalid'],
            query: [],
            rawBody: '{}',
            ipAddress: '127.0.0.1',
        ));
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'id' => 'evt_checkout_paid',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'object' => 'checkout.session',
                    'id' => 'cs_123',
                    'status' => 'complete',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_123',
                    'subscription' => 'sub_123',
                    'customer' => 'cus_123',
                ],
            ],
        ];
    }
}
