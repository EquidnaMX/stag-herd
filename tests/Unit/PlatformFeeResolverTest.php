<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Data\PlatformPaymentContextData;
use Equidna\StagHerd\Support\PlatformFeeResolver;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Tests\TestCase;

final class PlatformFeeResolverTest extends TestCase
{
    public function test_configured_percentage_overrides_manual_fee_for_paypal(): void
    {
        config()->set('stag-herd.providers.paypal.platform_fee.percentage', 5);

        $fee = (new PlatformFeeResolver())->resolve('paypal', new PaymentRequestData(
            amount: 10000,
            currency: 'MXN',
            method: 'paypal',
            provider: 'paypal',
            platformContext: new PlatformPaymentContextData(
                sellerReference: 'SELLER-123',
                platformFeeAmount: 1500,
            ),
        ));

        $this->assertSame(500, $fee);
    }

    public function test_manual_fee_is_used_when_provider_percentage_is_empty(): void
    {
        config()->set('stag-herd.providers.paypal.platform_fee.percentage', null);

        $fee = (new PlatformFeeResolver())->resolve('paypal', new PaymentRequestData(
            amount: 10000,
            currency: 'MXN',
            method: 'paypal',
            provider: 'paypal',
            platformContext: new PlatformPaymentContextData(
                sellerReference: 'SELLER-123',
                platformFeeAmount: 1500,
            ),
        ));

        $this->assertSame(1500, $fee);
    }

    public function test_no_fee_is_applied_without_platform_context(): void
    {
        config()->set('stag-herd.providers.paypal.platform_fee.percentage', 5);

        $fee = (new PlatformFeeResolver())->resolve('paypal', new PaymentRequestData(
            amount: 10000,
            currency: 'MXN',
            method: 'paypal',
            provider: 'paypal',
            platformContext: new PlatformPaymentContextData(
                platformFeeAmount: 1500,
            ),
        ));

        $this->assertNull($fee);
    }

    public function test_provider_specific_percentage_is_used_for_stripe(): void
    {
        config()->set('stag-herd.providers.stripe.platform_fee.percentage', 7);

        $fee = (new PlatformFeeResolver())->resolve('stripe', new PaymentRequestData(
            amount: 10000,
            currency: 'MXN',
            method: 'card',
            provider: 'stripe',
            platformContext: new PlatformPaymentContextData(
                sellerReference: 'acct_seller_123',
                platformFeeAmount: 1500,
            ),
        ));

        $this->assertSame(700, $fee);
    }

    public function test_provider_specific_percentage_is_used_for_mercado_pago(): void
    {
        config()->set('stag-herd.providers.mercado_pago.platform_fee.percentage', 6);

        $fee = (new PlatformFeeResolver())->resolve('mercado_pago', new PaymentRequestData(
            amount: 10000,
            currency: 'MXN',
            method: 'checkout_pro',
            provider: 'mercado_pago',
            platformContext: new PlatformPaymentContextData(
                platformFeeAmount: 1500,
                providerMetadata: [
                    'mercado_pago' => [
                        'seller_access_token' => 'SELLER-TOKEN',
                    ],
                ],
            ),
        ));

        $this->assertSame(600, $fee);
    }
}
