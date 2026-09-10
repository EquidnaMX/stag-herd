<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Infrastructure\Providers\Stripe\Services\StripeCardPaymentService;
use Equidna\StagHerd\Tests\Fakes\Gateways\RecordingStripeConnectGateway;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeResultMapper;
use Equidna\StagHerd\Data\PlatformPaymentContextData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Tests\TestCase;

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
