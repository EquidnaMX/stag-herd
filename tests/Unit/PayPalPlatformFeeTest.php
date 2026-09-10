<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Infrastructure\Providers\PayPal\Handlers\PayPalTokenizedCardHandler;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\Handlers\PayPalCheckoutHandler;
use Equidna\StagHerd\Tests\Fakes\Gateways\RecordingPayPalPlatformFeeGateway;
use Equidna\StagHerd\Tests\Fakes\PaymentMethods\NullPaymentMethodManager;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\PayPalResultMapper;
use Equidna\StagHerd\Data\PlatformPaymentContextData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Tests\TestCase;

class PayPalPlatformFeeTest extends TestCase
{
    public function test_checkout_order_adds_platform_fee_to_first_purchase_unit(): void
    {
        $gateway = new RecordingPayPalPlatformFeeGateway();

        $handler = new PayPalCheckoutHandler(
            gateway: $gateway,
            mapper: new PayPalResultMapper(),
        );

        $handler->createPayment(new PaymentRequestData(
            amount: 10000,
            currency: 'MXN',
            method: 'paypal',
            provider: 'paypal',
            externalReference: 'ORDER-123',
            platformContext: new PlatformPaymentContextData(
                sellerReference: 'SELLER-123',
                platformFeeAmount: 1500,
            ),
        ));

        $this->assertSame('SELLER-123', data_get(
            $gateway->lastCreateOrderPayload,
            'purchase_units.0.payee.merchant_id',
        ));

        $this->assertSame('MXN', data_get(
            $gateway->lastCreateOrderPayload,
            'purchase_units.0.payment_instruction.platform_fees.0.amount.currency_code',
        ));

        $this->assertSame('15.00', data_get(
            $gateway->lastCreateOrderPayload,
            'purchase_units.0.payment_instruction.platform_fees.0.amount.value',
        ));
    }

    public function test_checkout_order_does_not_add_platform_fee_when_absent(): void
    {
        $gateway = new RecordingPayPalPlatformFeeGateway();

        $handler = new PayPalCheckoutHandler(
            gateway: $gateway,
            mapper: new PayPalResultMapper(),
        );

        $handler->createPayment(new PaymentRequestData(
            amount: 10000,
            currency: 'MXN',
            method: 'paypal',
            provider: 'paypal',
            externalReference: 'ORDER-123',
        ));

        $this->assertNull(data_get(
            $gateway->lastCreateOrderPayload,
            'purchase_units.0.payment_instruction.platform_fees',
        ));
    }

    public function test_checkout_order_preserves_custom_platform_fees_when_package_fee_is_absent(): void
    {
        $gateway = new RecordingPayPalPlatformFeeGateway();

        $handler = new PayPalCheckoutHandler(
            gateway: $gateway,
            mapper: new PayPalResultMapper(),
        );

        $handler->createPayment(new PaymentRequestData(
            amount: 10000,
            currency: 'MXN',
            method: 'paypal',
            provider: 'paypal',
            externalReference: 'ORDER-123',
            metadata: [
                'paypal' => [
                    'payload' => [
                        'intent' => 'CAPTURE',
                        'purchase_units' => [
                            [
                                'reference_id' => 'ORDER-123',
                                'amount' => [
                                    'currency_code' => 'MXN',
                                    'value' => '100.00',
                                ],
                                'payment_instruction' => [
                                    'platform_fees' => [
                                        [
                                            'amount' => [
                                                'currency_code' => 'MXN',
                                                'value' => '9.99',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ));

        $this->assertSame('9.99', data_get(
            $gateway->lastCreateOrderPayload,
            'purchase_units.0.payment_instruction.platform_fees.0.amount.value',
        ));
    }

    public function test_tokenized_card_order_adds_platform_fee(): void
    {
        $gateway = new RecordingPayPalPlatformFeeGateway();

        $handler = new PayPalTokenizedCardHandler(
            gateway: $gateway,
            mapper: new PayPalResultMapper(),
            paymentMethods: new NullPaymentMethodManager(),
        );

        $handler->createPayment(new PaymentRequestData(
            amount: 10000,
            currency: 'MXN',
            method: 'tokenized_card',
            provider: 'paypal',
            externalReference: 'ORDER-123',
            payerReference: 'CLIENT-123',
            metadata: [
                'paypal' => [
                    'token_id' => 'TOKEN-123',
                    'token_type' => 'BILLING_AGREEMENT',
                ],
            ],
            platformContext: new PlatformPaymentContextData(
                platformFeeAmount: 1500,
            ),
        ));

        $this->assertSame('15.00', data_get(
            $gateway->lastCreateOrderPayload,
            'purchase_units.0.payment_instruction.platform_fees.0.amount.value',
        ));
    }
}
