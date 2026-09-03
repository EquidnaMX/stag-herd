<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Data\MercadoPagoRequestContextData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PlatformPaymentContextData;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\Handlers\MercadoPagoCardHandler;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\Handlers\MercadoPagoCheckoutProHandler;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoResultMapper;
use Equidna\StagHerd\Tests\TestCase;
use RuntimeException;

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

final class RecordingMercadoPagoMarketplaceGateway implements MercadoPagoGateway
{
    /** @var array<string, mixed> */
    public array $lastCreatePaymentPayload = [];

    public ?MercadoPagoRequestContextData $lastCreatePaymentContext = null;

    /** @var array<string, mixed> */
    public array $lastCreatePreferencePayload = [];

    public ?MercadoPagoRequestContextData $lastCreatePreferenceContext = null;

    public function createPayment(
        array $payload,
        ?string $idempotencyKey = null,
        ?string $deviceId = null,
        ?MercadoPagoRequestContextData $context = null,
    ): array {
        $this->lastCreatePaymentPayload = $payload;
        $this->lastCreatePaymentContext = $context;

        return [
            'id' => 123,
            'status' => 'approved',
            'transaction_amount' => $payload['transaction_amount'],
            'currency_id' => 'MXN',
            'external_reference' => $payload['external_reference'] ?? null,
            'metadata' => $payload['metadata'] ?? [],
        ];
    }

    public function createPreference(
        array $payload,
        ?MercadoPagoRequestContextData $context = null,
    ): array {
        $this->lastCreatePreferencePayload = $payload;
        $this->lastCreatePreferenceContext = $context;

        return [
            'id' => 'PREF-123',
            'status' => 'created',
            'init_point' => 'https://www.mercadopago.com/init',
            'external_reference' => $payload['external_reference'] ?? null,
            'metadata' => $payload['metadata'] ?? [],
        ];
    }

    public function getPayment(string $providerPaymentId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function searchPayments(array $filters = []): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function cancelPayment(string $providerPaymentId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function refundPayment(string $providerPaymentId, ?int $amount = null, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createPreapprovalPlan(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getPreapprovalPlan(string $planId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function createPreapproval(array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getPreapproval(string $subscriptionId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function updatePreapproval(string $subscriptionId, array $payload, ?string $idempotencyKey = null): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function getCustomerCards(string $customerId): array
    {
        throw new RuntimeException('Not implemented.');
    }
    public function deleteCustomerCard(string $customerId, string $cardId): array
    {
        throw new RuntimeException('Not implemented.');
    }
}
