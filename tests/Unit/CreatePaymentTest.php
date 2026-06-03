<?php

namespace Equidna\StagHerd\Tests\Unit;

use Equidna\StagHerd\Application\Actions\CreatePayment;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Infrastructure\Providers\Cash\CashProvider;
use Equidna\StagHerd\Support\ProviderRegistry;
use PHPUnit\Framework\TestCase;

class CreatePaymentTest extends TestCase
{
    public function test_it_creates_an_approved_cash_payment(): void
    {
        $registry = new ProviderRegistry();
        $registry->register(new CashProvider());

        $repository = new InMemoryPaymentRepository();

        $action = new CreatePayment(
            providers: $registry,
            payments: $repository,
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
            payments: $repository,
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
            payments: $repository,
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
            payments: $repository,
        );

        $this->expectException(\InvalidArgumentException::class);

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
}

class InMemoryPaymentRepository implements PaymentRepository
{
    /**
     * @var array<string, Payment>
     */
    private array $payments = [];

    private int $nextId = 1;

    public function storeFromResult(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): Payment {
        $payment = new Payment(
            id: (string) $this->nextId++,
            provider: $result->provider,
            method: $result->method,
            amount: $result->amount ?? $request->amount,
            currency: $result->currency ?? $request->currency,
            status: $result->status,
            providerStatus: $result->providerStatus,
            externalReference: $request->externalReference,
            payerReference: $request->payerReference,
            payerEmail: $request->payerEmail,
            references: $result->references ?? new ProviderReferencesData(),
            metadata: $result->metadata ?: $request->metadata,
        );

        $this->payments[$payment->id] = $payment;

        return $payment;
    }

    public function find(int|string $id): ?Payment
    {
        return $this->payments[(string) $id] ?? null;
    }

    public function findByExternalReference(string $externalReference): ?Payment
    {
        foreach ($this->payments as $payment) {
            if ($payment->externalReference === $externalReference) {
                return $payment;
            }
        }

        return null;
    }

    public function findByProviderReference(
        string $provider,
        string $reference,
    ): ?Payment {
        foreach ($this->payments as $payment) {
            if ($payment->provider !== $provider) {
                continue;
            }

            if ($payment->references?->providerPaymentId === $reference) {
                return $payment;
            }

            if ($payment->references?->providerOrderId === $reference) {
                return $payment;
            }

            if ($payment->references?->providerTransactionId === $reference) {
                return $payment;
            }

            if ($payment->references?->providerRefundId === $reference) {
                return $payment;
            }
        }

        return null;
    }

    public function updateFromResult(
        Payment $payment,
        PaymentResultData $result,
    ): Payment {
        $updated = new Payment(
            id: $payment->id,
            provider: $payment->provider,
            method: $payment->method,
            amount: $result->amount ?? $payment->amount,
            currency: $result->currency ?? $payment->currency,
            status: $result->status,
            providerStatus: $result->providerStatus,
            externalReference: $payment->externalReference,
            payerReference: $payment->payerReference,
            payerEmail: $payment->payerEmail,
            references: $result->references ?? $payment->references,
            metadata: array_merge($payment->metadata, $result->metadata),
        );

        $this->payments[$payment->id] = $updated;

        return $updated;
    }
}
