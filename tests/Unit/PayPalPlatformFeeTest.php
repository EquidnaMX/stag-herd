<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\ManagesPaymentMethods;
use Equidna\StagHerd\Data\PayPalRequestContextData;
use Equidna\StagHerd\Data\PaymentMethodData;
use Equidna\StagHerd\Data\PaymentMethodDeactivateData;
use Equidna\StagHerd\Data\PaymentMethodLookupData;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Equidna\StagHerd\Data\PaymentMethodSetDefaultData;
use Equidna\StagHerd\Data\PaymentMethodsListData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\Handlers\PayPalCheckoutHandler;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\Handlers\PayPalTokenizedCardHandler;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\PayPalResultMapper;
use Equidna\StagHerd\Tests\TestCase;
use RuntimeException;

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
            sellerMerchantId: 'SELLER-123',
            platformFeeAmount: 1500,
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
            paymentMethods: new NullPayPalPlatformFeePaymentMethodManager(),
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
            platformFeeAmount: 1500,
        ));

        $this->assertSame('15.00', data_get(
            $gateway->lastCreateOrderPayload,
            'purchase_units.0.payment_instruction.platform_fees.0.amount.value',
        ));
    }
}

final class RecordingPayPalPlatformFeeGateway implements PayPalGateway
{
    /** @var array<string, mixed>|null */
    public ?array $lastCreateOrderPayload = null;

    public function createOrder(
        array $payload,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        $this->lastCreateOrderPayload = $payload;

        return [
            'id' => 'PAYPAL-ORDER-123',
            'status' => 'CREATED',
            'links' => [],
        ];
    }

    public function getOrder(string $orderId, ?PayPalRequestContextData $context = null): array
    {
        throw new RuntimeException('Not implemented.');
    }

    public function captureOrder(
        string $orderId,
        ?string $idempotencyKey = null,
        ?PayPalRequestContextData $context = null,
    ): array {
        throw new RuntimeException('Not implemented.');
    }

    public function getCapture(string $captureId, ?PayPalRequestContextData $context = null): array
    {
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

    public function getSubscription(string $subscriptionId, ?PayPalRequestContextData $context = null): array
    {
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

    public function getPaymentToken(string $paymentTokenId, ?PayPalRequestContextData $context = null): array
    {
        throw new RuntimeException('Not implemented.');
    }

    public function deletePaymentToken(string $paymentTokenId, ?PayPalRequestContextData $context = null): array
    {
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

    public function verifyWebhookSignature(array $payload, ?PayPalRequestContextData $context = null): bool
    {
        throw new RuntimeException('Not implemented.');
    }
}

final class NullPayPalPlatformFeePaymentMethodManager implements ManagesPaymentMethods
{
    public function registerPaymentMethod(PaymentMethodRegisterData $request): PaymentMethodData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function listPaymentMethods(PaymentMethodsListData $request): array
    {
        throw new RuntimeException('Not implemented.');
    }

    public function setDefaultPaymentMethod(PaymentMethodSetDefaultData $request): PaymentMethodData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function deactivatePaymentMethod(PaymentMethodDeactivateData $request): PaymentMethodData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function resolveUsablePaymentMethod(PaymentMethodLookupData $request): PaymentMethodData
    {
        throw new RuntimeException('Not implemented.');
    }
}
