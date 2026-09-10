<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\Handlers\MercadoPagoCheckoutProHandler;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\Handlers\MercadoPagoCardHandler;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoResultMapper;
use Equidna\StagHerd\Tests\Fakes\Gateways\RecordingMercadoPagoMarketplaceGateway;
use Equidna\StagHerd\Data\PlatformPaymentContextData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Tests\TestCase;

final class MercadoPagoMarketplacePaymentTest extends TestCase
{
    public function test_checkout_pro_adds_marketplace_fee_and_uses_seller_context(): void
    {
        $gateway = new RecordingMercadoPagoMarketplaceGateway();

        $handler = new MercadoPagoCheckoutProHandler(
            gateway: $gateway,
            mapper: new MercadoPagoResultMapper(),
        );

        $handler->createPayment(new PaymentRequestData(
            amount: 10000,
            currency: 'MXN',
            method: 'checkout_pro',
            provider: 'mercado_pago',
            externalReference: 'ORDER-123',
            returnUrl: 'https://example.com/success',
            platformContext: new PlatformPaymentContextData(
                sellerReference: 'SELLER-123',
                platformFeeAmount: 1500,
                providerMetadata: [
                    'mercado_pago' => [
                        'seller_access_token' => 'SELLER_ACCESS_TOKEN',
                    ],
                ],
            ),
        ));

        $this->assertSame(15.0, $gateway->lastCreatePreferencePayload['marketplace_fee']);
        $this->assertSame('SELLER-123', data_get($gateway->lastCreatePreferencePayload, 'metadata.seller_reference'));
        $this->assertSame('SELLER_ACCESS_TOKEN', $gateway->lastCreatePreferenceContext?->sellerAccessToken);
    }

    public function test_card_payment_adds_application_fee_and_uses_seller_context(): void
    {
        $gateway = new RecordingMercadoPagoMarketplaceGateway();

        $handler = new MercadoPagoCardHandler(
            gateway: $gateway,
            mapper: new MercadoPagoResultMapper(),
        );

        $handler->createPayment(new PaymentRequestData(
            amount: 10000,
            currency: 'MXN',
            method: 'card',
            provider: 'mercado_pago',
            payerEmail: 'buyer@example.com',
            externalReference: 'ORDER-123',
            metadata: [
                'mercado_pago' => [
                    'token' => 'CARD_TOKEN',
                    'payment_method_id' => 'visa',
                ],
            ],
            platformContext: new PlatformPaymentContextData(
                sellerReference: 'SELLER-123',
                platformFeeAmount: 1500,
                providerMetadata: [
                    'mercado_pago' => [
                        'seller_access_token' => 'SELLER_ACCESS_TOKEN',
                    ],
                ],
            ),
        ));

        $this->assertSame(15.0, $gateway->lastCreatePaymentPayload['application_fee']);
        $this->assertSame('SELLER-123', data_get($gateway->lastCreatePaymentPayload, 'metadata.seller_reference'));
        $this->assertSame('SELLER_ACCESS_TOKEN', $gateway->lastCreatePaymentContext?->sellerAccessToken);
    }
}
