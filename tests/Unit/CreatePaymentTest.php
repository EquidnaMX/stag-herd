<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Tests\Fakes\PaymentMethods\NullPaymentMethodManager;
use Equidna\StagHerd\Application\Actions\RegisterPaymentMethodFromResult;
use Equidna\StagHerd\Tests\Fakes\Repositories\InMemoryPaymentRepository;
use Equidna\StagHerd\Infrastructure\Providers\Cash\CashProvider;
use Equidna\StagHerd\Application\Actions\RegisterPaymentMethod;
use Equidna\StagHerd\Exceptions\ProviderNotRegisteredException;
use Equidna\StagHerd\Application\Actions\StorePaymentResult;
use Equidna\StagHerd\Application\Actions\CreatePayment;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Support\ProviderRegistry;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Tests\TestCase;

class CreatePaymentTest extends TestCase
{
    public function test_it_creates_an_approved_cash_payment(): void
    {
        $registry = new ProviderRegistry();
        $registry->register(new CashProvider());

        $repository = new InMemoryPaymentRepository();

        $action = new CreatePayment(
            providers: $registry,
            storePaymentResult: $this->storePaymentResult($registry, $repository),
        );

        $payment = $action->handle(
            new PaymentRequestData(
                amount: 12000,
                currency: 'MXN',
                method: 'cash',
                provider: 'cash',
                externalReference: 'ORDER-UNIT-APPROVED',
                payerReference: 'CLIENT-123',
                payerEmail: 'cliente@test.com',
                description: 'Pago unitario aprobado',
                metadata: [
                    'cash_status' => 'approved',
                    'source' => 'unit-test',
                ],
            )
        );

        $this->assertSame('1', $payment->id);
        $this->assertSame('cash', $payment->provider);
        $this->assertSame('cash', $payment->method);
        $this->assertSame(12000, $payment->amount);
        $this->assertSame('MXN', $payment->currency);
        $this->assertSame(PaymentStatusEnum::APPROVED, $payment->status);
        $this->assertSame('approved', $payment->providerStatus);
        $this->assertSame('ORDER-UNIT-APPROVED', $payment->externalReference);
        $this->assertSame('CLIENT-123', $payment->payerReference);
        $this->assertSame('cliente@test.com', $payment->payerEmail);

        $this->assertNotNull($payment->references);
        $this->assertNotNull($payment->references->providerPaymentId);
        $this->assertStringStartsWith('cash_', $payment->references->providerPaymentId);
    }

    public function test_it_creates_a_pending_cash_payment(): void
    {
        $registry = new ProviderRegistry();
        $registry->register(new CashProvider());

        $repository = new InMemoryPaymentRepository();

        $action = new CreatePayment(
            providers: $registry,
            storePaymentResult: $this->storePaymentResult($registry, $repository),
        );

        $payment = $action->handle(
            new PaymentRequestData(
                amount: 5000,
                currency: 'MXN',
                method: 'cash',
                provider: 'cash',
                externalReference: 'ORDER-UNIT-PENDING',
                metadata: [
                    'cash_status' => 'pending',
                ],
            )
        );

        $this->assertSame(PaymentStatusEnum::PENDING, $payment->status);
        $this->assertSame('pending', $payment->providerStatus);
        $this->assertSame('ORDER-UNIT-PENDING', $payment->externalReference);
    }

    public function test_it_creates_a_rejected_cash_payment(): void
    {
        $registry = new ProviderRegistry();
        $registry->register(new CashProvider());

        $repository = new InMemoryPaymentRepository();

        $action = new CreatePayment(
            providers: $registry,
            storePaymentResult: $this->storePaymentResult($registry, $repository),
        );

        $payment = $action->handle(
            new PaymentRequestData(
                amount: 8000,
                currency: 'MXN',
                method: 'cash',
                provider: 'cash',
                externalReference: 'ORDER-UNIT-REJECTED',
                metadata: [
                    'cash_status' => 'rejected',
                ],
            )
        );

        $this->assertSame(PaymentStatusEnum::REJECTED, $payment->status);
        $this->assertSame('rejected', $payment->providerStatus);
        $this->assertSame('ORDER-UNIT-REJECTED', $payment->externalReference);
    }

    public function test_it_fails_when_provider_is_not_registered(): void
    {
        $registry = new ProviderRegistry();

        $repository = new InMemoryPaymentRepository();

        $action = new CreatePayment(
            providers: $registry,
            storePaymentResult: $this->storePaymentResult($registry, $repository),
        );

        $this->expectException(ProviderNotRegisteredException::class);

        $action->handle(
            new PaymentRequestData(
                amount: 12000,
                currency: 'MXN',
                method: 'cash',
                provider: 'fake_provider',
                externalReference: 'ORDER-UNIT-FAKE',
            )
        );
    }

    private function storePaymentResult(
        ProviderRegistry $registry,
        PaymentRepository $repository,
    ): StorePaymentResult {
        return new StorePaymentResult(
            payments: $repository,
            registerPaymentMethodFromResult: new RegisterPaymentMethodFromResult(
                providers: $registry,
                registerPaymentMethod: new RegisterPaymentMethod(new NullPaymentMethodManager()),
            ),
        );
    }
}
