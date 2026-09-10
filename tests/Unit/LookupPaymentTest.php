<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Tests\Fakes\Repositories\NullPaymentRepository;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Application\Actions\LookupPayment;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Contracts\PaymentProvider;
use Equidna\StagHerd\Support\ProviderRegistry;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Tests\TestCase;
use RuntimeException;

class LookupPaymentTest extends TestCase
{
    public function test_it_uses_the_local_payment_method_before_the_explicit_lookup_method(): void
    {
        $payment = new Payment(
            id: 'payment-1',
            provider: 'mercado_pago',
            method: 'card',
            amount: 12000,
            currency: 'MXN',
            status: PaymentStatusEnum::PENDING,
            providerStatus: 'pending',
            references: new ProviderReferencesData(providerPaymentId: 'provider-123'),
        );

        $provider = new SpyLookupPaymentProvider(['card']);
        $registry = new ProviderRegistry();
        $registry->register($provider);

        $action = new LookupPayment($registry, new InMemoryPaymentRepository($payment));

        $action->handle(new PaymentLookupData(
            provider: 'mercado_pago',
            method: 'pix',
            providerPaymentId: 'provider-123',
        ));

        $this->assertSame('card', $provider->lastLookupMethod);
    }

    public function test_it_throws_a_clear_error_when_method_cannot_be_resolved_for_multi_method_provider(): void
    {
        config()->set('stag-herd.providers.mercado_pago.enabled', true);
        config()->set('stag-herd.providers.mercado_pago.methods.card.enabled', true);
        config()->set('stag-herd.providers.mercado_pago.methods.pix.enabled', true);

        $provider = new SpyLookupPaymentProvider(['card', 'pix']);
        $registry = new ProviderRegistry();
        $registry->register($provider);

        $action = new LookupPayment($registry, new NullPaymentRepository());

        $this->expectException(InvalidPaymentPayloadException::class);
        $this->expectExceptionMessage('multiple methods are enabled: [card, pix]');

        $action->handle(new PaymentLookupData(
            provider: 'mercado_pago',
            providerPaymentId: 'provider-123',
        ));
    }
}

final class SpyLookupPaymentProvider implements PaymentProvider
{
    public ?string $lastLookupMethod = null;

    /**
     * @param list<string> $methods
     */
    public function __construct(
        private readonly array $methods,
    ) {
        //
    }

    public function getName(): string
    {
        return 'mercado_pago';
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        $this->lastLookupMethod = $request->method;

        return PaymentResultData::approved(
            provider: 'mercado_pago',
            method: $request->method ?? 'unknown',
            providerStatus: 'approved',
            references: new ProviderReferencesData(providerPaymentId: $request->providerPaymentId),
            amount: 12000,
            currency: 'MXN',
        );
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function refundPayment(RefundRequestData $request): PaymentResultData
    {
        throw new RuntimeException('Not implemented.');
    }
}
